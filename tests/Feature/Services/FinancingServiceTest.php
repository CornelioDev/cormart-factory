<?php

namespace Tests\Feature\Services;

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

    public function test_commission_is_5_percent_of_amount(): void
    {
        $commission = $this->service->calculateCommission(100000.00);
        $this->assertEquals(5000.00, $commission);
    }

    public function test_commission_rounds_to_2_decimal_places(): void
    {
        $commission = $this->service->calculateCommission(33333.33);
        $this->assertEquals(1666.67, $commission);
    }

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
}
