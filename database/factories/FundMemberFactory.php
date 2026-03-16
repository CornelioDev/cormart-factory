<?php

namespace Database\Factories;

use App\Models\FundMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FundMember> */
class FundMemberFactory extends Factory
{
    protected $model = FundMember::class;

    public function definition(): array
    {
        return [
            'name'            => fake()->name(),
            'type'            => 'capital',
            'contribution'    => 100000.00,
            'fund_percentage' => 50.0000,
            'active'          => true,
            'joined_at'       => now(),
        ];
    }

    public function capital(): static
    {
        return $this->state(fn () => ['type' => 'capital']);
    }

    public function inKind(): static
    {
        return $this->state(fn () => [
            'type'            => 'in_kind',
            'contribution'    => 0.00,
            'fund_percentage' => 0.0000,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active'  => false,
            'left_at' => now(),
        ]);
    }
}
