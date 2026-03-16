<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\User;
use App\Services\ClientService;
use Tests\ServiceTestCase;

class ClientServiceTest extends ServiceTestCase
{
    private ClientService $service;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClientService();
        $this->company = Company::factory()->create();
    }

    public function test_search_returns_clients_matching_name_fragment(): void
    {
        Client::factory()->for($this->company)->create(['name' => 'Juan Pérez']);
        Client::factory()->for($this->company)->create(['name' => 'María López']);

        $results = $this->service->search('Juan');

        $this->assertCount(1, $results);
        $this->assertEquals('Juan Pérez', $results->first()->name);
    }

    public function test_search_excludes_inactive_clients(): void
    {
        Client::factory()->for($this->company)->create(['name' => 'Juan Activo']);
        Client::factory()->for($this->company)->inactive()->create(['name' => 'Juan Inactivo']);

        $results = $this->service->search('Juan');

        $this->assertCount(1, $results);
        $this->assertEquals('Juan Activo', $results->first()->name);
    }

    public function test_search_scoped_to_company_when_company_id_provided(): void
    {
        $otherCompany = Company::factory()->create();

        Client::factory()->for($this->company)->create(['name' => 'Juan Aquí']);
        Client::factory()->for($otherCompany)->create(['name' => 'Juan Allá']);

        $results = $this->service->search('Juan', $this->company->id);

        $this->assertCount(1, $results);
        $this->assertEquals('Juan Aquí', $results->first()->name);
    }

    public function test_search_without_company_id_returns_all_active_clients(): void
    {
        $otherCompany = Company::factory()->create();

        Client::factory()->for($this->company)->create(['name' => 'Juan Uno']);
        Client::factory()->for($otherCompany)->create(['name' => 'Juan Dos']);

        $results = $this->service->search('Juan');

        $this->assertCount(2, $results);
    }

    public function test_create_quick_creates_active_client_linked_to_company(): void
    {
        $client = $this->service->createQuick('Nuevo Cliente', $this->company->id);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('Nuevo Cliente', $client->name);
        $this->assertEquals($this->company->id, $client->company_id);
        $this->assertTrue($client->active);
    }

    public function test_get_stats_returns_correct_financing_counts_per_status(): void
    {
        $client = Client::factory()->for($this->company)->create();
        $user   = User::factory()->create();

        Financing::factory()->count(2)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $user->id,
            'status'        => 'solicited',
        ]);

        Financing::factory()->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $user->id,
        ]);

        Financing::factory()->collected('2026-01')->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $user->id,
        ]);

        $stats = $this->service->getStats($client);

        $this->assertEquals(4, $stats['total_financings']);
        $this->assertEquals(2, $stats['solicited']);
        $this->assertEquals(1, $stats['disbursed']);
        $this->assertEquals(1, $stats['collected']);
    }

    public function test_get_stats_returns_correct_total_amounts(): void
    {
        $client = Client::factory()->for($this->company)->create();
        $user   = User::factory()->create();

        Financing::factory()->withAmount(50000.00)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $user->id,
        ]);

        Financing::factory()->withAmount(75000.00)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $user->id,
        ]);

        $stats = $this->service->getStats($client);

        $this->assertEquals(125000.00, (float) $stats['total_amount']);
    }

    public function test_get_stats_returns_zero_for_client_without_financings(): void
    {
        $client = Client::factory()->for($this->company)->create();

        $stats = $this->service->getStats($client);

        $this->assertEquals(0, $stats['total_financings']);
        $this->assertEquals(0, (float) $stats['total_amount']);
    }
}
