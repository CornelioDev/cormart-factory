<?php

namespace Tests\Feature;

use App\Filament\Pages\MemberAccountPage;
use App\Filament\Pages\MonthlyClosingPage;
use App\Filament\Pages\ParametrosPage;
use App\Filament\Resources\FinancingResource;
use App\Filament\Resources\TransactionResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Financing;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use Tests\ServiceTestCase;

class AuthorizationTest extends ServiceTestCase
{
    private Company $companyA;
    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);
    }

    private function createCompanyUser(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole($this->createRole('company_user'));

        return $user;
    }

    private function createUserWithRole(string $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->assignRole($this->createRole($role));

        return $user;
    }

    // ── Financing scoping ───────────────────────────────────────────────

    public function test_company_user_only_sees_own_company_financings(): void
    {
        $clientA = Client::factory()->for($this->companyA)->create();
        $clientB = Client::factory()->for($this->companyB)->create();
        $admin   = $this->createUserWithRole('super_admin');

        Financing::factory()->disbursed()->create([
            'company_id'    => $this->companyA->id,
            'client_id'     => $clientA->id,
            'registered_by' => $admin->id,
        ]);
        Financing::factory()->disbursed()->create([
            'company_id'    => $this->companyB->id,
            'client_id'     => $clientB->id,
            'registered_by' => $admin->id,
        ]);

        $companyUser = $this->createCompanyUser($this->companyA);
        $this->actingAs($companyUser);

        $results = FinancingResource::getEloquentQuery()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->companyA->id, $results->first()->company_id);
    }

    // ── Transaction scoping ─────────────────────────────────────────────

    public function test_company_user_only_sees_collections_in_transactions(): void
    {
        $admin = $this->createUserWithRole('super_admin');

        // Collection for company A
        Transaction::create([
            'type'               => 'collection',
            'status'             => 'confirmed',
            'amount'             => 10000,
            'company_id'         => $this->companyA->id,
            'bank'               => 'BHD',
            'transaction_number' => 'COL-001',
            'transaction_date'   => '2026-01-15',
            'registered_by'      => $admin->id,
            'confirmed_by'       => $admin->id,
            'confirmed_at'       => now(),
        ]);

        // Expense (no company) — should be excluded for company_user
        Transaction::create([
            'type'               => 'expense',
            'status'             => 'confirmed',
            'amount'             => 500,
            'bank'               => 'BanReservas',
            'transaction_number' => 'EXP-001',
            'transaction_date'   => '2026-01-15',
            'registered_by'      => $admin->id,
            'confirmed_by'       => $admin->id,
            'confirmed_at'       => now(),
        ]);

        // Collection for company B — should be excluded
        Transaction::create([
            'type'               => 'collection',
            'status'             => 'confirmed',
            'amount'             => 8000,
            'company_id'         => $this->companyB->id,
            'bank'               => 'BHD',
            'transaction_number' => 'COL-002',
            'transaction_date'   => '2026-01-15',
            'registered_by'      => $admin->id,
            'confirmed_by'       => $admin->id,
            'confirmed_at'       => now(),
        ]);

        $companyUser = $this->createCompanyUser($this->companyA);
        $this->actingAs($companyUser);

        $results = TransactionResource::getEloquentQuery()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('collection', $results->first()->type);
        $this->assertEquals($this->companyA->id, $results->first()->company_id);
    }

    // ── Page access ─────────────────────────────────────────────────────

    public function test_member_can_access_member_account_page(): void
    {
        $member = FundMember::factory()->create(['type' => 'capital', 'contribution' => 100000]);

        $memberUser = $this->createUserWithRole('member', ['fund_member_id' => $member->id]);
        $this->actingAs($memberUser);
        $this->assertTrue(MemberAccountPage::canAccess());

        $companyUser = $this->createCompanyUser($this->companyA);
        $this->actingAs($companyUser);
        $this->assertFalse(MemberAccountPage::canAccess());
    }

    public function test_only_super_admin_can_access_closing_and_params_pages(): void
    {
        $operator = $this->createUserWithRole('operator');
        $this->actingAs($operator);
        $this->assertFalse(MonthlyClosingPage::canAccess());
        $this->assertFalse(ParametrosPage::canAccess());

        $admin = $this->createUserWithRole('super_admin');
        $this->actingAs($admin);
        $this->assertTrue(MonthlyClosingPage::canAccess());
        $this->assertTrue(ParametrosPage::canAccess());
    }
}
