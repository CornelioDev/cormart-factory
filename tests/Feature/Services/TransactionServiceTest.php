<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\CapitalAccount;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Tests\ServiceTestCase;

class TransactionServiceTest extends ServiceTestCase
{
    private TransactionService $service;
    private Company $company;
    private Client $client;
    private User $operator;
    private User $superAdmin;
    private User $companyUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();
        $this->service = new TransactionService();
        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();

        FundAccount::create(['balance' => 0]);
        CapitalAccount::create(['balance' => 500000]);

        $operatorRole   = $this->createRole('operator');
        $superAdminRole = $this->createRole('super_admin');
        $companyRole    = $this->createRole('company_user');

        $this->operator = User::factory()->create();
        $this->operator->assignRole($operatorRole);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        $this->companyUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->companyUser->assignRole($companyRole);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

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

    private function disbursementData(): array
    {
        return [
            'type'               => 'disbursement',
            'company_id'         => $this->company->id,
            'bank'               => 'BanReservas',
            'transaction_number' => 'TXN-001',
            'transaction_date'   => '2026-01-15',
            'registered_by'      => $this->operator->id,
        ];
    }

    private function collectionData(): array
    {
        return [
            'type'               => 'collection',
            'bank'               => 'BHD',
            'transaction_number' => 'TXN-002',
            'transaction_date'   => '2026-01-30',
            'registered_by'      => $this->operator->id,
        ];
    }

    // ── Disbursement tests ─────────────────────────────────────────────────

    public function test_disbursement_auto_confirms_for_operator(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createSolicitedFinancing();

        $txn = $this->service->create($this->disbursementData(), [$financing->id]);

        $this->assertEquals('confirmed', $txn->fresh()->status);
        $this->assertEquals($this->operator->id, $txn->fresh()->confirmed_by);
    }

