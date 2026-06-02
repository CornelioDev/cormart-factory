<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Support\NotificationType;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NotificationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notificaciones';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title           = 'Configuración de Notificaciones';
    protected static ?int    $navigationSort  = 11;

    protected static string $view = 'filament.pages.notification-settings-page';

    public ?array $globals    = [];
    public ?array $perUser    = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $service = app(NotificationPreferenceService::class);

        $this->globalsForm->fill($service->globals());
        $this->perUserForm->fill($this->loadPerUserMatrix());
    }

    protected function getForms(): array
    {
        return [
            'globalsForm' => $this->makeForm()->schema($this->getGlobalsSchema())->statePath('globals'),
            'perUserForm' => $this->makeForm()->schema($this->getPerUserSchema())->statePath('perUser'),
        ];
    }

    protected function getGlobalsSchema(): array
    {
        $toggles = [];

        foreach (NotificationType::all() as $key => $meta) {
            $toggles[] = Toggle::make($key)
                ->label($meta['label'])
                ->helperText($meta['description'])
                ->inline(false);
        }

        return [
            Section::make('Interruptores globales')
                ->description('Apaga una notificación a nivel sistema. Los usuarios no podrán recibirla aunque la tengan activa en su perfil.')
                ->columns(2)
                ->schema($toggles),
        ];
    }

    protected function getPerUserSchema(): array
    {
        $users = $this->relevantUsers();

        if ($users->isEmpty()) {
            return [
                Section::make('Preferencias por usuario')
                    ->description('No hay usuarios activos con roles que reciban notificaciones.')
                    ->schema([]),
            ];
        }

        $sections = [];

        foreach ($users as $user) {
            $eligible = app(NotificationPreferenceService::class)->eligibleKeysForUser($user);

            if (empty($eligible)) {
                continue;
            }

            $toggles = [];
            foreach ($eligible as $key) {
                $toggles[] = Toggle::make("user_{$user->id}.{$key}")
                    ->label(NotificationType::label($key))
                    ->inline(false);
            }

            $roleLabels = $user->getRoleNames()->implode(', ');

            $sections[] = Section::make($user->name)
                ->description("{$user->email} — {$roleLabels}")
                ->columns(2)
                ->collapsed()
                ->collapsible()
                ->schema($toggles);
        }

        return $sections;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    protected function loadPerUserMatrix(): array
    {
        $service = app(NotificationPreferenceService::class);
        $matrix  = [];

        foreach ($this->relevantUsers() as $user) {
            $prefs = $service->userPreferences($user);
            $matrix["user_{$user->id}"] = $prefs;
        }

        return $matrix;
    }

    protected function relevantUsers()
    {
        // Cualquier usuario activo cuyo rol reciba al menos una notificación.
        return User::where('is_active', true)
            ->role(['super_admin', 'operator', 'company_user'])
            ->orderBy('name')
            ->get();
    }

    public function saveGlobals(): void
    {
        $values  = $this->globalsForm->getState();
        $service = app(NotificationPreferenceService::class);

        foreach (NotificationType::keys() as $key) {
            $service->setGlobal($key, (bool) ($values[$key] ?? false));
        }

        Notification::make()
            ->title('Configuración global guardada')
            ->success()
            ->send();
    }

    public function savePerUser(): void
    {
        $values  = $this->perUserForm->getState();
        $service = app(NotificationPreferenceService::class);

        foreach ($this->relevantUsers() as $user) {
            $eligible = $service->eligibleKeysForUser($user);
            $userKey  = "user_{$user->id}";

            foreach ($eligible as $key) {
                $enabled = (bool) ($values[$userKey][$key] ?? true);
                $service->setUserPreference($user, $key, $enabled);
            }
        }

        Notification::make()
            ->title('Preferencias por usuario guardadas')
            ->success()
            ->send();
    }
}
