<?php

namespace App\Filament\Pages;

use App\Models\Parameter;
use App\Services\ParameterService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
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
    protected static ?string $navigationGroup = 'Cierre Mensual';
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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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

        foreach ($values as $key => $value) {
            $service->update($key, (float) $value, $period, $userId);
        }

        Notification::make()
            ->title('Parámetros actualizados')
            ->body('Los cambios quedarán reflejados en el próximo cierre mensual.')
            ->success()
            ->send();
    }
}
