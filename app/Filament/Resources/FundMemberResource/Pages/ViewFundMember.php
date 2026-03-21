<?php

namespace App\Filament\Resources\FundMemberResource\Pages;

use App\Filament\Resources\FundMemberResource;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Services\FundMemberService;
use App\Services\TransactionService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ViewFundMember extends ViewRecord
{
    protected static string $resource = FundMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => auth()->user()->hasRole('super_admin'))
                ->fillForm(fn (FundMember $record): array => [
                    'name'         => $record->name,
                    'type'         => $record->type,
                    'contribution' => $record->type === 'capital' ? number_format((float) $record->contribution, 2, '.', ',') : null,
                    'joined_at'    => $record->joined_at,
                    'left_at'      => $record->left_at,
                    'active'       => $record->active,
                ])
                ->form([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'capital' => 'Aportante de Capital',
                            'in_kind' => 'Aportante en Naturaleza',
                        ])
                        ->required()
                        ->reactive(),

                    TextInput::make('contribution')
                        ->label('Aportación')
                        ->prefix('RD$')
                        ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                        ->stripCharacters(',')
                        ->numeric()
                        ->required()
                        ->visible(fn (Get $get) => $get('type') === 'capital'),

                    DatePicker::make('joined_at')
                        ->label('Miembro desde')
                        ->required(),

                    DatePicker::make('left_at')
                        ->label('Fecha de salida')
                        ->nullable(),

                    Toggle::make('active')
                        ->label('Activo'),
                ])
                ->action(function (FundMember $record, array $data): void {
                    $record->update([
                        'name'         => $data['name'],
                        'type'         => $data['type'],
                        'contribution' => $data['type'] === 'capital'
                            ? (float) str_replace(',', '', $data['contribution'])
                            : 0,
                        'joined_at'    => $data['joined_at'],
                        'left_at'      => $data['left_at'],
                        'active'       => $data['active'],
                    ]);

                    (new FundMemberService())->recalculateAllPercentages();

                    Notification::make()
                        ->title('Miembro actualizado')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record]));
                }),

            Actions\Action::make('disburse_earnings')
                ->label('Desembolsar Ganancias')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (FundMember $record): bool =>
                    auth()->user()->hasRole('super_admin')
                    && $record->earningsBalance() > 0
                )
                ->form([
                    Placeholder::make('balance_display')
                        ->label('Balance Disponible')
                        ->content(fn (FundMember $record): string =>
                            'RD$ ' . number_format($record->earningsBalance(), 2, '.', ',')
                        ),

                    TextInput::make('amount')
                        ->label('Monto a Desembolsar')
                        ->prefix('RD$')
                        ->required()
                        ->mask(RawJs::make("\$money(\$input, '.', ',', 2)"))
                        ->stripCharacters(',')
                        ->numeric()
                        ->rule(fn (FundMember $record): \Closure =>
                            function (string $attribute, $value, \Closure $fail) use ($record) {
                                $amount = (float) str_replace(',', '', $value);
                                if ($amount <= 0) {
                                    $fail('El monto debe ser mayor a cero.');
                                }
                                if ($amount > $record->earningsBalance()) {
                                    $fail('El monto excede el balance disponible.');
                                }
                            }
                        ),

                    Select::make('bank')
                        ->label('Banco')
                        ->required()
                        ->options([
                            'BanReservas'   => 'BanReservas',
                            'BHD'           => 'BHD',
                            'Banco Popular' => 'Banco Popular',
                        ]),

                    TextInput::make('transaction_number')
                        ->label('Número de Transacción')
                        ->required()
                        ->unique(table: 'transactions', column: 'transaction_number')
                        ->maxLength(255),

                    DatePicker::make('transaction_date')
                        ->label('Fecha de Transacción')
                        ->required()
                        ->default(now())
                        ->displayFormat('d/m/Y'),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->maxLength(500),
                ])
                ->modalHeading(fn (FundMember $record): string =>
                    "Desembolsar Ganancias — {$record->name}"
                )
                ->modalSubmitActionLabel('Desembolsar')
                ->requiresConfirmation()
                ->action(function (FundMember $record, array $data): void {
                    (new TransactionService())->createMemberDisbursement([
                        'fund_member_id'     => $record->id,
                        'amount'             => (float) str_replace(',', '', $data['amount']),
                        'bank'               => $data['bank'],
                        'transaction_number' => $data['transaction_number'],
                        'transaction_date'   => $data['transaction_date'],
                        'notes'              => $data['notes'],
                    ]);

                    Notification::make()
                        ->title('Desembolso registrado')
                        ->body("Se desembolsaron ganancias a {$record->name}")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record]));
                }),
        ];
    }
}
