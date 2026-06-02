<?php

namespace App\Filament\Pages\Auth;

use App\Services\NotificationPreferenceService;
use App\Support\NotificationType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Datos de la cuenta')
                            ->schema([
                                $this->getNameFormComponent(),
                                $this->getEmailFormComponent(),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),

                        $this->getNotificationPreferencesSection(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function getNotificationPreferencesSection(): Section
    {
        $service  = app(NotificationPreferenceService::class);
        $user     = $this->getUser();
        $eligible = $service->eligibleKeysForUser($user);

        if (empty($eligible)) {
            return Section::make('Preferencias de Notificación')
                ->description('Tu rol no recibe notificaciones por correo del sistema.')
                ->schema([]);
        }

        $components = [];
        foreach ($eligible as $key) {
            $globallyEnabled = $service->isGloballyEnabled($key);

            $toggle = Toggle::make("notify_{$key}")
                ->label(NotificationType::label($key))
                ->helperText(NotificationType::description($key))
                ->dehydrated(false)
                ->inline(false);

            if (! $globallyEnabled) {
                $toggle = $toggle
                    ->disabled()
                    ->helperText(NotificationType::description($key) . ' — Desactivada globalmente por el administrador.');
            }

            $components[] = $toggle;
        }

        return Section::make('Preferencias de Notificación')
            ->description('Desactiva los correos que no quieras recibir. Si el administrador apaga una notificación a nivel sistema, no la recibirás aunque la tengas activa.')
            ->columns(2)
            ->schema($components);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $service = app(NotificationPreferenceService::class);
        $prefs   = $service->userPreferences($this->getUser());

        foreach ($prefs as $key => $enabled) {
            $data["notify_{$key}"] = $enabled;
        }

        return $data;
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        // Persistir preferencias de notificación tras el save estándar.
        $service  = app(NotificationPreferenceService::class);
        $user     = $this->getUser();
        $eligible = $service->eligibleKeysForUser($user);

        foreach ($eligible as $key) {
            $field = "notify_{$key}";
            if (! array_key_exists($field, $this->data)) {
                continue;
            }

            // No persistir cambios sobre toggles deshabilitados globalmente
            if (! $service->isGloballyEnabled($key)) {
                continue;
            }

            $service->setUserPreference($user, $key, (bool) $this->data[$field]);
        }

        return parent::getSavedNotification();
    }
}
