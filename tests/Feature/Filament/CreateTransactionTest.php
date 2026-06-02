<?php

namespace Tests\Feature\Filament;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\CapitalAccount;
use App\Models\FundAccount;
use App\Models\User;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

class CreateTransactionTest extends ServiceTestCase
{
    private TransactionService $service;
    private Company $company;
    private Client $client;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = new TransactionService();
        $this->company  = Company::factory()->create();
        $this->client   = Client::factory()->for($this->company)->create();
        $this->operator = User::factory()->create();
        $this->operator->assignRole($this->createRole('super_admin'));

        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createSolicitedFinancing(float $amount = 100000.00): Financing
    {
        return Financing::factory()->withAmount($amount)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
    }

    private function createDisbursedFinancing(float $amount = 100000.00): Financing
    {
        return Financing::factory()->withAmount($amount)->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
        ]);
    }

    // ── Amount calculation tests (disbursement) ─────────────────────────────

    public function test_calculate_amount_for_disbursement_uses_transfer_amount(): void
    {
        $f1 = $this->createSolicitedFinancing(100000.00); // transfer = 95000
        $f2 = $this->createSolicitedFinancing(50000.00);  // transfer = 47500

        $amount = $this->service->calculateAmount('disbursement', [$f1->id, $f2->id]);

        $this->assertEquals(142500.00, $amount);
    }

    public function test_calculate_amount_for_single_disbursement(): void
    {
        $financing = $this->createSolicitedFinancing(80000.00); // transfer = 76000

        $amount = $this->service->calculateAmount('disbursement', [$financing->id]);

        $this->assertEquals(76000.00, $amount);
    }

    public function test_calculate_amount_returns_zero_for_empty_ids(): void
    {
        $this->assertEquals(0.0, $this->service->calculateAmount('disbursement', []));
        $this->assertEquals(0.0, $this->service->calculateRemainingBalance([]));
        $this->assertEquals(0.0, $this->service->calculateTotalOwed([]));
    }

    // ── Amount calculation tests (collection) ───────────────────────────────

    public function test_calculate_total_owed_equals_balance_when_not_overdue(): void
    {
        $this->seedParameters();
        $financing = $this->createDisbursedFinancing(100000.00);

        $total = $this->service->calculateTotalOwed([$financing->id]);

        $this->assertEquals(100000.00, $total);
    }

    public function test_calculate_total_owed_includes_late_fee_when_overdue(): void
    {
        $this->seedParameters();
        $financing = Financing::factory()->withAmount(100000.00)->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
            'due_date'      => now()->subDays(45),
        ]);

        // 45 days overdue → floor(45/30) = 1 tier → 5% × 1 = 5% mora → 5,000
        $total = $this->service->calculateTotalOwed([$financing->id]);

        $this->assertEquals(105000.00, $total);
    }

    public function test_calculate_remaining_balance_for_full_amount(): void
    {
        $financing = $this->createDisbursedFinancing(100000.00);

        $remaining = $this->service->calculateRemainingBalance([$financing->id]);

        $this->assertEquals(100000.00, $remaining);
    }

    public function test_calculate_remaining_balance_after_partial_payment(): void
    {
        $financing = Financing::factory()->withAmount(100000.00)->disbursed()->create([
            'company_id'       => $this->company->id,
            'client_id'        => $this->client->id,
            'registered_by'    => $this->operator->id,
            'collected_amount' => 30000.00,
            'status'           => 'partially_collected',
        ]);

        $remaining = $this->service->calculateRemainingBalance([$financing->id]);

        $this->assertEquals(70000.00, $remaining);
    }

    public function test_calculate_remaining_balance_for_multiple_financings(): void
    {
        $f1 = $this->createDisbursedFinancing(100000.00);
        $f2 = Financing::factory()->withAmount(50000.00)->disbursed()->create([
            'company_id'       => $this->company->id,
            'client_id'        => $this->client->id,
            'registered_by'    => $this->operator->id,
            'collected_amount' => 20000.00,
            'status'           => 'partially_collected',
        ]);

        $remaining = $this->service->calculateRemainingBalance([$f1->id, $f2->id]);

        $this->assertEquals(130000.00, $remaining); // 100000 + 30000
    }

    // ── Disbursement creation with correct amount ───────────────────────────

    public function test_disbursement_saves_correct_amount_from_transfer_amounts(): void
    {
        $this->actingAs($this->operator);

        $f1 = $this->createSolicitedFinancing(100000.00); // transfer = 95000
        $f2 = $this->createSolicitedFinancing(50000.00);  // transfer = 47500

        $txn = $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-001',
            'transaction_date'   => '2026-03-15',
            'registered_by'      => $this->operator->id,
        ], [$f1->id, $f2->id]);

        $this->assertEquals(142500.00, (float) $txn->amount);
        $this->assertEquals('confirmed', $txn->status);
    }

    public function test_disbursement_ignores_form_amount_and_recalculates(): void
    {
        $this->actingAs($this->operator);

        $financing = $this->createSolicitedFinancing(100000.00); // transfer = 95000

        // Simulate form sending amount=null (the bug scenario)
        $txn = $this->service->create([
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'amount'             => null,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-002',
            'transaction_date'   => '2026-03-15',
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        // Service always recalculates for disbursements
        $this->assertEquals(95000.00, (float) $txn->amount);
    }

    // ── Collection creation with partial amounts ────────────────────────────

    public function test_collection_uses_provided_amount_for_partial_payment(): void
    {
        $this->actingAs($this->operator);

        $financing = $this->createDisbursedFinancing(100000.00);

        $txn = $this->service->create([
            'type'               => 'collection',
            'amount'             => 40000.00,
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-003',
            'transaction_date'   => '2026-03-15',
            'registered_by'      => $this->operator->id,
        ], [$financing->id]);

        $this->assertEquals(40000.00, (float) $txn->amount);
        $this->assertEquals('partially_collected', $financing->fresh()->status);
        $this->assertEquals(40000.00, (float) $financing->fresh()->collected_amount);
    }

}
