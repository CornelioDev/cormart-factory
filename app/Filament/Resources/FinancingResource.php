<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancingResource\Pages;
use App\Models\Client;
use App\Models\Financing;
use App\Models\Parameter;
use App\Services\FinancingService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FinancingResource extends Resource
{
    protected static ?string $model = Financing::class;

    protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel  = 'Financiamientos';
    protected static ?string $navigationGroup  = 'Operaciones';
    protected static ?string $modelLabel       = 'Financiamiento';
    protected static ?string $pluralModelLabel = 'Financiamientos';
    protected static ?int    $navigationSort   = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user->hasRole('company_user')) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $defaultTerm = (int) Parameter::where('key', 'default_term_days')->value('value') ?? 15;

        return $form->schema([
            // ── Datos principales ────────────────────────────────────────────
            Select::make('company_id')
                ->label('Compañía')
                ->required()
                ->searchable()
                ->preload()
                ->relationship('company', 'name', fn ($query) => $query->where('active', true))
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre / Razón Social')
                        ->required()
                        ->unique()
                        ->maxLength(255),
                    TextInput::make('rnc')
                        ->label('RNC')
                        ->maxLength(20),
                    TextInput::make('contact_name')
                        ->label('Nombre de Contacto')
                        ->maxLength(255),
                    TextInput::make('contact_email')
                        ->label('Correo de Contacto')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('contact_phone')
                        ->label('Teléfono de Contacto')
                        ->tel()
                        ->maxLength(30),
                    Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('client_id', null)),

            Select::make('client_id')
                ->label('Deudor')
                ->required()
                ->searchable()
                ->preload()
                ->options(function (Get $get): array {
                    $companyId = $get('company_id');
                    if (! $companyId) {
                        return [];
                    }

                    return Client::where('company_id', $companyId)
                        ->where('active', true)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nombre / Razón Social')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('active')
                        ->label('Activo')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data, Get $get): int {
                    return Client::create([
                        'company_id' => $get('company_id'),
                        'name'       => $data['name'],
                        'active'     => $data['active'] ?? true,
                    ])->id;
                })
                ->helperText('Selecciona primero la compañía'),

            DatePicker::make('request_date')
                ->label('Fecha de Solicitud')
                ->required()
                ->default(now())
                ->displayFormat('d/m/Y')
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $days = $get('term_days') ?? 15;
                    if ($state) {
                        $set('due_date', Carbon::parse($state)->addDays((int) $days)->format('Y-m-d'));
                    }
                }),

            TextInput::make('amount')
                ->label('Monto a Financiar')
                ->required()
                ->numeric()
                ->step(0.01)
                ->prefix('RD$')
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $amount = (float) $state;
                    $service = new FinancingService();
                    $commission = $service->calculateCommission($amount);
                    $transferAmount = $service->calculateTransferAmount($amount, $commission);
                    $set('commission', number_format($commission, 2, '.', ','));
                    $set('transfer_amount', number_format($transferAmount, 2, '.', ','));
                }),

            TextInput::make('term_days')
                ->label('Plazo (días)')
                ->required()
                ->numeric()
                ->default($defaultTerm)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    $date = $get('request_date');
                    if ($date) {
                        $set('due_date', Carbon::parse($date)->addDays((int) $state)->format('Y-m-d'));
                    }
                }),

            TextInput::make('commission')
                ->label('Comisión (5%)')
                ->prefix('RD$')
                ->disabled()
                ->dehydrated()
                ->formatStateUsing(fn ($state) => $state ? number_format((float) str_replace(',', '', $state), 2, '.', ',') : null)
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null),

            TextInput::make('transfer_amount')
                ->label('Monto a Transferir')
                ->prefix('RD$')
                ->disabled()
                ->dehydrated()
                ->formatStateUsing(fn ($state) => $state ? number_format((float) str_replace(',', '', $state), 2, '.', ',') : null)
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null),

            DatePicker::make('due_date')
                ->label('Fecha de Vencimiento')
                ->disabled()
                ->dehydrated()
                ->displayFormat('d/m/Y')
                ->default(fn () => Carbon::now()->addDays($defaultTerm)->format('Y-m-d')),

            // ── Documentos (OC / Factura) ────────────────────────────────────
            Repeater::make('documents')
                ->label('Documentos (OC / Factura)')
                ->helperText('Al menos un documento es obligatorio.')
                ->relationship('documents')
                ->schema([
                    Select::make('type')
                        ->label('Tipo')
                        ->required()
                        ->options([
                            'purchase_order' => 'Orden de Compra',
                            'invoice'        => 'Factura',
                        ]),

                    TextInput::make('document_number')
                        ->label('Número de Documento')
                        ->maxLength(255)
                        ->nullable(),

                    DatePicker::make('document_date')
                        ->label('Fecha del Documento')
                        ->displayFormat('d/m/Y')
                        ->nullable(),

                    FileUpload::make('file_path')
                        ->label('Adjunto')
                        ->disk('public')
                        ->directory('financing-documents')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->openable()
                        ->downloadable()
                        ->nullable(),
                ])
                ->minItems(1)
                ->addActionLabel('Agregar documento')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('N° Financiamiento')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Deudor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('commission')
                    ->label('Comisión')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('request_date')
                    ->label('Solicitud')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'solicited'           => 'warning',
                        'disbursed'           => 'info',
                        'partially_collected' => 'purple',
                        'collected'           => 'success',
                        'cancelled'           => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'solicited'           => 'Solicitado',
                        'disbursed'           => 'Desembolsado',
                        'partially_collected' => 'Abonado',
                        'collected'           => 'Cobrado',
                        'cancelled'           => 'Cancelado',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'solicited'           => 'Solicitado',
                        'disbursed'           => 'Desembolsado',
                        'partially_collected' => 'Abonado',
                        'collected'           => 'Cobrado',
                        'cancelled'           => 'Cancelado',
                    ]),

                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->relationship('company', 'name'),
            ])
            ->actions([
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Financing $record): bool => in_array($record->status, ['solicited', 'disbursed', 'partially_collected']))
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Motivo de Cancelación')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Financing $record, array $data): void {
                        $record->update([
                            'status'              => 'cancelled',
                            'cancellation_reason' => $data['cancellation_reason'],
                        ]);
                    }),

                EditAction::make()
                    ->visible(fn (Financing $record): bool => $record->status === 'solicited'),
            ])
            ->bulkActions([
                BulkAction::make('disburse')
                    ->label('Desembolsar seleccionados')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()->hasAnyRole(['super_admin', 'operator']))
                    ->action(function (Collection $records): void {
                        $companies = $records->pluck('company_id')->unique();
                        $statuses  = $records->pluck('status')->unique();

                        if ($companies->count() > 1) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben pertenecer a la misma compañía.')
                                ->danger()
                                ->send();
                            return;
                        }

                        if ($statuses->count() > 1 || $statuses->first() !== 'solicited') {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben estar en estado "Solicitado".')
                                ->danger()
                                ->send();
                            return;
                        }

                        redirect('/admin/transactions/create?' . http_build_query([
                            'type'          => 'disbursement',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),

                BulkAction::make('collect')
                    ->label('Cobrar seleccionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records): void {
                        $companies = $records->pluck('company_id')->unique();
                        $statuses  = $records->pluck('status')->unique();

                        if ($companies->count() > 1) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben pertenecer a la misma compañía.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $allowedForCollection = ['disbursed', 'partially_collected'];
                        if ($statuses->diff($allowedForCollection)->isNotEmpty()) {
                            Notification::make()
                                ->title('Selección inválida')
                                ->body('Todos los financiamientos deben estar en estado "Desembolsado" o "Abonado".')
                                ->danger()
                                ->send();
                            return;
                        }

                        redirect('/admin/transactions/create?' . http_build_query([
                            'type'          => 'collection',
                            'company_id'    => $records->first()->company_id,
                            'financing_ids' => $records->pluck('id')->implode(','),
                        ]));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinancings::route('/'),
            'create' => Pages\CreateFinancing::route('/create'),
            'edit'   => Pages\EditFinancing::route('/{record}/edit'),
        ];
    }
}
