<?php

namespace App\Support;

class NotificationType
{
    public const FINANCING_REQUESTED            = 'financing_requested';
    public const PENDING_COLLECTION_CREATED     = 'pending_collection_created';
    public const FINANCING_DISBURSED            = 'financing_disbursed';
    public const MEMBER_DISBURSEMENT_CREATED    = 'member_disbursement_created';
    public const FINANCING_APPROACHING_DUE_DATE = 'financing_approaching_due_date';
    public const FINANCING_OVERDUE              = 'financing_overdue';
    public const ACCOUNTING_LEDGER_ERROR        = 'accounting_ledger_error';

    /**
     * Catálogo completo: key => metadata.
     *
     * @return array<string, array{label: string, description: string, roles: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            self::FINANCING_REQUESTED => [
                'label'       => 'Solicitud de financiamiento',
                'description' => 'Se envía cuando un company_user crea una nueva solicitud de financiamiento.',
                'roles'       => ['super_admin', 'operator'],
            ],
            self::PENDING_COLLECTION_CREATED => [
                'label'       => 'Cobro pendiente de confirmación',
                'description' => 'Se envía cuando un company_user registra un cobro que requiere confirmación.',
                'roles'       => ['super_admin', 'operator'],
            ],
            self::FINANCING_DISBURSED => [
                'label'       => 'Desembolso de financiamiento',
                'description' => 'Se envía al company_user cuando un financiamiento de su compañía se desembolsa.',
                'roles'       => ['company_user'],
            ],
            self::MEMBER_DISBURSEMENT_CREATED => [
                'label'       => 'Desembolso de ganancia de miembro',
                'description' => 'Se envía al super_admin cuando se registra un desembolso de ganancia a un miembro del fondo.',
                'roles'       => ['super_admin'],
            ],
            self::FINANCING_APPROACHING_DUE_DATE => [
                'label'       => 'Financiamiento próximo a vencer',
                'description' => 'Alerta diaria sobre financiamientos cuya fecha de vencimiento se aproxima.',
                'roles'       => ['super_admin', 'operator', 'company_user'],
            ],
            self::FINANCING_OVERDUE => [
                'label'       => 'Financiamiento vencido',
                'description' => 'Alerta diaria sobre financiamientos cuya fecha de vencimiento ya pasó.',
                'roles'       => ['super_admin', 'operator', 'company_user'],
            ],
            self::ACCOUNTING_LEDGER_ERROR => [
                'label'       => 'Error contable en ledger',
                'description' => 'Se envía al super_admin cuando la verificación de ledgers detecta una discrepancia.',
                'roles'       => ['super_admin'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    public static function description(string $key): string
    {
        return self::all()[$key]['description'] ?? '';
    }

    /**
     * @return array<int, string>
     */
    public static function rolesFor(string $key): array
    {
        return self::all()[$key]['roles'] ?? [];
    }

    /**
     * Tipos elegibles para un rol específico.
     *
     * @return array<int, string>
     */
    public static function keysForRole(string $role): array
    {
        return array_keys(array_filter(
            self::all(),
            fn (array $meta) => in_array($role, $meta['roles'], true),
        ));
    }
}
