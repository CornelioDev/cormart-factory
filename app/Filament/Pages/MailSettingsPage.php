<?php

namespace App\Filament\Pages;

use App\Models\Financing;
use App\Models\FundMember;
use App\Models\MailSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\FinancingApproachingDueDate;
use App\Notifications\FinancingDisbursed;
use App\Notifications\FinancingOverdue;
use App\Notifications\FinancingRequested;
use App\Notifications\MemberDisbursementRequested;
use App\Notifications\PendingCollectionAwaitingConfirmation;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class MailSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Correo Electrónico';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title           = 'Configuración de Correo';
    protected static ?int    $navigationSort  = 10;

    protected static string $view = 'filament.pages.mail-settings-page';

    public ?array $data     = [];
    public ?array $testData = [];

    protected function getForms(): array
    {
        return [
            'form'     => $this->makeForm()->schema($this->getFormSchema())->statePath('data'),
            'testForm' => $this->makeForm()->schema($this->getTestFormSchema())->statePath('testData'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        $settings = MailSetting::first();

        $this->form->fill([
            'host'         => $settings?->host ?? '',
            'port'         => $settings?->port ?? 465,
            'username'     => $settings?->username ?? '',
            'encryption'   => $settings?->encryption ?? 'smtps',
            'from_address' => $settings?->from_address ?? '',
            'from_name'    => $settings?->from_name ?? config('app.name'),
        ]);

        $this->testForm->fill([
            'test_email' => auth()->user()->email,
            'test_type'  => 'simple',
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Servidor SMTP')
                ->description('Configuración del servidor de correo saliente.')
                ->columns(2)
                ->schema([
                    TextInput::make('host')
                        ->label('Servidor (Host)')
                        ->placeholder('mail.ejemplo.com')
                        ->required(),

                    TextInput::make('port')
                        ->label('Puerto')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->required(),

                    TextInput::make('username')
                        ->label('Usuario')
                        ->placeholder('usuario@ejemplo.com'),

                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->placeholder('Dejar vacío para mantener la actual'),

                    Select::make('encryption')
                        ->label('Encriptación')
                        ->options([
                            'smtps' => 'SSL/TLS (puerto 465)',
                            'smtp'  => 'STARTTLS (puerto 587)',
                            'none'  => 'Ninguna (puerto 25)',
                        ])
                        ->required(),
                ]),

            Section::make('Remitente')
                ->description('Datos del remitente en los correos enviados.')
                ->columns(2)
                ->schema([
                    TextInput::make('from_address')
                        ->label('Correo del Remitente')
                        ->email()
                        ->placeholder('notificaciones@ejemplo.com')
                        ->required(),

                    TextInput::make('from_name')
                        ->label('Nombre del Remitente')
                        ->placeholder('Cormart Factory')
                        ->required(),
                ]),
        ];
    }

    protected function getTestFormSchema(): array
    {
        return [
            Section::make('Correo de Prueba')
                ->description('Envía un correo de prueba para verificar la configuración.')
                ->columns(2)
                ->schema([
                    TextInput::make('test_email')
                        ->label('Enviar a')
                        ->email()
                        ->required()
                        ->placeholder('correo@ejemplo.com'),

                    Select::make('test_type')
                        ->label('Tipo de Notificación')
                        ->options([
                            'simple'               => 'Correo simple (conexión SMTP)',
                            'financing_requested'  => 'Solicitud de financiamiento',
                            'pending_collection'   => 'Cobro pendiente de confirmación',
                            'financing_disbursed'  => 'Desembolso de financiamiento',
                            'approaching_due_date' => 'Financiamiento próximo a vencer',
                            'financing_overdue'    => 'Financiamiento vencido',
                            'member_disbursement'  => 'Desembolso de ganancia de miembro',
                        ])
                        ->required(),
                ]),
        ];
    }

    public function save(): void
    {
        $values   = $this->form->getState();
        $settings = MailSetting::instance();

        $updateData = [
            'host'         => $values['host'],
            'port'         => $values['port'],
            'username'     => $values['username'],
            'encryption'   => $values['encryption'],
            'from_address' => $values['from_address'],
            'from_name'    => $values['from_name'],
        ];

        // Solo actualizar password si se proporcionó uno nuevo
        if (! empty($values['password'])) {
            $updateData['password'] = $values['password'];
        }

        $settings->update($updateData);

        Notification::make()
            ->title('Configuración guardada')
            ->body('La configuración de correo ha sido actualizada.')
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        $testValues = $this->testForm->getState();
        $settings   = MailSetting::instance();

        if (! $settings->host) {
            Notification::make()
                ->title('Configuración incompleta')
                ->body('Guarda la configuración SMTP antes de enviar un correo de prueba.')
                ->warning()
                ->send();

            return;
        }

        // Aplicar la configuración actual en runtime
        config([
            'mail.default'               => 'smtp',
            'mail.mailers.smtp.host'     => $settings->host,
            'mail.mailers.smtp.port'     => $settings->port,
            'mail.mailers.smtp.username' => $settings->username,
            'mail.mailers.smtp.password' => $settings->decrypted_password,
            'mail.mailers.smtp.scheme'   => $settings->encryption === 'none' ? null : $settings->encryption,
            'mail.from.address'          => $settings->from_address,
            'mail.from.name'             => $settings->from_name,
        ]);

        Mail::purge('smtp');

        $email = $testValues['test_email'];
        $type  = $testValues['test_type'];

        try {
            if ($type === 'simple') {
                $this->sendSimpleTest($email);
            } else {
                $this->sendNotificationTest($email, $type);
            }

            Notification::make()
                ->title('Correo enviado')
                ->body("Se envió un correo de prueba ({$this->getTestTypeLabel($type)}) a {$email}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al enviar correo')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function sendSimpleTest(string $email): void
    {
        Mail::raw(
            'Este es un correo de prueba desde Cormart Factory. Si lo recibes, la configuración SMTP está funcionando correctamente.',
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('Cormart Factory — Correo de Prueba');
            }
        );
    }

    private function sendNotificationTest(string $email, string $type): void
    {
        // Crear un usuario temporal en memoria para enviar la notificación
        $testUser        = new User();
        $testUser->name  = 'Usuario de Prueba';
        $testUser->email = $email;

        $notification = match ($type) {
            'financing_requested'  => $this->buildFinancingRequestedTest(),
            'pending_collection'   => $this->buildPendingCollectionTest(),
            'financing_disbursed'  => $this->buildFinancingDisbursedTest(),
            'approaching_due_date' => $this->buildApproachingDueDateTest(),
            'financing_overdue'    => $this->buildFinancingOverdueTest(),
            'member_disbursement'  => $this->buildMemberDisbursementTest(),
        };

        $testUser->notify($notification);
    }

    private function buildFinancingRequestedTest(): FinancingRequested
    {
        $financing = Financing::with(['company', 'client'])->latest()->first();

        if (! $financing) {
            throw new \Exception('No hay financiamientos en el sistema para usar como ejemplo.');
        }

        return new FinancingRequested($financing);
    }

    private function buildPendingCollectionTest(): PendingCollectionAwaitingConfirmation
    {
        $transaction = Transaction::where('type', 'collection')->with(['company', 'registeredBy'])->latest()->first();

        if (! $transaction) {
            throw new \Exception('No hay cobros en el sistema para usar como ejemplo.');
        }

        return new PendingCollectionAwaitingConfirmation($transaction);
    }

    private function buildFinancingDisbursedTest(): FinancingDisbursed
    {
        $transaction = Transaction::where('type', 'disbursement')->with('financings')->latest()->first();

        if (! $transaction) {
            throw new \Exception('No hay desembolsos en el sistema para usar como ejemplo.');
        }

        return new FinancingDisbursed($transaction, $transaction->financings);
    }

    private function buildApproachingDueDateTest(): FinancingApproachingDueDate
    {
        $financings = Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->whereNotNull('due_date')
            ->with('company')
            ->latest()
            ->take(3)
            ->get();

        if ($financings->isEmpty()) {
            throw new \Exception('No hay financiamientos activos con fecha de vencimiento para usar como ejemplo.');
        }

        return new FinancingApproachingDueDate($financings);
    }

    private function buildFinancingOverdueTest(): FinancingOverdue
    {
        $financings = Financing::whereIn('status', ['disbursed', 'partially_collected'])
            ->whereNotNull('due_date')
            ->with('company')
            ->latest()
            ->take(3)
            ->get();

        if ($financings->isEmpty()) {
            throw new \Exception('No hay financiamientos activos con fecha de vencimiento para usar como ejemplo.');
        }

        return new FinancingOverdue($financings);
    }

    private function buildMemberDisbursementTest(): MemberDisbursementRequested
    {
        $transaction = Transaction::where('type', 'member_disbursement')->with('fundMember')->latest()->first();

        if ($transaction) {
            return new MemberDisbursementRequested($transaction, $transaction->fundMember);
        }

        // Si no hay desembolsos de miembro, usar una transacción y miembro cualquiera
        $member      = FundMember::first();
        $transaction = Transaction::latest()->first();

        if (! $member || ! $transaction) {
            throw new \Exception('No hay miembros ni transacciones en el sistema para usar como ejemplo.');
        }

        return new MemberDisbursementRequested($transaction, $member);
    }

    private function getTestTypeLabel(string $type): string
    {
        return match ($type) {
            'simple'               => 'Correo simple',
            'financing_requested'  => 'Solicitud de financiamiento',
            'pending_collection'   => 'Cobro pendiente',
            'financing_disbursed'  => 'Desembolso',
            'approaching_due_date' => 'Próximo a vencer',
            'financing_overdue'    => 'Vencido',
            'member_disbursement'  => 'Desembolso de ganancia',
            default                => $type,
        };
    }
}
