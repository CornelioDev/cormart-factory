<?php

namespace App\Services;

use App\Models\Financing;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\AccountingLedgerError;
use App\Notifications\FinancingApproachingDueDate;
use App\Notifications\FinancingDisbursed;
use App\Notifications\FinancingOverdue;
use App\Notifications\FinancingRequested;
use App\Notifications\MemberDisbursementRequested;
use App\Notifications\PendingCollectionAwaitingConfirmation;
use App\Support\NotificationType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Notification;

class NotificationService
{
    public function __construct(
        protected NotificationPreferenceService $preferences,
    ) {}

    /**
     * Users con rol super_admin u operator.
     */
    public function getAdminAndOperatorUsers(): EloquentCollection
    {
        return User::role(['super_admin', 'operator'])->where('is_active', true)->get();
    }

    /**
     * Users con rol super_admin.
     */
    public function getSuperAdminUsers(): EloquentCollection
    {
        return User::role('super_admin')->where('is_active', true)->get();
    }

    /**
     * Users con rol company_user vinculados a una compañía.
     */
    public function getCompanyUsers(int $companyId): EloquentCollection
    {
        return User::role('company_user')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Envía una notificación a una colección de usuarios con rescue().
     */
    public function notifyUsers(EloquentCollection $users, Notification $notification): void
    {
        $users->each(fn (User $user) => rescue(fn () => $user->notify($notification), report: true));
    }

    /**
     * Aplica preferencias (global + per-user) antes de despachar.
     */
    protected function dispatch(EloquentCollection $users, string $key, Notification $notification): void
    {
        $recipients = $this->preferences->filterRecipients($users, $key);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifyUsers($recipients, $notification);
    }

    // ── Métodos de conveniencia ─────────────────────────────────────────

    /**
     * #1 — Solicitud de financiamiento creada → Admin + Operator.
     */
    public function financingRequested(Financing $financing): void
    {
        $this->dispatch(
            $this->getAdminAndOperatorUsers(),
            NotificationType::FINANCING_REQUESTED,
            new FinancingRequested($financing),
        );
    }

    /**
     * #2 — Cobro pendiente de confirmación → Admin + Operator.
     */
    public function pendingCollectionCreated(Transaction $transaction): void
    {
        $this->dispatch(
            $this->getAdminAndOperatorUsers(),
            NotificationType::PENDING_COLLECTION_CREATED,
            new PendingCollectionAwaitingConfirmation($transaction),
        );
    }

    /**
     * #6 — Desembolso de financiamiento → Company Users de la compañía.
     */
    public function financingDisbursed(Transaction $transaction, EloquentCollection $financings): void
    {
        $companyId = $financings->first()?->company_id;

        if (! $companyId) {
            return;
        }

        $this->dispatch(
            $this->getCompanyUsers($companyId),
            NotificationType::FINANCING_DISBURSED,
            new FinancingDisbursed($transaction, $financings),
        );
    }

    /**
     * #5 — Desembolso de ganancia de miembro → Super Admin.
     */
    public function memberDisbursementCreated(Transaction $transaction, FundMember $member): void
    {
        $this->dispatch(
            $this->getSuperAdminUsers(),
            NotificationType::MEMBER_DISBURSEMENT_CREATED,
            new MemberDisbursementRequested($transaction, $member),
        );
    }

    /**
     * Error contable detectado → Super Admin.
     */
    public function accountingLedgerError(array $failedChecks): void
    {
        $this->dispatch(
            $this->getSuperAdminUsers(),
            NotificationType::ACCOUNTING_LEDGER_ERROR,
            new AccountingLedgerError($failedChecks),
        );
    }

    /**
     * #3 y #4 — Alertas de vencimiento (llamado desde el comando programado).
     */
    public function sendDueDateAlerts(EloquentCollection $approaching, EloquentCollection $overdue): void
    {
        $adminsOperators = $this->getAdminAndOperatorUsers();

        // Alertas a admin/operator con todos los financiamientos
        if ($approaching->isNotEmpty()) {
            $this->dispatch(
                $adminsOperators,
                NotificationType::FINANCING_APPROACHING_DUE_DATE,
                new FinancingApproachingDueDate($approaching),
            );
        }

        if ($overdue->isNotEmpty()) {
            $this->dispatch(
                $adminsOperators,
                NotificationType::FINANCING_OVERDUE,
                new FinancingOverdue($overdue),
            );
        }

        // Alertas a company_users filtradas por su compañía
        $approachingByCompany = $approaching->groupBy('company_id');
        $overdueByCompany     = $overdue->groupBy('company_id');

        $companyIds = $approachingByCompany->keys()
            ->merge($overdueByCompany->keys())
            ->unique();

        foreach ($companyIds as $companyId) {
            $companyUsers = $this->getCompanyUsers($companyId);

            if ($companyUsers->isEmpty()) {
                continue;
            }

            $companyApproaching = $approachingByCompany->get($companyId);
            $companyOverdue     = $overdueByCompany->get($companyId);

            if ($companyApproaching && $companyApproaching->isNotEmpty()) {
                $this->dispatch(
                    $companyUsers,
                    NotificationType::FINANCING_APPROACHING_DUE_DATE,
                    new FinancingApproachingDueDate($companyApproaching),
                );
            }

            if ($companyOverdue && $companyOverdue->isNotEmpty()) {
                $this->dispatch(
                    $companyUsers,
                    NotificationType::FINANCING_OVERDUE,
                    new FinancingOverdue($companyOverdue),
                );
            }
        }
    }
}
