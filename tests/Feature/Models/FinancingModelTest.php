<?php

namespace Tests\Feature\Models;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\User;
use Tests\ServiceTestCase;

class FinancingModelTest extends ServiceTestCase
{
    private Company $company;
    private Client $client;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();
        $this->user    = User::factory()->create();
    }

    public function test_code_is_auto_generated_on_create(): void
    {
        $financing = Financing::factory()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
        ]);

        $this->assertNotNull($financing->fresh()->code);
    }

    public function test_code_format_is_fn_padded_to_6_digits(): void
    {
        $financing = Financing::factory()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
        ]);

        $expected = 'FN' . str_pad($financing->id, 6, '0', STR_PAD_LEFT);
        $this->assertEquals($expected, $financing->fresh()->code);
    }

    public function test_second_financing_gets_sequential_code(): void
    {
        $f1 = Financing::factory()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
        ]);

        $f2 = Financing::factory()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->user->id,
        ]);

        $this->assertNotEquals($f1->fresh()->code, $f2->fresh()->code);
        $this->assertEquals($f1->id + 1, $f2->id);
    }

    public function test_remaining_balance_returns_amount_minus_collected(): void
    {
        $financing = Financing::factory()->create([
            'company_id'       => $this->company->id,
            'client_id'        => $this->client->id,
            'registered_by'    => $this->user->id,
            'amount'           => 100000.00,
            'collected_amount' => 30000.00,
        ]);

        $this->assertEquals(70000.00, $financing->remainingBalance());
    }

    public function test_remaining_balance_returns_zero_when_fully_collected(): void
    {
        $financing = Financing::factory()->create([
            'company_id'       => $this->company->id,
            'client_id'        => $this->client->id,
            'registered_by'    => $this->user->id,
            'amount'           => 100000.00,
            'collected_amount' => 100000.00,
        ]);

        $this->assertEquals(0.00, $financing->remainingBalance());
    }
}