    public function test_disbursement_auto_confirms_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin);
        $financing = $this->createSolicitedFinancing();

        $data = $this->disbursementData();
        $data['registered_by'] = $this->superAdmin->id;
        $txn = $this->service->create($data, [$financing->id]);

        $this->assertEquals('confirmed', $txn->fresh()->status);
    }

    public function test_disbursement_updates_financing_status_to_disbursed(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createSolicitedFinancing();

        $this->service->create($this->disbursementData(), [$financing->id]);

        $this->assertEquals('disbursed', $financing->fresh()->status);
        $this->assertNotNull($financing->fresh()->disbursed_at);
    }

    public function test_disbursement_amount_equals_sum_of_transfer_amounts(): void
    {
        $this->actingAs($this->operator);
        $f1 = $this->createSolicitedFinancing(100000.00); // transfer = 95000
        $f2 = $this->createSolicitedFinancing(50000.00);  // transfer = 47500

        $txn = $this->service->create($this->disbursementData(), [$f1->id, $f2->id]);

        $this->assertEquals(142500.00, (float) $txn->amount);
    }

    public function test_disbursement_sets_issue_period(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createSolicitedFinancing();

        $this->service->create($this->disbursementData(), [$financing->id]);

        $this->assertEquals(now()->format('Y-m'), $financing->fresh()->issue_period);
    }

    // ── Collection tests ───────────────────────────────────────────────────

    public function test_collection_auto_confirms_for_operator(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing();

        $txn = $this->service->create($this->collectionData(), [$financing->id]);

        $this->assertEquals('confirmed', $txn->fresh()->status);
    }

    public function test_collection_stays_pending_for_company_user(): void
    {
        $this->actingAs($this->companyUser);
        $financing = $this->createDisbursedFinancing();

        $data = $this->collectionData();
        $data['registered_by'] = $this->companyUser->id;
        $txn = $this->service->create($data, [$financing->id]);

        $this->assertEquals('pending', $txn->fresh()->status);
    }

    public function test_collection_updates_financing_status_to_collected(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing();

        $this->service->create($this->collectionData(), [$financing->id]);

        $this->assertEquals('collected', $financing->fresh()->status);
    }

    public function test_collection_amount_equals_sum_of_financing_amounts(): void
    {
        $this->actingAs($this->operator);
        $f1 = $this->createDisbursedFinancing(100000.00);
        $f2 = $this->createDisbursedFinancing(50000.00);

        $txn = $this->service->create($this->collectionData(), [$f1->id, $f2->id]);

        $this->assertEquals(150000.00, (float) $txn->amount);
    }

    public function test_collection_sets_collection_period(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing();

        $data = $this->collectionData();
        $data['transaction_date'] = '2026-03-15';

        $this->service->create($data, [$financing->id]);

        $this->assertEquals('2026-03', $financing->fresh()->collection_period);
    }

    // ── Confirm tests ──────────────────────────────────────────────────────

    public function test_confirm_changes_status_to_confirmed(): void
    {
        // Create as company_user so it stays pending
        $this->actingAs($this->companyUser);
        $financing = $this->createDisbursedFinancing();
        $data = $this->collectionData();
        $data['registered_by'] = $this->companyUser->id;
        $txn = $this->service->create($data, [$financing->id]);

        $this->assertEquals('pending', $txn->fresh()->status);

        // Confirm as operator
        $this->actingAs($this->operator);
        $confirmed = $this->service->confirm($txn->fresh());

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertEquals($this->operator->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->confirmed_at);
    }

    public function test_confirm_throws_if_already_confirmed(): void
    {
        // Create as company_user so it stays pending, then confirm
        $this->actingAs($this->companyUser);
        $financing = $this->createDisbursedFinancing();
        $data = $this->collectionData();
        $data['registered_by'] = $this->companyUser->id;
        $txn = $this->service->create($data, [$financing->id]);

        $this->actingAs($this->operator);
        $this->service->confirm($txn->fresh());

        $this->expectException(\Exception::class);
        $this->service->confirm($txn->fresh());
    }

    // ── calculateAmount tests ──────────────────────────────────────────────

    public function test_calculate_amount_sums_transfer_amounts_for_disbursement(): void
    {
        $f1 = $this->createSolicitedFinancing(100000.00);
        $f2 = $this->createSolicitedFinancing(50000.00);

        $amount = $this->service->calculateAmount('disbursement', [$f1->id, $f2->id]);

        $this->assertEquals(142500.00, $amount);
    }

    public function test_calculate_amount_returns_zero_for_empty_array(): void
    {
        $amount = $this->service->calculateAmount('disbursement', []);
        $this->assertEquals(0.0, $amount);
    }

    // ── Partial collection (abono) tests ───────────────────────────────────

    public function test_partial_collection_sets_status_to_partially_collected(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data = $this->collectionData();
        $data['amount'] = 30000.00;
        $this->service->create($data, [$financing->id]);

        $this->assertEquals('partially_collected', $financing->fresh()->status);
    }

    public function test_partial_collection_updates_collected_amount(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data = $this->collectionData();
        $data['amount'] = 30000.00;
        $this->service->create($data, [$financing->id]);

        $this->assertEquals(30000.00, (float) $financing->fresh()->collected_amount);
    }

    public function test_partial_collection_does_not_set_collection_period(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data = $this->collectionData();
        $data['amount'] = 30000.00;
        $this->service->create($data, [$financing->id]);

        $this->assertNull($financing->fresh()->collection_period);
        $this->assertNull($financing->fresh()->collected_at);
    }

    public function test_multiple_partial_collections_accumulate_collected_amount(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data1 = $this->collectionData();
        $data1['amount'] = 30000.00;
        $data1['transaction_number'] = 'TXN-P1';
        $this->service->create($data1, [$financing->id]);

        $data2 = $this->collectionData();
        $data2['amount'] = 25000.00;
        $data2['transaction_number'] = 'TXN-P2';
        $this->service->create($data2, [$financing->id]);

        $this->assertEquals(55000.00, (float) $financing->fresh()->collected_amount);
        $this->assertEquals('partially_collected', $financing->fresh()->status);
    }

    public function test_final_partial_collection_sets_status_to_collected(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        // First partial payment
        $data1 = $this->collectionData();
        $data1['amount'] = 60000.00;
        $data1['transaction_number'] = 'TXN-P1';
        $this->service->create($data1, [$financing->id]);

        // Final payment completes the total
        $data2 = $this->collectionData();
        $data2['amount'] = 40000.00;
        $data2['transaction_number'] = 'TXN-P2';
        $data2['transaction_date'] = '2026-02-15';
        $this->service->create($data2, [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('collected', $f->status);
        $this->assertEquals(100000.00, (float) $f->collected_amount);
    }

    public function test_final_partial_collection_sets_collection_period(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data1 = $this->collectionData();
        $data1['amount'] = 70000.00;
        $data1['transaction_number'] = 'TXN-P1';
        $data1['transaction_date'] = '2026-01-15';
        $this->service->create($data1, [$financing->id]);

        $data2 = $this->collectionData();
        $data2['amount'] = 30000.00;
        $data2['transaction_number'] = 'TXN-P2';
        $data2['transaction_date'] = '2026-02-20';
        $this->service->create($data2, [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('2026-02', $f->collection_period);
        $this->assertEquals('2026-02-20', $f->collected_at->format('Y-m-d'));
    }

    public function test_calculate_remaining_balance_returns_correct_value(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data = $this->collectionData();
        $data['amount'] = 30000.00;
        $this->service->create($data, [$financing->id]);

        $remaining = $this->service->calculateRemainingBalance([$financing->id]);
        $this->assertEquals(70000.00, $remaining);
    }

    public function test_full_collection_sets_collected_amount_to_total(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        // Full collection (no amount specified — uses financing amount)
        $this->service->create($this->collectionData(), [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('collected', $f->status);
        $this->assertEquals(100000.00, (float) $f->collected_amount);
    }

    // ── Expense tests ───────────────────────────────────────────────────

    private function expenseData(float $amount = 5000.00): array
    {
        return [
            'type'               => 'expense',
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'EXP-' . uniqid(),
            'transaction_date'   => '2026-01-15',
            'notes'              => 'Gasto de prueba',
            'registered_by'      => $this->superAdmin->id,
        ];
    }

    private function memberDisbursementData(int $fundMemberId, float $amount = 5000.00): array
    {
        return [
            'fund_member_id'     => $fundMemberId,
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'MD-' . uniqid(),
            'transaction_date'   => '2026-01-15',
        ];
    }

    public function test_create_expense_auto_confirms_and_debits_fund_account(): void
    {
        $this->actingAs($this->superAdmin);

        // Credit fund first so debit doesn't go negative
        FundAccount::first()->update(['balance' => 10000]);

        $txn = $this->service->createExpense($this->expenseData(5000.00));

        $this->assertEquals('confirmed', $txn->fresh()->status);
        $this->assertEquals($this->superAdmin->id, $txn->fresh()->confirmed_by);
        $this->assertNotNull($txn->fresh()->confirmed_at);
        $this->assertEquals(5000.00, (float) FundAccount::first()->balance);
    }

    public function test_disbursement_auto_generates_tax_expense(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createSolicitedFinancing(100000.00);

        $txn = $this->service->create($this->disbursementData(), [$financing->id]);

        // transfer_amount = 95000, tax_pct = 0.15 → tax = 95000 × 0.0015 = 142.50
        $taxExpense = Transaction::where('type', 'expense')
            ->where('transaction_number', 'like', 'TX%')
            ->latest('id')
            ->first();

        $this->assertNotNull($taxExpense);
        $this->assertEquals(142.50, (float) $taxExpense->amount);
        $this->assertEquals('confirmed', $taxExpense->status);
    }

    public function test_tax_expense_links_to_dgii_supplier(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createSolicitedFinancing(100000.00);

        $this->service->create($this->disbursementData(), [$financing->id]);

        $taxExpense = Transaction::where('type', 'expense')
            ->where('transaction_number', 'like', 'TX%')
            ->latest('id')
            ->first();

        $this->assertNotNull($taxExpense->supplier_id);
        $this->assertEquals('DGII', $taxExpense->supplier->name);
    }

    // ── Member disbursement tests ───────────────────────────────────────

    public function test_create_member_disbursement_succeeds_with_sufficient_balance(): void
    {
        $this->actingAs($this->superAdmin);

        $member = FundMember::factory()->create(['type' => 'capital', 'contribution' => 100000]);

        // Simulate earning_distribution to give the member a balance
        Transaction::create([
            'type'               => 'earning_distribution',
            'status'             => 'confirmed',
            'amount'             => 10000.00,
            'transaction_number' => 'DIST-TEST-' . $member->id,
            'transaction_date'   => '2026-01-15',
            'fund_member_id'     => $member->id,
            'registered_by'      => $this->superAdmin->id,
            'confirmed_by'       => $this->superAdmin->id,
            'confirmed_at'       => now(),
        ]);

        $txn = $this->service->createMemberDisbursement(
            $this->memberDisbursementData($member->id, 5000.00)
        );

        $this->assertEquals('member_disbursement', $txn->type);
        $this->assertEquals('confirmed', $txn->status);
        $this->assertEquals(5000.00, (float) $txn->amount);
        $this->assertEquals($member->id, $txn->fund_member_id);
    }

    public function test_create_member_disbursement_throws_when_balance_insufficient(): void
    {
        $this->actingAs($this->superAdmin);

        $member = FundMember::factory()->create(['type' => 'capital', 'contribution' => 100000]);

        // No earning distributions — balance is 0
        $this->expectException(\Exception::class);
        $this->service->createMemberDisbursement(
            $this->memberDisbursementData($member->id, 5000.00)
        );
    }

    public function test_member_disbursement_generates_tax_expense(): void
    {
        $this->actingAs($this->superAdmin);

        $member = FundMember::factory()->create(['type' => 'capital', 'contribution' => 100000]);

        // Give member a balance
        Transaction::create([
            'type'               => 'earning_distribution',
            'status'             => 'confirmed',
            'amount'             => 10000.00,
            'transaction_number' => 'DIST-TEST-' . $member->id,
            'transaction_date'   => '2026-01-15',
            'fund_member_id'     => $member->id,
            'registered_by'      => $this->superAdmin->id,
            'confirmed_by'       => $this->superAdmin->id,
            'confirmed_at'       => now(),
        ]);

        $expenseCountBefore = Transaction::where('type', 'expense')->count();

        $this->service->createMemberDisbursement(
            $this->memberDisbursementData($member->id, 5000.00)
        );

        $expenseCountAfter = Transaction::where('type', 'expense')->count();
        $this->assertGreaterThan($expenseCountBefore, $expenseCountAfter);

        // Tax = 5000 × 0.0015 = 7.50
        $taxExpense = Transaction::where('type', 'expense')
            ->latest('id')
            ->first();

        $this->assertEquals(7.50, (float) $taxExpense->amount);
    }

    // ── Late fee tests ───────────────────────────────────────────────────

    private function createOverdueFinancing(float $amount = 100000.00, string $dueDate = '2026-01-01'): Financing
    {
        return Financing::factory()->withAmount($amount)->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->operator->id,
            'due_date'      => $dueDate,
        ]);
    }

    public function test_collection_without_late_fee_when_not_overdue(): void
    {
        $this->actingAs($this->operator);
        $financing = $this->createDisbursedFinancing(100000.00);

        $data = $this->collectionData();
        $data['transaction_date'] = $financing->due_date->subDay()->format('Y-m-d');
        $this->service->create($data, [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals(100000.00, (float) $f->collected_amount);
        $this->assertEquals(0.00, (float) $f->late_fee_amount);
        $this->assertEquals(0.00, (float) $f->late_fee_pending);
    }

    public function test_full_collection_with_late_fee_distributes_proportionally(): void
    {
        $this->actingAs($this->operator);
        // Due Jan 1, collect Feb 15 → 45 days → floor(45/30) = 1 tier → 5% mora
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        // Mora = 100,000 × 5% × 1 = 5,000. Total owed = 105,000
        // Pay the full 105,000
        $data = $this->collectionData();
        $data['amount'] = 105000.00;
        $data['transaction_date'] = '2026-02-15';
        $this->service->create($data, [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('collected', $f->status);
        $this->assertEquals(100000.00, (float) $f->collected_amount);
        $this->assertEquals(5000.00, (float) $f->late_fee_amount);
        $this->assertEquals(0.00, (float) $f->late_fee_pending);
    }

    public function test_partial_collection_with_late_fee_distributes_proportionally(): void
    {
        $this->actingAs($this->operator);
        // Due Jan 1, collect Feb 15 → 45 days → floor(45/30) = 1 tier → 5% mora
        $financing = $this->createOverdueFinancing(100000.00, '2026-01-01');

        // Mora = 5,000. Total owed = 105,000. Pay 50,000
        // Capital: 50,000 × (100,000 / 105,000) = 47,619.05
        // Late fee: 50,000 × (5,000 / 105,000) = 2,380.95
        $data = $this->collectionData();
        $data['amount'] = 50000.00;
        $data['transaction_date'] = '2026-02-15';
        $this->service->create($data, [$financing->id]);

        $f = $financing->fresh();
        $this->assertEquals('partially_collected', $f->status);
        $this->assertEquals(47619.05, (float) $f->collected_amount);
        $this->assertEquals(2380.95, (float) $f->late_fee_amount);
    }
}
