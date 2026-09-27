<?php

namespace Tests\Feature\Commands;

use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerVerificationService;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

class ReassignDuplicateDisbursementTest extends ServiceTestCase
{
    private Company $company;
    private Client $client;
    private User $operator;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();
        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();

        // Capital respaldado por un aporte del mismo monto, para que la
        // verificación contable arranque cuadrada.
        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        FundMember::factory()->create([
            'type'         => 'capital',
            'active'       => true,
            'contribution' => 500000,
        ]);

        $this->operator = User::factory()->create();
        $this->operator->assignRole($this->createRole('operator'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole($this->createRole('super_admin'));

        // El desembolso de preparación se registra como operador, igual que en la UI.
        $this->actingAs($this->operator);
    }

    private function solicited(float $amount): Financing
    {
        return Financing::factory()->withAmount($amount)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
    }

    /**
     * Desembolsa de verdad, vía el servicio, para que los ledgers queden
     * exactamente como los deja la operación real.
     */
    private function disburse(Financing $financing): Transaction
    {
        return (new TransactionService())->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);
    }

    private function ledgerFails(): array
    {
        return (new LedgerVerificationService())->runChecks();
    }

    public function test_traslada_el_desembolso_y_deja_los_ledgers_cuadrados(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);

        $this->disburse($duplicate);
        $duplicate->refresh();

        $this->assertSame('disbursed', $duplicate->status);
        $this->assertEmpty($this->ledgerFails(), 'Los ledgers deben arrancar cuadrados.');

