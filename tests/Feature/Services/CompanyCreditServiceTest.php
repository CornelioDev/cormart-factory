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
use App\Services\CompanyCreditService;
use App\Services\LedgerVerificationService;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

/**
 * Cobros en cheque, saldo a favor del fondo y liquidaciones.
 *
 * La aserción que de verdad importa en casi todos los casos es
 * assertLedgersBalanced(): el efectivo que una compañía retiene no puede dejar a
 * los ledgers diciendo que el fondo tiene dinero que no está en el banco.
 */
class CompanyCreditServiceTest extends ServiceTestCase
{
    private TransactionService $service;
    private CompanyCreditService $credits;
    private Company $company;
    private Client $client;
    private User $operator;
    private User $companyUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();
        $this->service = new TransactionService();
        $this->credits = new CompanyCreditService();
        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();

        // El saldo inicial de capital tiene que venir de un aporte real: la invariante
        // lo deriva de FundMember.contribution, no de un balance puesto a mano.
        FundMember::factory()->create([
            'type'            => 'capital',
            'contribution'    => 500000.00,
            'fund_percentage' => 100.0,
            'active'          => true,
        ]);

        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        $this->operator = User::factory()->create();
        $this->operator->assignRole($this->createRole('operator'));

        $this->companyUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->companyUser->assignRole($this->createRole('company_user'));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function solicitedFinancing(float $amount = 100000.00): Financing
    {
        return Financing::factory()->withAmount($amount)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
    }

    /**
     * Desembolsa de verdad, pasando por el servicio, para que el ledger quede
     * consistente: el estado `disbursed()` de la factory marca el financiamiento
     * pero nunca debita capital, y entonces la invariante no podría verificarse.
     */
    private function createDisbursedFinancing(float $amount = 100000.00): Financing
    {
        $financing = $this->solicitedFinancing($amount);

        $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-' . fake()->unique()->numerify('######'),
            'transaction_date'   => '2026-01-10',
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        return $financing->fresh();
    }

    private function createOverdueFinancing(float $amount = 100000.00, string $dueDate = '2026-01-01'): Financing
    {
        $financing = $this->createDisbursedFinancing($amount);
        $financing->updateQuietly(['due_date' => $dueDate]);

        return $financing->fresh();
    }

    private function checkCollection(array $overrides = []): array
    {
        return array_merge([
            'type'               => 'collection',
            'payment_method'     => 'check',
            'bank'               => 'BHD',
            'transaction_number' => 'CHK-' . fake()->unique()->numerify('######'),
            'transaction_date'   => '2026-01-30',
            'registered_by'      => $this->operator->id,
        ], $overrides);
    }

    private function settlementData(array $overrides = []): array
    {
        return array_merge([
            'type'               => 'settlement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'STL-' . fake()->unique()->numerify('######'),
            'transaction_date'   => '2026-02-10',
            'registered_by'      => $this->operator->id,
        ], $overrides);
    }

    private function capital(): float
    {
        return round((float) CapitalAccount::instance()->balance, 2);
    }

    private function fund(): float
    {
        return round((float) FundAccount::instance()->balance, 2);
    }

    private function assertLedgersBalanced(): void
    {
        $failed = (new LedgerVerificationService())->runChecks();

        $this->assertSame([], $failed, 'Los ledgers quedaron descuadrados: ' . json_encode($failed));
    }

    // ── Cobro en cheque ────────────────────────────────────────────────────

    public function test_check_collection_does_not_move_the_ledgers(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $capitalBefore = $this->capital();
        $fundBefore    = $this->fund();

        $this->service->create($this->checkCollection(), [$financing->id]);

        $this->assertEquals($capitalBefore, $this->capital());
        $this->assertEquals($fundBefore, $this->fund());
        $this->assertLedgersBalanced();
    }

