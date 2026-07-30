<?php

namespace App\Policies;

use App\Models\Financing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FinancingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_financing');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('view_financing');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_financing');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('update_financing');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('delete_financing');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_financing');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('force_delete_financing');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_financing');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('restore_financing');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_financing');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Financing $financing): bool
    {
        if ($user->hasRole('company_user') && (int) $user->company_id !== (int) $financing->company_id) {
            return false;
        }

        return $user->can('replicate_financing');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_financing');
    }
}
