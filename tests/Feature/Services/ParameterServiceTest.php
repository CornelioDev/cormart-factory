<?php

namespace Tests\Feature\Services;

use App\Models\ParameterHistory;
use App\Models\User;
use App\Services\ParameterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\ServiceTestCase;

class ParameterServiceTest extends ServiceTestCase
{
    private ParameterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParameters();
        $this->service = new ParameterService();
    }

    public function test_get_returns_float_for_existing_key(): void
    {
        $value = $this->service->get('commission_pct');

        $this->assertIsFloat($value);
        $this->assertEquals(5.0, $value);
    }

    public function test_get_throws_for_unknown_key(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service->get('nonexistent_key');
    }

    public function test_get_all_returns_all_parameters(): void
    {
        $all = $this->service->getAll();

        $this->assertCount(7, $all);
        $this->assertArrayHasKey('commission_pct', $all);
        $this->assertArrayHasKey('fixed_return_pct', $all);
        $this->assertArrayHasKey('reserve_pct', $all);
        $this->assertArrayHasKey('in_kind_pct', $all);
        $this->assertArrayHasKey('default_term_days', $all);
        $this->assertArrayHasKey('tax_pct', $all);
        $this->assertArrayHasKey('late_fee_pct', $all);

        foreach ($all as $value) {
            $this->assertIsFloat($value);
        }
    }

    public function test_update_changes_parameter_value(): void
    {
        $user = User::factory()->create();

        $this->service->update('commission_pct', 7.5, '2026-01', $user->id);

        $this->assertEquals(7.5, $this->service->get('commission_pct'));
    }

    public function test_update_creates_history_record_with_previous_value(): void
    {
        $user = User::factory()->create();

        $this->service->update('commission_pct', 7.5, '2026-01', $user->id);

        $history = ParameterHistory::where('key', 'commission_pct')->first();
        $this->assertNotNull($history);
        $this->assertEquals(5.0, (float) $history->previous_value);
        $this->assertEquals(7.5, (float) $history->new_value);
    }

    public function test_update_history_records_correct_period_and_user(): void
    {
        $user = User::factory()->create();

        $this->service->update('reserve_pct', 25.0, '2026-03', $user->id);

        $history = ParameterHistory::where('key', 'reserve_pct')->first();
        $this->assertEquals('2026-03', $history->period);
        $this->assertEquals($user->id, $history->changed_by);
    }
}
