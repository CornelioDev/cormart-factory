<?php

namespace Tests\Feature\Services;

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

/**
 * Corregir y rechazar un cobro que todavía espera confirmación.
 */
class PendingCollectionTest extends ServiceTestCase
{
    private Company $company;
    private Client $client;
    private User $operator;
    private User $companyUser;
    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();
        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();

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

        $this->service = new TransactionService();
    }

    private function disbursedFinancing(float $amount = 100000.00): Financing
    {
        $this->actingAs($this->operator);

        $financing = Financing::factory()->withAmount($amount)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);

        $this->service->create([
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
     * Cobro registrado por el company_user: queda pendiente. Deja al operador
     * autenticado, que es quien lo gestiona después.
     */
    private function pendingCollection(Financing $financing, float $amount, string $method = 'check', string $number = 'REF-1'): Transaction
    {
        $this->actingAs($this->companyUser);

        $transaction = $this->service->create([
            'type'               => 'collection',
            'payment_method'     => $method,
            'company_id'         => $this->company->id,
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => $number,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->companyUser->id,
        ], [$financing->id]);

        $this->actingAs($this->operator);

        return $transaction;
    }

    private function assertLedgersBalanced(): void
    {
        $this->assertEmpty((new LedgerVerificationService())->runChecks(), 'Los ledgers deben quedar cuadrados.');
    }

    // ── Corregir ───────────────────────────────────────────────────────────

    public function test_corregir_el_monto_no_toca_nada_y_deja_rastro(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);
        $capital    = (float) CapitalAccount::instance()->balance;

        $corrected = $this->service->correctPendingCollection(
            $collection,
            ['amount' => 80000.00],
            'El cheque era por menos.'
        );

        $this->assertSame('pending', $corrected->status);
        $this->assertEquals(80000.00, (float) $corrected->amount);
        $this->assertStringContainsString('100,000.00', $corrected->notes);
        $this->assertStringContainsString('El cheque era por menos.', $corrected->notes);
        $this->assertStringContainsString($this->operator->email, $corrected->notes);

        $this->assertSame('pending_payment', $financing->fresh()->status);
        $this->assertEquals($capital, (float) CapitalAccount::instance()->balance);
        $this->assertSame(0, CompanyCredit::count());
        $this->assertLedgersBalanced();
    }

    /**
     * El cobro se registró como cheque pero el dinero entró por transferencia.
     * Tras corregir el método, confirmar acredita el capital en vez de dejar el
     * dinero anotado en manos de la compañía.
     */
    public function test_corregir_el_metodo_cambia_a_donde_va_el_dinero_al_confirmar(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00, 'check');
        $capital    = (float) CapitalAccount::instance()->balance;

        $this->service->correctPendingCollection(
            $collection,
            ['payment_method' => 'transfer'],
            'Fue transferencia.'
        );
        $this->service->confirm($collection->fresh());

        $this->assertSame('collected', $financing->fresh()->status);
        $this->assertSame(0, CompanyCredit::count());
        $this->assertEquals(round($capital + 100000.00, 2), (float) CapitalAccount::instance()->balance);
        $this->assertLedgersBalanced();
    }

    public function test_corregir_rechaza_un_monto_mayor_a_lo_adeudado(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('supera lo adeudado');

        $this->service->correctPendingCollection($collection, ['amount' => 100000.01], 'x');
    }

    public function test_corregir_rechaza_un_cobro_ya_confirmado(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);
        $this->service->confirm($collection);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo se puede modificar un cobro pendiente');

        $this->service->correctPendingCollection($collection->fresh(), ['amount' => 50000.00], 'x');
    }

    public function test_corregir_rechaza_si_no_cambia_nada(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No hay ningún cambio');

        $this->service->correctPendingCollection(
            $collection,
            ['amount' => 80000.00, 'payment_method' => 'check', 'bank' => 'BanReservas', 'transaction_number' => 'REF-1'],
            'x'
        );
    }

    public function test_corregir_rechaza_un_numero_que_ya_usa_otra_transaccion(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ya existe otra transacción');

        // El número del desembolso de preparación.
        $this->service->correctPendingCollection(
            $collection,
            ['transaction_number' => 'TXN-' . $financing->id],
            'x'
        );
    }

    public function test_un_company_user_no_puede_corregir(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 80000.00);

        $this->actingAs($this->companyUser);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo un usuario interno');

        $this->service->correctPendingCollection($collection, ['amount' => 50000.00], 'x');
    }

    // ── Rechazar ───────────────────────────────────────────────────────────

    public function test_rechazar_devuelve_el_financiamiento_a_por_cobrar(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);
        $capital    = (float) CapitalAccount::instance()->balance;

        $rejected = $this->service->rejectPendingCollection($collection, 'El cheque rebotó.');

        $this->assertSame('rejected', $rejected->status);
        $this->assertStringContainsString('El cheque rebotó.', $rejected->notes);
        $this->assertStringContainsString($this->operator->email, $rejected->notes);

        $financing->refresh();
        $this->assertSame('disbursed', $financing->status);
        $this->assertEquals(0.0, (float) $financing->collected_amount);

        $this->assertEquals($capital, (float) CapitalAccount::instance()->balance);
        $this->assertSame(0, CompanyCredit::count());
        $this->assertLedgersBalanced();
    }

    public function test_rechazar_respeta_los_abonos_previos(): void
    {
        $financing = $this->disbursedFinancing();

        // Abono confirmado por el operador, y luego un cobro pendiente por el resto.
        $this->service->create([
            'type'               => 'collection',
            'amount'             => 40000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'ABONO-1',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        $collection = $this->pendingCollection($financing->fresh(), 60000.00);
        $this->assertSame('pending_payment', $financing->fresh()->status);

        $this->service->rejectPendingCollection($collection, 'No corresponde.');

        $this->assertSame('partially_collected', $financing->fresh()->status);
        $this->assertEquals(40000.00, (float) $financing->fresh()->collected_amount);
        $this->assertLedgersBalanced();
    }

    /**
     * El número es único en la tabla: rechazar lo libera para que el pago real
     * pueda registrarse con su misma referencia.
     */
    public function test_rechazar_libera_el_numero_de_transaccion(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00, 'transfer', 'REF-777');

        $this->service->rejectPendingCollection($collection, 'Monto equivocado, lo registran de nuevo.');

        $this->assertNotSame('REF-777', $collection->fresh()->transaction_number);
        $this->assertStringStartsWith('REF-777', $collection->fresh()->transaction_number);

        $again = $this->pendingCollection($financing->fresh(), 90000.00, 'transfer', 'REF-777');

        $this->assertSame('pending', $again->status);
        $this->assertSame('REF-777', $again->transaction_number);
    }

    public function test_un_cobro_rechazado_no_se_puede_confirmar(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);
        $this->service->rejectPendingCollection($collection, 'x');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo se pueden confirmar transacciones en estado pendiente');

        $this->service->confirm($collection->fresh());
    }

    public function test_un_company_user_no_puede_rechazar(): void
    {
        $financing  = $this->disbursedFinancing();
        $collection = $this->pendingCollection($financing, 100000.00);

        $this->actingAs($this->companyUser);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo un usuario interno');

        $this->service->rejectPendingCollection($collection, 'x');
    }
}
