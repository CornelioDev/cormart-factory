<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\NotificationType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class NotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function globals(): array
    {
        $stored = NotificationSetting::pluck('enabled', 'key')->all();

        $result = [];
        foreach (NotificationType::keys() as $key) {
            $result[$key] = $stored[$key] ?? true;
        }

        return $result;
    }

    public function isGloballyEnabled(string $key): bool
    {
        return NotificationSetting::where('key', $key)->value('enabled') ?? true;
    }

    public function setGlobal(string $key, bool $enabled): void
    {
        if (! in_array($key, NotificationType::keys(), true)) {
            return;
        }

        NotificationSetting::updateOrCreate(
            ['key' => $key],
            ['enabled' => $enabled],
        );
    }

    /**
     * Preferencias efectivas del usuario (default true por ausencia de registro),
     * limitadas a los tipos elegibles según sus roles.
     *
     * @return array<string, bool>
     */
    public function userPreferences(User $user): array
    {
        $eligible = $this->eligibleKeysForUser($user);
        $stored   = UserNotificationPreference::where('user_id', $user->id)
            ->pluck('enabled', 'notification_key')
            ->all();

        $result = [];
        foreach ($eligible as $key) {
            $result[$key] = $stored[$key] ?? true;
        }

        return $result;
    }

    public function setUserPreference(User $user, string $key, bool $enabled): void
    {
        if (! in_array($key, NotificationType::keys(), true)) {
            return;
        }

        // Default es opt-in: si el usuario vuelve a true, eliminar el row para mantener la tabla limpia.
        if ($enabled) {
            UserNotificationPreference::where('user_id', $user->id)
                ->where('notification_key', $key)
                ->delete();

            return;
        }

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'notification_key' => $key],
            ['enabled' => false],
        );
    }

    /**
     * Filtra una colección de destinatarios para un tipo de notificación:
     * - vacía si el toggle global está apagado
     * - excluye usuarios con opt-out explícito
     */
    public function filterRecipients(EloquentCollection $users, string $key): EloquentCollection
    {
        if (! $this->isGloballyEnabled($key)) {
            return $users->take(0);
        }

        $optedOut = UserNotificationPreference::where('notification_key', $key)
            ->where('enabled', false)
            ->whereIn('user_id', $users->pluck('id'))
            ->pluck('user_id')
            ->all();

        if (empty($optedOut)) {
            return $users;
        }

        return $users->reject(fn (User $u) => in_array($u->id, $optedOut, true))->values();
    }

    /**
     * @return array<int, string>
     */
    public function eligibleKeysForUser(User $user): array
    {
        $roles = $user->getRoleNames()->all();
        $keys  = [];

        foreach ($roles as $role) {
            $keys = array_merge($keys, NotificationType::keysForRole($role));
        }

        return array_values(array_unique($keys));
    }
}
