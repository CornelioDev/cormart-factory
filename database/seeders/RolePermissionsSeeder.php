<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // OPERATOR
        $operator = Role::findByName('operator');
        $operator->syncPermissions([
            'view_any_company', 'view_company', 'create_company', 'update_company',
            'view_any_financing', 'view_financing', 'create_financing', 'update_financing',
            'view_any_transaction', 'view_transaction', 'create_transaction',
            'view_any_client', 'view_client', 'create_client', 'update_client',
            'widget_CapitalSummaryWidget',
            'widget_FinancingPipelineWidget',
            'widget_PendingTransactionsWidget',
            'page_CuentasPorCobrarPage',
            'page_CuentasPorPagarPage',
        ]);

        // MEMBER — solo lectura
        $member = Role::findByName('member');
        $member->syncPermissions([
            'view_any_financing', 'view_financing',
            'view_any_monthly::closing', 'view_monthly::closing',
            'widget_CapitalSummaryWidget',
            'widget_FinancingPipelineWidget',
            'page_CuentasPorCobrarPage',
        ]);

        // COMPANY USER — solo su compañía
        $companyUser = Role::findByName('company_user');
        $companyUser->syncPermissions([
            'view_any_financing', 'view_financing',
            'view_any_transaction', 'view_transaction', 'create_transaction',
            'widget_FinancingPipelineWidget',
            'page_CuentasPorCobrarPage',
        ]);
    }
}
