<?php

namespace Tests\Feature\Commands;

use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyReceivableService;
use App\Services\LedgerVerificationService;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

class ReclassifyRepaymentAsCollectionTest extends ServiceTestCase
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

        $this->actingAs($this->operator);
    }

    /**
     * Financiamiento de 100,000 ya desembolsado, vía el servicio.
     */
    private function disbursedFinancing(?Company $company = null): Financing
    {
        $company ??= $this->company;

        $financing = Financing::factory()->withAmount(100000.00)->create([
            'company_id'    => $company->id,
            'client_id'     => $company->is($this->company)
                ? $this->client->id
                : Client::factory()->for($company)->create()->id,
            'registered_by' => $this->operator->id,
        ]);

        (new TransactionService())->create([
            'type'               => 'disbursement',
            'company_id'         => $company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-' . $financing->id,
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        return $financing->fresh();
    }

    /**
     * La compañía queda debiendo un saldo y "lo devuelve": el par que deja el
     * libro en cero y el capital acreditado, como TX000385 / TX000397.
     */
    private function repayment(float $amount): Transaction
    {
        $service = new CompanyReceivableService();

        $service->record(['company_id' => $this->company->id, 'amount' => $amount]);

        return $service->repay([
            'company_id'         => $this->company->id,
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'DEV-1',
        ]);
    }

    private function ledgerFails(): array
    {
        return (new LedgerVerificationService())->runChecks();
    }

    private function receivableBalance(): float
    {
        return (new CompanyReceivableService())->balanceFor($this->company->id);
    }

    public function test_reclasifica_y_reabre_el_saldo_de_la_compania(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->assertEquals(0.0, $this->receivableBalance());
        $this->assertEmpty($this->ledgerFails(), 'Los ledgers deben arrancar cuadrados.');

        $capitalBefore = (float) CapitalAccount::instance()->balance;
        $fundBefore    = (float) FundAccount::instance()->balance;

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->expectsConfirmation('¿Aplicar la reclasificación?', 'yes')
            ->assertSuccessful();

        $repayment->refresh();
        $financing->refresh();

        $this->assertSame('collection', $repayment->type);
        $this->assertTrue($repayment->financings->contains($financing->id));

        $this->assertSame('partially_collected', $financing->status);
        $this->assertEquals(5000.00, (float) $financing->collected_amount);

        // La devolución dejó de existir: la compañía vuelve a deber.
        $this->assertEquals(5000.00, $this->receivableBalance());

        // El efectivo ya estaba en el banco: ningún ledger se mueve.
        $this->assertEquals($capitalBefore, (float) CapitalAccount::instance()->balance);
        $this->assertEquals($fundBefore, (float) FundAccount::instance()->balance);
        $this->assertEmpty($this->ledgerFails());
    }

    public function test_si_cubre_el_saldo_el_financiamiento_queda_cobrado(): void
    {
        $financing = $this->disbursedFinancing();

        (new TransactionService())->create([
            'type'               => 'collection',
            'amount'             => 95000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-COB',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->expectsConfirmation('¿Aplicar la reclasificación?', 'yes')
            ->assertSuccessful();

        $financing->refresh();

        $this->assertSame('collected', $financing->status);
        $this->assertEquals(100000.00, (float) $financing->collected_amount);
        $this->assertSame(now()->format('Y-m'), $financing->collection_period);
        $this->assertEmpty($this->ledgerFails());
    }

    /**
     * El caso de FN000130: hay un cheque esperando confirmación por el saldo
     * exacto que queda. El financiamiento sigue en pending_payment y es la
     * confirmación del cheque la que lo salda.
     */
    public function test_respeta_un_cobro_pendiente_y_su_confirmacion_salda_el_financiamiento(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->actingAs($this->companyUser);
        $pending = (new TransactionService())->create([
            'type'               => 'collection',
            'payment_method'     => 'check',
            'company_id'         => $this->company->id,
            'amount'             => 95000.00,
            'bank'               => 'BanReservas',
            'transaction_number' => 'CHK-1',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->companyUser->id,
        ], [$financing->id]);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->expectsConfirmation('¿Aplicar la reclasificación?', 'yes')
            ->assertSuccessful();

        $this->assertSame('pending_payment', $financing->fresh()->status);
        $this->assertEquals(5000.00, (float) $financing->fresh()->collected_amount);

        $this->actingAs($this->operator);
        (new TransactionService())->confirm($pending->fresh());

        $this->assertSame('collected', $financing->fresh()->status);
        $this->assertEquals(5000.00, $this->receivableBalance());
        $this->assertEmpty($this->ledgerFails());
    }

    public function test_rechaza_si_el_cobro_pendiente_supera_el_saldo_que_quedara(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->actingAs($this->companyUser);
        (new TransactionService())->create([
            'type'               => 'collection',
            'payment_method'     => 'check',
            'company_id'         => $this->company->id,
            'amount'             => 100000.00,
            'bank'               => 'BanReservas',
            'transaction_number' => 'CHK-1',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->companyUser->id,
        ], [$financing->id]);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
        $this->assertEquals(0.0, (float) $financing->fresh()->collected_amount);
    }

    public function test_rechaza_una_transaccion_que_no_es_devolucion(): void
    {
        $financing    = $this->disbursedFinancing();
        $disbursement = $financing->transactions()->where('type', 'disbursement')->first();

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $disbursement->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('disbursement', $disbursement->fresh()->type);
    }

    public function test_rechaza_un_monto_mayor_al_saldo_del_financiamiento(): void
    {
        $financing = $this->disbursedFinancing();

        (new TransactionService())->create([
            'type'               => 'collection',
            'amount'             => 98000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-COB',
            'transaction_date'   => now()->toDateString(),
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
        $this->assertEquals(98000.00, (float) $financing->fresh()->collected_amount);
    }

    public function test_rechaza_un_financiamiento_de_otra_compania(): void
    {
        $other     = $this->disbursedFinancing(Company::factory()->create());
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $other->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
    }

    public function test_rechaza_un_financiamiento_sin_desembolsar(): void
    {
        $solicited = Financing::factory()->withAmount(100000.00)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $solicited->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
    }

    // ── Autorización: exclusivo de super_admin ─────────────────────────────

    public function test_rechaza_si_no_se_indica_quien_autoriza(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
    }

    public function test_rechaza_a_un_operator(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->operator->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
    }

    public function test_rechaza_un_super_admin_inactivo(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->admin->update(['is_active' => false]);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->assertFailed();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
    }

    public function test_deja_rastro_del_super_admin_que_autorizo(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
        ])->expectsConfirmation('¿Aplicar la reclasificación?', 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString($this->admin->email, (string) $repayment->fresh()->notes);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        $financing = $this->disbursedFinancing();
        $repayment = $this->repayment(5000.00);

        $this->artisan('ops:reclassify-repayment-as-collection', [
            '--as'          => $this->admin->email,
            '--transaction' => $repayment->code,
            '--financing'   => $financing->code,
            '--dry-run'     => true,
        ])->assertSuccessful();

        $this->assertSame('company_repayment', $repayment->fresh()->type);
        $this->assertEquals(0.0, (float) $financing->fresh()->collected_amount);
        $this->assertEquals(0.0, $this->receivableBalance());
    }
}
