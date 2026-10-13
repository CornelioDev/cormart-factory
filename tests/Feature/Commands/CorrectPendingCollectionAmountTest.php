<?php

namespace Tests\Feature\Commands;

use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyCredit;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerVerificationService;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

class CorrectPendingCollectionAmountTest extends ServiceTestCase
{
    private Company $company;
    private Client $client;
    private User $operator;
    private User $companyUser;
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

        $this->companyUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->companyUser->assignRole($this->createRole('company_user'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole($this->createRole('super_admin'));
    }

    /**
     * Financiamiento de 100,000 ya desembolsado, vía el servicio, para que los
     * ledgers queden exactamente como los deja la operación real.
     */
    private function disbursedFinancing(): Financing
    {
        $this->actingAs($this->operator);

        $financing = Financing::factory()->withAmount(100000.00)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);

        (new TransactionService())->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        return $financing->fresh();
    }

    /**
     * Cobro registrado por el company_user: queda pendiente, igual que en la UI.
     */
    private function pendingCollection(Financing $financing, float $amount, string $method = 'check'): Transaction
    {
        $this->actingAs($this->companyUser);

        return (new TransactionService())->create([
            'type'               => 'collection',
            'payment_method'     => $method,
            'company_id'         => $this->company->id,
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'CHK-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->companyUser->id,
        ], [$financing->id]);
    }

    private function ledgerFails(): array
    {
        return (new LedgerVerificationService())->runChecks();
    }

    public function test_corrige_el_monto_y_el_cobro_sigue_pendiente(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->assertSame('pending', $collection->status);

        $capitalBefore = (float) CapitalAccount::instance()->balance;
        $fundBefore    = (float) FundAccount::instance()->balance;

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
            '--reason'      => 'El cheque era por un monto menor.',
        ])->expectsConfirmation('¿Aplicar la corrección?', 'yes')
            ->assertSuccessful();

        $collection->refresh();

        $this->assertSame('pending', $collection->status);
        $this->assertEquals(80000.00, (float) $collection->amount);

        // Un cobro pendiente no toca nada: ni financiamiento, ni ledgers, ni saldos.
        $this->assertSame('pending_payment', $financing->fresh()->status);
        $this->assertEquals(0.0, (float) $financing->fresh()->collected_amount);
        $this->assertEquals($capitalBefore, (float) CapitalAccount::instance()->balance);
        $this->assertEquals($fundBefore, (float) FundAccount::instance()->balance);
        $this->assertSame(0, CompanyCredit::count());
        $this->assertEmpty($this->ledgerFails());
    }

    /**
     * El caso de TX000440: tras corregir, la confirmación normal aplica el monto
     * del cheque y el financiamiento queda con el resto por cobrar.
     */
    public function test_confirmar_despues_aplica_el_monto_corregido(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->expectsConfirmation('¿Aplicar la corrección?', 'yes')
            ->assertSuccessful();

        $this->actingAs($this->operator);
        (new TransactionService())->confirm($collection->fresh());

        $financing->refresh();

        $this->assertSame('partially_collected', $financing->status);
        $this->assertEquals(80000.00, (float) $financing->collected_amount);
        $this->assertEquals(20000.00, $financing->remainingBalance());

        // Cheque: el efectivo queda en la compañía por el monto corregido.
        $this->assertEquals(80000.00, (float) CompanyCredit::sum('amount'));
        $this->assertEmpty($this->ledgerFails());
    }

    public function test_rechaza_un_cobro_ya_confirmado(): void
    {
        $financing = $this->disbursedFinancing();

        // Registrado por un operador: se confirma solo.
        $collection = (new TransactionService())->create([
            'type'               => 'collection',
            'amount'             => 50000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-COB',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '40000.00',
        ])->assertFailed();

        $this->assertEquals(50000.00, (float) $collection->fresh()->amount);
        $this->assertEquals(50000.00, (float) $financing->fresh()->collected_amount);
    }

    public function test_rechaza_una_transaccion_que_no_es_cobro(): void
    {
        $financing    = $this->disbursedFinancing();
        $disbursement = $financing->transactions()->where('type', 'disbursement')->first();

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $disbursement->code,
            '--amount'      => '1000.00',
        ])->assertFailed();

        $this->assertEquals(95000.00, (float) $disbursement->fresh()->amount);
    }

    public function test_rechaza_un_monto_mayor_a_lo_adeudado(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '100000.01',
        ])->assertFailed();

        $this->assertEquals(80000.00, (float) $collection->fresh()->amount);
    }

    public function test_rechaza_montos_invalidos(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        foreach (['0', '-500', '131,770.60', 'abc', '80000.00'] as $amount) {
            $this->artisan('ops:correct-pending-collection-amount', [
                '--as'          => $this->admin->email,
                '--transaction' => $collection->code,
                '--amount'      => $amount,
            ])->assertFailed();
        }

        $this->assertEquals(80000.00, (float) $collection->fresh()->amount);
    }

    public function test_rechaza_una_transaccion_inexistente(): void
    {
        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => 'TX999999',
            '--amount'      => '1000.00',
        ])->assertFailed();
    }

    // ── Autorización: exclusivo de super_admin ─────────────────────────────

    public function test_rechaza_si_no_se_indica_quien_autoriza(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->assertFailed();

        $this->assertEquals(100000.00, (float) $collection->fresh()->amount);
    }

    public function test_rechaza_a_un_operator(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->operator->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->assertFailed();

        $this->assertEquals(100000.00, (float) $collection->fresh()->amount);
    }

    public function test_rechaza_un_super_admin_inactivo(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->admin->update(['is_active' => false]);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->assertFailed();

        $this->assertEquals(100000.00, (float) $collection->fresh()->amount);
    }

    public function test_deja_rastro_del_super_admin_que_autorizo(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->expectsConfirmation('¿Aplicar la corrección?', 'yes')
            ->assertSuccessful();

        $notes = (string) $collection->fresh()->notes;

        $this->assertStringContainsString($this->admin->email, $notes);
        $this->assertStringContainsString('100,000.00', $notes);
    }

    public function test_sin_confirmar_no_escribe_nada(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
        ])->expectsConfirmation('¿Aplicar la corrección?', 'no')
            ->assertSuccessful();

        $this->assertEquals(100000.00, (float) $collection->fresh()->amount);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->artisan('ops:correct-pending-collection-amount', [
            '--as'          => $this->admin->email,
            '--transaction' => $collection->code,
            '--amount'      => '80000.00',
            '--dry-run'     => true,
        ])->assertSuccessful();

        $this->assertEquals(100000.00, (float) $collection->fresh()->amount);
        $this->assertNull($collection->fresh()->notes);
    }
}
