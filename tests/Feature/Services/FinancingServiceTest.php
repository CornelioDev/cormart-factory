<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\User;
use App\Services\FinancingService;
use Carbon\Carbon;
use Tests\ServiceTestCase;

class FinancingServiceTest extends ServiceTestCase
{
    private FinancingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParameters();
        $this->service = new FinancingService();
    }

    // ── Commission by term tiers (5% per 30-day block) ──────────────────

    public function test_commission_is_5_percent_for_15_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 15);
        $this->assertEquals(5000.00, $commission);
    }

    public function test_commission_is_5_percent_for_30_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 30);
        $this->assertEquals(5000.00, $commission);
    }

    public function test_commission_is_10_percent_for_31_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 31);
        $this->assertEquals(10000.00, $commission);
    }

    public function test_commission_is_10_percent_for_60_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 60);
        $this->assertEquals(10000.00, $commission);
    }

    public function test_commission_is_15_percent_for_61_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 61);
        $this->assertEquals(15000.00, $commission);
    }

    public function test_commission_is_15_percent_for_90_days(): void
    {
        $commission = $this->service->calculateCommission(100000.00, 90);
        $this->assertEquals(15000.00, $commission);
    }

    public function test_commission_rounds_to_2_decimal_places(): void
    {
        $commission = $this->service->calculateCommission(33333.33, 15);
        $this->assertEquals(1666.67, $commission);
    }

    public function test_commission_rounds_correctly_for_multi_tier(): void
    {
        $commission = $this->service->calculateCommission(33333.33, 45);
        $this->assertEquals(3333.33, $commission);
    }

    // ── Transfer amount ─────────────────────────────────────────────────

    public function test_transfer_amount_equals_amount_minus_commission(): void
    {
        $transfer = $this->service->calculateTransferAmount(100000.00, 5000.00);
        $this->assertEquals(95000.00, $transfer);
    }

    public function test_transfer_amount_is_zero_when_commission_equals_amount(): void
    {
        $transfer = $this->service->calculateTransferAmount(5000.00, 5000.00);
        $this->assertEquals(0.00, $transfer);
    }

    // ── Due date ────────────────────────────────────────────────────────

    public function test_due_date_adds_correct_number_of_days(): void
    {
        $date    = Carbon::parse('2026-01-01');
        $dueDate = $this->service->calculateDueDate($date, 15);

        $this->assertEquals('2026-01-16', $dueDate->format('Y-m-d'));
    }

    public function test_due_date_from_month_end_does_not_overflow(): void
    {
        $date    = Carbon::parse('2026-01-31');
        $dueDate = $this->service->calculateDueDate($date, 15);

        $this->assertEquals('2026-02-15', $dueDate->format('Y-m-d'));
        // Original date should not be mutated
        $this->assertEquals('2026-01-31', $date->format('Y-m-d'));
    }

    // ── Late fee ────────────────────────────────────────────────────────

    private function createDisbursedFinancing(array $overrides = []): Financing
    {
        $company = Company::factory()->create();

        return Financing::factory()->disbursed()->create(array_merge([
            'company_id'    => $company->id,
            'client_id'     => Client::factory()->for($company)->create()->id,
            'registered_by' => User::factory()->create()->id,
            'amount'        => 100000.00,
            'due_date'      => Carbon::parse('2026-01-01'),
        ], $overrides));
    }

    public function test_late_fee_is_zero_when_not_overdue(): void
    {
        $financing = $this->createDisbursedFinancing(['due_date' => Carbon::parse('2026-02-01')]);
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-01-15'));

        $this->assertEquals(0.00, $fee);
    }

    public function test_late_fee_is_zero_on_due_date(): void
    {
        $financing = $this->createDisbursedFinancing(['due_date' => Carbon::parse('2026-01-01')]);
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-01-01'));

        $this->assertEquals(0.00, $fee);
    }

    public function test_late_fee_is_zero_when_under_30_days_overdue(): void
    {
        $financing = $this->createDisbursedFinancing();
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-01-16'));

        // 15 days overdue → floor(15/30) = 0 tiers → no mora
        $this->assertEquals(0.00, $fee);
    }

    public function test_late_fee_one_tier_30_days_overdue(): void
    {
        $financing = $this->createDisbursedFinancing();
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-01-31'));

        // 30 days → floor(30/30) = 1 tier → 100,000 × 5% × 1 = 5,000
        $this->assertEquals(5000.00, $fee);
    }

    public function test_late_fee_one_tier_45_days_overdue(): void
    {
        $financing = $this->createDisbursedFinancing();
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-02-15'));

        // 45 days → floor(45/30) = 1 tier → 100,000 × 5% × 1 = 5,000
        $this->assertEquals(5000.00, $fee);
    }

    public function test_late_fee_on_partial_balance(): void
    {
        $financing = $this->createDisbursedFinancing([
            'collected_amount' => 40000.00,
        ]);
        $fee = $this->service->calculateLateFee($financing, Carbon::parse('2026-01-16'));

        // Balance = 60,000, 15 days → floor(15/30) = 0 tiers → no mora
        $this->assertEquals(0.00, $fee);
    }
}