        $capitalBefore = (float) CapitalAccount::instance()->balance;
        $fundBefore    = (float) FundAccount::instance()->balance;

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from'   => $duplicate->code,
            '--to'     => $target->code,
            '--reason' => 'Solicitud duplicada por error del cliente.',
        ])->expectsConfirmation('¿Aplicar el traslado?', 'yes')
            ->assertSuccessful();

        $duplicate->refresh();
        $target->refresh();

        $this->assertSame('cancelled', $duplicate->status);
        $this->assertEquals(0.0, (float) $duplicate->disbursed_amount);
        $this->assertEquals(0.0, (float) $duplicate->commission);
        $this->assertNull($duplicate->issue_period);
        $this->assertNotNull($duplicate->cancellation_reason);

        $this->assertSame('disbursed', $target->status);
        $this->assertEquals(95000.00, (float) $target->disbursed_amount);
        $this->assertSame(now()->format('Y-m'), $target->issue_period);

        $this->assertEmpty($this->ledgerFails(), 'Los ledgers deben quedar cuadrados tras el traslado.');

        // Comisiones iguales: ni capital ni fondo se mueven, y el banco tampoco.
        $this->assertEquals($capitalBefore, (float) CapitalAccount::instance()->balance);
        $this->assertEquals($fundBefore, (float) FundAccount::instance()->balance);
    }

    public function test_el_desembolso_original_queda_colgando_del_destino(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);

        $transaction = $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->expectsConfirmation('¿Aplicar el traslado?', 'yes')
            ->assertSuccessful();

        $linked = $transaction->fresh()->financings->pluck('id');

        $this->assertTrue($linked->contains($target->id), 'El desembolso debe apuntar al destino.');
        $this->assertFalse($linked->contains($duplicate->id), 'El desembolso no debe seguir apuntando al duplicado.');
    }

    public function test_destino_mayor_queda_parcialmente_desembolsado_y_cuadrado(): void
    {
        $duplicate = $this->solicited(100000.00);   // transfer 95,000
        $target    = $this->solicited(200000.00);   // transfer 190,000

        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->expectsConfirmation('¿Aplicar el traslado?', 'yes')
            ->assertSuccessful();

        $target->refresh();

        $this->assertSame('partially_disbursed', $target->status);
        $this->assertEquals(95000.00, (float) $target->disbursed_amount);
        $this->assertEmpty($this->ledgerFails());
    }

    /**
     * El caso completo del destino mayor: se reasigna, y después se completa la
     * partida que falta por el flujo real. La comisión del destino se retiene una
     * sola vez y el issue_period no se mueve.
     */
    public function test_completar_la_partida_restante_no_retiene_la_comision_dos_veces(): void
    {
        $duplicate = $this->solicited(100000.00);   // transfer 95,000 / comisión 5,000
        $target    = $this->solicited(200000.00);   // transfer 190,000 / comisión 10,000

        $this->disburse($duplicate);
        $periodoOriginal = $duplicate->fresh()->issue_period;

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->expectsConfirmation('¿Aplicar el traslado?', 'yes')
            ->assertSuccessful();

        $target->refresh();
        $this->assertSame('partially_disbursed', $target->status);
        $this->assertEquals(95000.00, (float) $target->remainingToDisburse());

        $fundAntes = (float) FundAccount::instance()->balance;

        // Partida restante, exactamente como la registraría el operador en la UI.
        (new TransactionService())->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'amount'             => 95000.00,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-RESTO',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$target->id]);

        $target->refresh();

        $this->assertSame('disbursed', $target->status);
        $this->assertEquals(190000.00, (float) $target->disbursed_amount);
        $this->assertSame($periodoOriginal, $target->issue_period, 'El issue_period no debe moverse.');

        // La comisión ya se había retenido en el traslado. La segunda partida solo
        // debe mover el impuesto del desembolso, nunca comisión de nuevo.
        $impuesto = (float) Transaction::where('type', 'expense')
            ->where('transaction_number', 'like', 'TX%')
            ->orderByDesc('id')
            ->value('amount');

        $this->assertEquals(
            round($fundAntes - $impuesto, 2),
            round((float) FundAccount::instance()->balance, 2),
            'El fondo solo debe bajar por el impuesto, sin doble retención de comisión.'
        );

        $this->assertEmpty($this->ledgerFails());
    }

    public function test_rechaza_destino_menor_que_el_efectivo_desembolsado(): void
    {
        $duplicate = $this->solicited(100000.00);   // transfer 95,000
        $target    = $this->solicited(50000.00);    // transfer 47,500

        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
        $this->assertEmpty($this->ledgerFails());
    }

    public function test_rechaza_si_el_periodo_ya_esta_cerrado(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);

        $this->disburse($duplicate);

        MonthlyClosing::create([
            'period'                => now()->format('Y-m'),
            'total_commissions'     => 5000,
            'total_expenses'        => 0,
            'total_fixed'           => 0,
            'net_profit'            => 5000,
            'reserve'               => 1000,
            'post_reserve'          => 4000,
            'in_kind_payment'       => 2000,
            'available_for_capital' => 2000,
            'executed_by'           => $this->operator->id,
            'closed_at'             => now(),
        ]);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_rechaza_destino_de_otra_compania(): void
    {
        $duplicate  = $this->solicited(100000.00);
        $otherOwner = Company::factory()->create();
        $target     = Financing::factory()->withAmount(100000.00)->create([
            'company_id'    => $otherOwner->id,
            'client_id'     => Client::factory()->for($otherOwner)->create()->id,
            'registered_by' => $this->operator->id,
        ]);

        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_rechaza_duplicado_con_cobros_aplicados(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);

        $this->disburse($duplicate);

        (new TransactionService())->create([
            'type'               => 'collection',
            'amount'             => 20000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-COB',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$duplicate->id]);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('partially_collected', $duplicate->fresh()->status);
    }

    // ── Autorización: exclusivo de super_admin ─────────────────────────────

    public function test_rechaza_si_no_se_indica_quien_autoriza(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);
        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_rechaza_a_un_operator(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);
        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->operator->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_rechaza_un_super_admin_inactivo(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);
        $this->disburse($duplicate);

        $this->admin->update(['is_active' => false]);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_rechaza_un_correo_inexistente(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);
        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => 'nadie@corneliodev.com',
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->assertFailed();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
    }

    public function test_deja_rastro_del_super_admin_que_autorizo(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);
        $this->disburse($duplicate);

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from' => $duplicate->code,
            '--to'   => $target->code,
        ])->expectsConfirmation('¿Aplicar el traslado?', 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString(
            $this->admin->email,
            (string) $duplicate->fresh()->cancellation_reason
        );
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $duplicate = $this->solicited(100000.00);
        $target    = $this->solicited(100000.00);

        $this->disburse($duplicate);

        $capitalBefore = (float) CapitalAccount::instance()->balance;

        $this->artisan('ops:reassign-duplicate-disbursement', [
            '--as'   => $this->admin->email,
            '--from'    => $duplicate->code,
            '--to'      => $target->code,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame('disbursed', $duplicate->fresh()->status);
        $this->assertSame('solicited', $target->fresh()->status);
        $this->assertEquals($capitalBefore, (float) CapitalAccount::instance()->balance);
    }
}
