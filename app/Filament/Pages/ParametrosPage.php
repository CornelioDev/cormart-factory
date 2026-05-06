<?php

namespace App\Filament\Pages;

use App\Models\Parameter;
use App\Services\ParameterService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ParametrosPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Parámetros';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title           = 'Parámetros de Distribución';
    protected static ?int    $navigationSort  = 3;

    protected static string $view = 'filament.pages.parametros-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
        $params = Parameter::all()->pluck('value', 'key')->toArray();

        $this->form->fill([
            'commission_pct'    => round((float) ($params['commission_pct']    ?? 5.0),  2),
            'fixed_return_pct'  => round((float) ($params['fixed_return_pct']  ?? 3.0),  2),
            'reserve_pct'       => round((float) ($params['reserve_pct']       ?? 20.0), 2),
            'in_kind_pct'       => round((float) ($params['in_kind_pct']       ?? 50.0), 2),
            'default_term_days' => (int)   ($params['default_term_days'] ?? 15),
            'tax_pct'           => round((float) ($params['tax_pct']           ?? 0.15), 4),
            'late_fee_pct'      => round((float) ($params['late_fee_pct']      ?? 5.0),  2),
            'due_alert_days'    => (int)   ($params['due_alert_days']    ?? 5),
            'alert_send_time'   =>          $params['alert_send_time']   ?? '07:00',
            'timezone'          =>          $params['timezone']          ?? 'America/Santo_Domingo',
            'allow_fund_loan_to_capital' => (string) ($params['allow_fund_loan_to_capital'] ?? '0') === '1',
        ]);
    }

    public function form(Form $form): Form
    {
        $timezones = collect(timezone_identifiers_list())
            ->filter(fn ($tz) => str_starts_with($tz, 'America/') || $tz === 'UTC')
            ->mapWithKeys(fn ($tz) => [$tz => str_replace('_', ' ', $tz)])
            ->toArray();

        return $form
            ->schema([
                Section::make('General')
                    ->columns(1)
                    ->schema([
                        Select::make('timezone')
                            ->label('Zona Horaria')
                            ->options($timezones)
                            ->searchable()
                            ->required()
                            ->helperText('Afecta fechas, horas y el scheduler de notificaciones.'),
                    ]),

                Section::make('Comisiones y Rendimientos')
                    ->description('Porcentajes que aplican a cada financiamiento y al cierre mensual.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('commission_pct')
                            ->label('Comisión por Financiamiento')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('Se descuenta del monto a desembolsar.'),

                        TextInput::make('fixed_return_pct')
                            ->label('Rendimiento Fijo para Capital')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('% mensual sobre la aportación de cada miembro de capital.'),

                        TextInput::make('reserve_pct')
                            ->label('Reserva del Fondo')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('% sobre la ganancia neta que queda en el fondo.'),

                        TextInput::make('in_kind_pct')
                            ->label('Participación en Naturaleza')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('% del post-reserva para el aportante en naturaleza.'),
                    ]),

                Section::make('Operación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_term_days')
                            ->label('Plazo Estándar')
                            ->suffix('días')
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->required()
                            ->helperText('Plazo predeterminado al crear un financiamiento.'),

                        TextInput::make('tax_pct')
                            ->label('Impuesto sobre Desembolsos')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('Se genera automáticamente como gasto en cada desembolso al fondo o a un miembro.'),

                        TextInput::make('late_fee_pct')
                            ->label('Mora por Atraso')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            ->helperText('Porcentaje sobre saldo pendiente por cada 30 días de atraso.'),
                    ]),

                Section::make('Tesorería')
                    ->description('Reglas de uso del cash del fondo y el capital.')
                    ->columns(1)
                    ->schema([
                        Toggle::make('allow_fund_loan_to_capital')
                            ->label('Permitir préstamo del fondo al capital')
                            ->helperText('Cuando esté activo, si un desembolso requiere más cash del que hay en CapitalAccount, el sistema tomará el faltante del FundAccount (ganancias retenidas), siempre que el banco total cubra la operación. La deuda se repone automáticamente con los próximos cobros.'),
                    ]),

                Section::make('Notificaciones')
                    ->description('Configuración de alertas automáticas por correo electrónico.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('due_alert_days')
                            ->label('Alerta de Vencimiento')
                            ->suffix('días')
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->required()
                            ->helperText('Días de anticipación para enviar alerta antes del vencimiento.'),

                        TextInput::make('alert_send_time')
                            ->label('Hora de Envío de Alertas')
                            ->type('time')
                            ->required()
                            ->helperText('Hora del día en que se envían las alertas de vencimiento (formato 24h).'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $values  = $this->form->getState();
        $service = new ParameterService();
        $period  = now()->format('Y-m');
        $userId  = auth()->id();

        // Parámetros que almacenan texto (no numéricos)
        $stringParams = ['alert_send_time', 'timezone'];
        $boolParams   = ['allow_fund_loan_to_capital'];

        foreach ($values as $key => $value) {
            if (in_array($key, $boolParams)) {
                $service->updateRaw($key, $value ? '1' : '0', $period, $userId);
            } elseif (in_array($key, $stringParams)) {
                $service->updateRaw($key, $value, $period, $userId);
            } else {
                $service->update($key, (float) $value, $period, $userId);
            }
        }

        Notification::make()
            ->title('Parámetros actualizados')
            ->body('Los cambios quedarán reflejados en el próximo cierre mensual.')
            ->success()
            ->send();
    }
}
