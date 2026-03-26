<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Asegurar que existan los permisos de widgets nuevos
        Permission::firstOrCreate(['name' => 'widget_OverdueFinancingsWidget', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'widget_SolicitedFinancingsWidget', 'guard_name' => 'web']);

        // OPERATOR
        $operator = Role::findByName('operator');
        $operator->syncPermissions([
            'view_any_company', 'view_company', 'create_company', 'update_company',
            'view_any_financing', 'view_financing', 'create_financing', 'update_financing',
            'view_any_transaction', 'view_transaction', 'create_transaction',
            'view_any_client', 'view_client', 'create_client', 'update_client',
            'widget_FinancingPipelineWidget',
            'widget_PendingTransactionsWidget',
            'widget_OverdueFinancingsWidget',
            'widget_SolicitedFinancingsWidget',
            'page_FinancialDashboardPage',
            'page_CuentasPorCobrarPage',
            'page_CuentasPorPagarPage',
        ]);

        // MEMBER — solo lectura + acceso a su propio perfil
        $member = Role::findByName('member');
        $member->syncPermissions([
            'view_any_financing', 'view_financing',
            'view_any_monthly::closing', 'view_monthly::closing',
            'view_fund::member',
            'widget_FinancingPipelineWidget',
            'page_CuentasPorCobrarPage',
            'page_MemberAccountPage',
        ]);

        // COMPANY USER — solo su compañía
        $companyUser = Role::findByName('company_user');
        $companyUser->syncPermissions([
            'view_any_financing', 'view_financing', 'create_financing',
            'view_any_transaction', 'view_transaction', 'create_transaction',
            'widget_FinancingPipelineWidget',
            'widget_PendingTransactionsWidget',
            'widget_OverdueFinancingsWidget',
            'widget_SolicitedFinancingsWidget',
            'page_CuentasPorCobrarPage',
        ]);
    }
}
