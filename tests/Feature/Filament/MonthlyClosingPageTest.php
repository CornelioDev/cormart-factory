<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\MonthlyClosingPage;
use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\FundMember;
use App\Models\MonthlyClosing;
use App\Models\User;
use Livewire\Livewire;
use Tests\ServiceTestCase;

class MonthlyClosingPageTest extends ServiceTestCase
{
    private User $admin;
    private Company $company;
    private Client $client;
    private string $period = '2026-08';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();

        $this->admin = User::factory()->create();
        $this->admin->assignRole($this->createRole('super_admin'));

        $this->company = Company::factory()->create();
        $this->client  = Client::factory()->for($this->company)->create();

        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        FundMember::factory()->create([
            'name' => 'Capital A', 'type' => 'capital',
            'contribution' => 100000.00, 'fund_percentage' => 60.0000,
        ]);
        FundMember::factory()->create([
            'name' => 'Capital B', 'type' => 'capital',
            'contribution' => 50000.00, 'fund_percentage' => 40.0000,
        ]);
        FundMember::factory()->inKind()->create(['name' => 'Aportante Natura']);

        $this->createFinancings(3, 5000.00);

        $this->actingAs($this->admin);
    }

    private function createFinancings(int $count, float $commission): void
    {
        Financing::factory()->count($count)->disbursed()->create([
            'company_id'    => $this->company->id,
            'client_id'     => $this->client->id,
            'registered_by' => $this->admin->id,
            'commission'    => $commission,
            'issue_period'  => $this->period,
        ]);
    }

    private function calculated(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(MonthlyClosingPage::class)
            ->set('selectedPeriod', $this->period)
            ->call('calculate');
    }

    public function test_preview_no_viaja_en_el_snapshot_de_livewire(): void
    {
        $data = $this->calculated()->snapshot['data'];

        $this->assertArrayNotHasKey('preview', $data, 'preview no debe viajar en el snapshot');
        $this->assertArrayHasKey('selectedPeriod', $data);
        $this->assertArrayHasKey('showPreview', $data);

        $sizeBefore = strlen(json_encode($data));

        // Más miembros y más financiamientos = distribución más grande, pero el
        // snapshot debe pesar exactamente lo mismo. Si alguien vuelve a meter la
        // distribución en una propiedad pública, esta aserción falla.
        FundMember::factory()->count(5)->create([
            'type' => 'capital', 'contribution' => 25000.00, 'fund_percentage' => 1.0000,
        ]);
        $this->createFinancings(10, 1234.56);

        $this->assertSame(
            $sizeBefore,
            strlen(json_encode($this->calculated()->snapshot['data'])),
            'el snapshot no debe crecer con el volumen de datos'
        );
    }

    public function test_calculate_muestra_la_distribucion(): void
    {
        $this->calculated()
            ->assertSet('showPreview', true)
            ->assertSet('alreadyClosed', false)
            ->assertSee('Cascada de Distribución')
            ->assertSee('Desglose por Miembro')
            ->assertSee('Gastos del período');
    }

    public function test_execute_closing_persiste_el_cierre(): void
    {
        $this->calculated()
            ->call('executeClosing')
            ->assertSet('alreadyClosed', true)
            ->assertSet('showPreview', false);

        $closing = MonthlyClosing::where('period', $this->period)->first();

        $this->assertNotNull($closing, 'el cierre debe existir');
        $this->assertEquals($this->admin->id, $closing->executed_by);
        $this->assertEquals(0.0, (float) $closing->verification_diff);
        $this->assertCount(3, $closing->distributions);
    }

    public function test_no_permite_cerrar_dos_veces_el_mismo_periodo(): void
    {
        $this->calculated()->call('executeClosing');

        $this->calculated()
            ->assertSet('alreadyClosed', true)
            ->call('executeClosing');

        $this->assertEquals(1, MonthlyClosing::where('period', $this->period)->count());
    }
}
