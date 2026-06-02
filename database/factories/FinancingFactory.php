<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Financing> */
class FinancingFactory extends Factory
{
    protected $model = Financing::class;

    public function definition(): array
    {
        return [
            'company_id'      => Company::factory(),
            'client_id'       => Client::factory(),
            'amount'          => 100000.00,
            'commission'      => 5000.00,
            'transfer_amount' => 95000.00,
            'term_days'       => 15,
            'request_date'    => now(),
            'due_date'        => now()->addDays(15),
            'status'          => 'solicited',
            'registered_by'   => User::factory(),
        ];
    }

    public function disbursed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'           => 'disbursed',
            'disbursed_at'     => now(),
            'issue_period'     => now()->format('Y-m'),
            'disbursed_amount' => $attrs['transfer_amount'] ?? 95000.00,
        ]);
    }

    public function collected(string $period = null): static
    {
        $period = $period ?? now()->format('Y-m');

        return $this->state(fn (array $attrs) => [
            'status'            => 'collected',
            'disbursed_at'      => now()->subDays(15),
            'issue_period'      => now()->subDays(15)->format('Y-m'),
            'collected_at'      => now(),
            'collection_period' => $period,
            'disbursed_amount'  => $attrs['transfer_amount'] ?? 95000.00,
            'collected_amount'  => $attrs['amount'] ?? 100000.00,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'              => 'cancelled',
            'cancellation_reason' => 'Test cancellation',
        ]);
    }

    public function withAmount(float $amount, float $commissionPct = 5.0): static
    {
        $commission = round($amount * ($commissionPct / 100), 2);
        $transfer   = round($amount - $commission, 2);

        return $this->state(fn () => [
            'amount'          => $amount,
            'commission'      => $commission,
            'transfer_amount' => $transfer,
        ]);
    }
}