    public function test_check_collection_still_settles_the_financing(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $this->service->create($this->checkCollection(), [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('collected', $f->status);
        $this->assertEquals(100000.00, (float) $f->collected_amount);
    }

    public function test_check_collection_creates_accrual_for_the_company(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $txn = $this->service->create($this->checkCollection(), [$financing->id]);

        $accrual = CompanyCredit::where('transaction_id', $txn->id)->where('type', 'accrual')->first();

        $this->assertNotNull($accrual);
        $this->assertEquals(100000.00, (float) $accrual->amount);
        $this->assertEquals(100000.00, (float) $accrual->capital_amount);
        $this->assertEquals(0.00, (float) $accrual->late_fee_amount);
        $this->assertEquals(100000.00, $this->credits->balanceFor($this->company->id));
    }

    public function test_check_collection_splits_capital_and_late_fee_in_the_accrual(): void
    {
        $this->actingAs($this->operator);
        // Vence 2026-01-01, cobra 2026-02-15 → 45 días → 1 tramo → 5% mora = 5,000
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        $txn = $this->service->create(
            $this->checkCollection(['amount' => 105000.00, 'transaction_date' => '2026-02-15']),
            [$financing->id]
        );

        $accrual = CompanyCredit::where('transaction_id', $txn->id)->where('type', 'accrual')->first();

        $this->assertEquals(105000.00, (float) $accrual->amount);
        $this->assertEquals(100000.00, (float) $accrual->capital_amount);
        $this->assertEquals(5000.00, (float) $accrual->late_fee_amount);
        $this->assertLedgersBalanced();
    }

    public function test_transfer_collection_still_credits_the_ledgers(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        $capitalBefore = $this->capital();
        $fundBefore    = $this->fund();

        $this->service->create(
            $this->checkCollection([
                'payment_method'   => 'transfer',
                'amount'           => 105000.00,
                'transaction_date' => '2026-02-15',
            ]),
            [$financing->id]
        );

        $this->assertEquals(round($capitalBefore + 100000.00, 2), $this->capital());
        $this->assertEquals(round($fundBefore + 5000.00, 2), $this->fund());
        $this->assertCount(0, CompanyCredit::all());
        $this->assertLedgersBalanced();
    }

    public function test_accrual_is_not_duplicated(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $txn = $this->service->create($this->checkCollection(), [$financing->id]);

        $this->assertNull($this->credits->accrue($txn->fresh(), 100000.00, 0.00));
        $this->assertEquals(1, CompanyCredit::where('transaction_id', $txn->id)->count());
    }

    // ── Liquidación ────────────────────────────────────────────────────────

    public function test_settlement_credits_capital_and_fund_by_composition(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        $this->service->create(
            $this->checkCollection(['amount' => 105000.00, 'transaction_date' => '2026-02-15']),
            [$financing->id]
        );

        $capitalBefore = $this->capital();
        $fundBefore    = $this->fund();

        $this->service->create($this->settlementData(['amount' => 105000.00]), []);

        // Capital recuperado → CapitalAccount; mora → FundAccount.
        $this->assertEquals(round($capitalBefore + 100000.00, 2), $this->capital());
        $this->assertEquals(round($fundBefore + 5000.00, 2), $this->fund());
        $this->assertEquals(0.00, $this->credits->balanceFor($this->company->id));
        $this->assertLedgersBalanced();
    }

    public function test_partial_settlement_splits_proportionally(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        $this->service->create(
            $this->checkCollection(['amount' => 105000.00, 'transaction_date' => '2026-02-15']),
            [$financing->id]
        );

        $capitalBefore = $this->capital();
        $fundBefore    = $this->fund();

        // Liquida 52,500 = la mitad. Mora: 52,500 × (5,000/105,000) = 2,500
        $this->service->create($this->settlementData(['amount' => 52500.00]), []);

        $this->assertEquals(round($capitalBefore + 50000.00, 2), $this->capital());
        $this->assertEquals(round($fundBefore + 2500.00, 2), $this->fund());
        $this->assertEquals(52500.00, $this->credits->balanceFor($this->company->id));
        $this->assertLedgersBalanced();
    }

    public function test_settlement_cannot_exceed_outstanding_balance(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$financing->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('supera el saldo pendiente');

        $this->service->create($this->settlementData(['amount' => 100000.01]), []);
    }

    public function test_settlement_requires_an_internal_user(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$financing->id]);

        $this->actingAs($this->companyUser);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Solo un operador puede registrar la liquidación');

        $this->service->create(
            $this->settlementData(['amount' => 50000.00, 'registered_by' => $this->companyUser->id]),
            []
        );
    }

