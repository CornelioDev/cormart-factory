<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\ClosingDistribution;
use App\Models\ClosingParametersSnapshot;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\User;
use App\Services\DistributionService;
use Tests\ServiceTestCase;

class DistributionServiceTest extends ServiceTestCase
{
    private DistributionService $service;
    private Company $company;
    private Client $client;
    private User $user;
    private string $period = '2026-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DistributionService();
        $this->seedParameters();

        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();
        $this->user    = User::factory()->create();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function createStandardMembers(): array
    {
        $memberA = FundMember::factory()->create([
            'name'            => 'Capital A',
            'type'            => 'capital',
            'contribution'    => 100000.00,
            'fund_percentage' => 60.0000,
        ]);

        $memberB = FundMember::factory()->create([
            'name'            => 'Capital B',
            'type'            => 'capital',
            'contribution'    => 50000.00,
            'fund_percentage' => 40.0000,
        ]);

        $inKind = FundMember::factory()->inKind()->create([
            'name' => 'In Kind Member',
        ]);

        return [$memberA, $memberB, $inKind];
    }

    private function createCollectedFinancings(int $count = 3, float $commission = 5000.00): void
    {
        Financing::factory()->count($count)->collected($this->period)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
            'commission'    => $commission,
        ]);
    }

    // ── calculate() math tests ─────────────────────────────────────────────

    /**
     * Standard fixture:
     *   total_commissions = 3 × 5000 = 15000
     *   total_fixed = (100000 + 50000) × 0.03 = 4500
     *   net_profit = 15000 - 4500 = 10500
     *   reserve = 10500 × 0.20 = 2100
     *   post_reserve = 10500 - 2100 = 8400
     *   in_kind = 8400 × 0.50 = 4200
     *   available = 8400 - 4200 = 4200
     *   diff = 15000 - (4500 + 2100 + 4200 + 4200) = 0
     */
    public function test_verification_diff_is_zero_with_standard_fixture(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        $this->assertEquals(0.0, (float) $result['verification_diff']);
        $this->assertEquals(15000.00, (float) $result['total_commissions']);
        $this->assertEquals(4500.00, (float) $result['total_fixed']);
        $this->assertEquals(10500.00, (float) $result['net_profit']);
        $this->assertEquals(2100.00, (float) $result['reserve']);
        $this->assertEquals(8400.00, (float) $result['post_reserve']);
        $this->assertEquals(4200.00, (float) $result['in_kind_payment']);
        $this->assertEquals(4200.00, (float) $result['available_for_capital']);
    }

    public function test_total_commissions_sums_only_collected_financings_in_period(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(2, 3000.00);

        $result = $this->service->calculate($this->period);

        $this->assertEquals(6000.00, (float) $result['total_commissions']);
        $this->assertEquals(2, $result['financings_count']);
    }

    public function test_financings_from_other_periods_are_excluded(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(2, 5000.00);

        // Financing in a different period
        Financing::factory()->collected('2026-02')->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
            'commission'    => 5000.00,
        ]);

        $result = $this->service->calculate($this->period);

        $this->assertEquals(10000.00, (float) $result['total_commissions']);
        $this->assertEquals(2, $result['financings_count']);
    }

    public function test_uncollected_financings_are_excluded_from_commissions(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(1, 5000.00);

        // Disbursed financing (not collected)
        Financing::factory()->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
            'commission'    => 5000.00,
        ]);

        $result = $this->service->calculate($this->period);

        $this->assertEquals(5000.00, (float) $result['total_commissions']);
    }

    public function test_fixed_return_is_subtracted_before_reserve(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        // net_profit = total_commissions - total_fixed
        $expectedNet = (float) $result['total_commissions'] - (float) $result['total_fixed'];
        $this->assertEquals($expectedNet, (float) $result['net_profit']);
    }

    public function test_reserve_applied_to_net_profit_not_total_commissions(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        // reserve = net_profit × 20%, NOT total_commissions × 20%
        $this->assertEquals(2100.00, (float) $result['reserve']); // 10500 × 0.20
        $this->assertNotEquals(3000.00, (float) $result['reserve']); // NOT 15000 × 0.20
    }

    public function test_in_kind_payment_applied_to_post_reserve(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        // in_kind = post_reserve × 50%
        $expectedInKind = round((float) $result['post_reserve'] * 0.50, 2);
        $this->assertEquals($expectedInKind, (float) $result['in_kind_payment']);
    }

    public function test_proportional_distribution_respects_fund_percentage(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        $capitalDists = collect($result['distributions'])->where('type', 'capital');
        $memberA = $capitalDists->firstWhere('name', 'Capital A');
        $memberB = $capitalDists->firstWhere('name', 'Capital B');

        // available = 4200, A gets 60%, B gets 40%
        $this->assertEquals(2520.00, $memberA['proportional_amount']); // 4200 × 0.60
        $this->assertEquals(1680.00, $memberB['proportional_amount']); // 4200 × 0.40
    }

    public function test_in_kind_member_has_zero_fixed_and_receives_in_kind_amount(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $result = $this->service->calculate($this->period);

        $inKindDist = collect($result['distributions'])->firstWhere('type', 'in_kind');

        $this->assertEquals(0, $inKindDist['fixed_amount']);
        $this->assertEquals(4200.00, $inKindDist['proportional_amount']);
        $this->assertEquals(4200.00, $inKindDist['total_amount']);
    }

    public function test_calculate_with_no_collected_financings_returns_zeros(): void
    {
        $this->createStandardMembers();

        $result = $this->service->calculate($this->period);

        $this->assertEquals(0.0, (float) $result['total_commissions']);
        $this->assertEquals(0, $result['financings_count']);
    }

    public function test_member_distributions_count_matches_active_members(): void
    {
        $this->createStandardMembers(); // 2 capital + 1 in_kind = 3
        FundMember::factory()->inactive()->create(); // Should be excluded
        $this->createCollectedFinancings(1);

        $result = $this->service->calculate($this->period);

        $this->assertCount(3, $result['distributions']);
    }

    // ── executeClosing() persistence tests ─────────────────────────────────

    public function test_execute_closing_creates_monthly_closing_record(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings();

        $closing = $this->service->executeClosing($this->period, $this->user->id);

        $this->assertInstanceOf(MonthlyClosing::class, $closing);
        $this->assertEquals($this->period, $closing->period);
        $this->assertEquals($this->user->id, $closing->executed_by);
        $this->assertNotNull($closing->closed_at);
    }

    public function test_execute_closing_creates_distribution_per_member(): void
    {
        $this->createStandardMembers(); // 3 members
        $this->createCollectedFinancings();

        $closing = $this->service->executeClosing($this->period, $this->user->id);

        $this->assertEquals(3, ClosingDistribution::where('monthly_closing_id', $closing->id)->count());
    }

    public function test_execute_closing_creates_parameters_snapshot(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings();

        $closing = $this->service->executeClosing($this->period, $this->user->id);

        $this->assertEquals(5, ClosingParametersSnapshot::where('monthly_closing_id', $closing->id)->count());
    }

    public function test_execute_closing_throws_if_period_already_closed(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings();

        $this->service->executeClosing($this->period, $this->user->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ya tiene un cierre ejecutado');
        $this->service->executeClosing($this->period, $this->user->id);
    }

    public function test_execute_closing_persists_zero_verification_diff(): void
    {
        $this->createStandardMembers();
        $this->createCollectedFinancings(3, 5000.00);

        $closing = $this->service->executeClosing($this->period, $this->user->id);

        $this->assertEquals(0.0, (float) $closing->verification_diff);
    }
}
