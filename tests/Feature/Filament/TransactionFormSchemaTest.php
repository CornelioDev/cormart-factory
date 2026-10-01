<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Models\CapitalAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyCredit;
use App\Models\Financing;
use App\Models\FundAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\ServiceTestCase;

/**
 * Renderiza el formulario de transacciones en los casos que tocan el saldo de
 * compañías. El esquema está lleno de closures condicionales (visibilidad,
 * etiquetas, montos) que solo fallan al renderizar, no al ejecutar los servicios.
 */
class TransactionFormSchemaTest extends ServiceTestCase
{
    private Company $company;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedParameters();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->company = Company::factory()->create();

        FundAccount::query()->delete();
        FundAccount::create(['balance' => 0]);
        CapitalAccount::query()->delete();
        CapitalAccount::create(['balance' => 500000]);

        $role = $this->createRole('operator');
        foreach (['view_any_transaction', 'view_transaction', 'create_transaction'] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]));
        }

        $this->operator = User::factory()->create();
        $this->operator->assignRole($role);

        $this->actingAs($this->operator);
    }

    private function creditTheCompany(float $amount): void
    {
        CompanyCredit::create([
            'company_id'      => $this->company->id,
            'type'            => 'accrual',
            'amount'          => $amount,
            'capital_amount'  => $amount,
            'late_fee_amount' => 0,
            'created_by'      => $this->operator->id,
        ]);
    }

    public function test_collection_form_renders_with_the_payment_method_field(): void
    {
        Livewire::test(CreateTransaction::class)
            ->assertFormFieldExists('payment_method')
            ->fillForm([
                'type'           => 'collection',
                'company_id'     => $this->company->id,
                'payment_method' => 'check',
            ])
            ->assertHasNoFormErrors();
    }

    public function test_disbursement_form_shows_the_credit_field_when_there_is_balance(): void
    {
        $this->creditTheCompany(10000.00);

        $client    = Client::factory()->for($this->company)->create();
        $financing = Financing::factory()->withAmount(20000.00)->create([
            'company_id'    => $this->company->id,
            'client_id'     => $client->id,
            'registered_by' => $this->operator->id,
        ]);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'          => 'disbursement',
                'company_id'    => $this->company->id,
                'financing_ids' => [$financing->id],
            ])
            ->assertFormFieldExists('credit_applied')
            ->fillForm(['credit_applied' => '10,000.00'])
            ->assertHasNoFormErrors();
    }

    public function test_disbursement_form_hides_the_credit_field_without_balance(): void
    {
        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'       => 'disbursement',
                'company_id' => $this->company->id,
            ])
            ->assertFormFieldIsHidden('credit_applied');
    }

    public function test_settlement_form_hides_the_financings_field(): void
    {
        $this->creditTheCompany(10000.00);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'type'       => 'settlement',
                'company_id' => $this->company->id,
            ])
            ->assertFormFieldIsHidden('financing_ids');
    }
}