    public function test_settlement_is_not_duplicated(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$financing->id]);

        $settlement = $this->service->create($this->settlementData(['amount' => 100000.00]), []);

        $this->assertNull($this->credits->settle($settlement->fresh()));
        $this->assertEquals(1, CompanyCredit::where('transaction_id', $settlement->id)->count());
    }

    // ── Saldo aplicado a un desembolso ─────────────────────────────────────

    public function test_credit_applied_reduces_the_capital_debit(): void
    {
        $this->actingAs($this->operator);
        $collected = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$collected->id]);

        $capitalBefore = $this->capital();

        // Nuevo financiamiento: transfer_amount 95,000, comisión 5,000.
        $nuevo = $this->solicitedFinancing(100000.00);

        $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'credit_applied'     => 30000.00,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-900',
            'transaction_date'   => '2026-02-01',
            'registered_by'      => $this->operator->id,
        ], [$nuevo->id]);

        // Débito normal sería 95,000 + 5,000 = 100,000. Con 30,000 de saldo: 70,000.
        $this->assertEquals(round($capitalBefore - 70000.00, 2), $this->capital());
        $this->assertEquals(70000.00, $this->credits->balanceFor($this->company->id));
        $this->assertLedgersBalanced();
    }

    public function test_credit_applied_records_an_application_movement(): void
    {
        $this->actingAs($this->operator);
        $collected = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$collected->id]);

        $nuevo = $this->solicitedFinancing(100000.00);

        $txn = $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'credit_applied'     => 30000.00,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-901',
            'transaction_date'   => '2026-02-01',
            'registered_by'      => $this->operator->id,
        ], [$nuevo->id]);

        $application = CompanyCredit::where('transaction_id', $txn->id)->where('type', 'application')->first();

        $this->assertNotNull($application);
        $this->assertEquals(-30000.00, (float) $application->amount);
        $this->assertEquals(-30000.00, (float) $application->capital_amount);
        $this->assertEquals(0.00, (float) $application->late_fee_amount);
        // transfer_amount 95,000 − 30,000 de saldo aplicado = 65,000 transferidos.
        $this->assertEquals(65000.00, $txn->fresh()->netTransferred());
    }

    public function test_credit_applied_cannot_exceed_available_credit(): void
    {
        $this->actingAs($this->operator);
        $collected = $this->createDisbursedFinancing(100000.00);
        $this->service->create($this->checkCollection(), [$collected->id]);

        $nuevo = $this->solicitedFinancing(200000.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('supera el saldo de capital disponible');

        $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'credit_applied'     => 100000.01,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-902',
            'transaction_date'   => '2026-02-01',
            'registered_by'      => $this->operator->id,
        ], [$nuevo->id]);
    }

    public function test_late_fee_credit_cannot_be_applied_to_a_disbursement(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        // Cobro en cheque de solo la mora: 5,000 de mora y nada de capital.
        $this->service->create(
            $this->checkCollection(['amount' => 5000.00, 'transaction_date' => '2026-02-15']),
            [$financing->id]
        );

        $balance = $this->credits->balanceFor($this->company->id);
        $capital = $this->credits->availableForApplication($this->company->id);

        // Hay saldo, pero su parte de capital es lo único aplicable.
        $this->assertGreaterThan(0, $balance);
        $this->assertLessThan($balance, $capital);
        $this->assertLedgersBalanced();
    }

    public function test_disbursement_fully_covered_by_credit_needs_no_bank_data(): void
    {
        $this->actingAs($this->operator);
        $collected = $this->createDisbursedFinancing(200000.00);
        $this->service->create($this->checkCollection(), [$collected->id]);

        $nuevo = $this->solicitedFinancing(100000.00);

        $txn = $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'credit_applied'     => 95000.00,
            'bank'               => null,
            'transaction_number' => null,
            'transaction_date'   => '2026-02-01',
            'registered_by'      => $this->operator->id,
        ], [$nuevo->id]);

        $this->assertEquals(0.00, $txn->fresh()->netTransferred());
        $this->assertLedgersBalanced();
    }

    public function test_disbursement_with_cash_movement_still_requires_bank_data(): void
    {
        $this->actingAs($this->operator);
        $nuevo = $this->solicitedFinancing(100000.00);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('banco y el número de transacción son obligatorios');

        $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => null,
            'transaction_number' => null,
            'transaction_date'   => '2026-02-01',
            'registered_by'      => $this->operator->id,
        ], [$nuevo->id]);
    }
}
