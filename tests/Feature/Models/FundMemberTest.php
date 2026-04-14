<?php

namespace Tests\Feature\Models;

use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use Tests\ServiceTestCase;

class FundMemberTest extends ServiceTestCase
{
    private FundMember $member;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create();
        $this->member = FundMember::factory()->create([
            'type'         => 'capital',
            'contribution' => 100000,
        ]);

        $this->actingAs($this->user);
    }

    private function createEarningDistribution(float $amount, string $status = 'confirmed'): Transaction
    {
        return Transaction::create([
            'type'               => 'earning_distribution',
            'status'             => $status,
            'amount'             => $amount,
            'transaction_number' => 'DIST-' . uniqid(),
            'transaction_date'   => '2026-01-15',
            'fund_member_id'     => $this->member->id,
            'registered_by'      => $this->user->id,
            'confirmed_by'       => $status === 'confirmed' ? $this->user->id : null,
            'confirmed_at'       => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function createMemberDisbursement(float $amount): Transaction
    {
        return Transaction::create([
            'type'               => 'member_disbursement',
            'status'             => 'confirmed',
            'amount'             => $amount,
            'bank'               => 'BanReservas',
            'transaction_number' => 'MD-' . uniqid(),
            'transaction_date'   => '2026-01-20',
            'fund_member_id'     => $this->member->id,
            'registered_by'      => $this->user->id,
            'confirmed_by'       => $this->user->id,
            'confirmed_at'       => now(),
        ]);
    }

    public function test_earnings_balance_returns_earned_minus_disbursed(): void
    {
        $this->createEarningDistribution(5000.00);
        $this->createEarningDistribution(3000.00);
        $this->createMemberDisbursement(2000.00);

        $this->assertEquals(6000.00, $this->member->earningsBalance());
    }

    public function test_total_earned_only_sums_confirmed_distributions(): void
    {
        $this->createEarningDistribution(5000.00, 'confirmed');
        $this->createEarningDistribution(3000.00, 'confirmed');
        $this->createEarningDistribution(1000.00, 'pending');

        $this->assertEquals(8000.00, $this->member->totalEarned());
    }

    public function test_earnings_disbursements_includes_member_disbursements_and_capitalizations(): void
    {
        $this->createEarningDistribution(5000.00);
        $this->createMemberDisbursement(1000.00);
        $this->createMemberDisbursement(500.00);

        $this->assertEquals(2, $this->member->earningsDisbursements()->count());
    }

    public function test_earnings_to_capital_counts_in_total_disbursed(): void
    {
        $this->createEarningDistribution(10000.00);

        Transaction::create([
            'type'             => 'earnings_to_capital',
            'status'           => 'confirmed',
            'amount'           => 3000.00,
            'transaction_date' => '2026-01-20',
            'fund_member_id'   => $this->member->id,
            'registered_by'    => $this->user->id,
            'confirmed_by'     => $this->user->id,
            'confirmed_at'     => now(),
        ]);

        $this->assertEquals(3000.00, $this->member->totalDisbursed());
        $this->assertEquals(7000.00, $this->member->earningsBalance());
    }

    public function test_earnings_to_capital_appears_in_earnings_disbursements_relation(): void
    {
        $this->createEarningDistribution(10000.00);
        $this->createMemberDisbursement(2000.00);

        Transaction::create([
            'type'             => 'earnings_to_capital',
            'status'           => 'confirmed',
            'amount'           => 3000.00,
            'transaction_date' => '2026-01-20',
            'fund_member_id'   => $this->member->id,
            'registered_by'    => $this->user->id,
            'confirmed_by'     => $this->user->id,
            'confirmed_at'     => now(),
        ]);

        $this->assertEquals(2, $this->member->earningsDisbursements()->count());
        $this->assertEquals(5000.00, $this->member->earningsBalance());
    }

    public function test_balance_is_zero_with_no_transactions(): void
    {
        $this->assertEquals(0.00, $this->member->earningsBalance());
        $this->assertEquals(0.00, $this->member->totalEarned());
        $this->assertEquals(0.00, $this->member->totalDisbursed());
    }
}
