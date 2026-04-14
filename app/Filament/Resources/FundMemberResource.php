<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundMemberResource\Pages;
use App\Models\FundMember;
use App\Models\Transaction;
use App\Services\FundMemberService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FundMemberResource extends Resource
{
    protected static ?string $model = FundMember::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationLabel  = 'Miembros del Fondo';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?string $modelLabel       = 'Miembro';
    protected static ?string $pluralModelLabel = 'Miembros del Fondo';
    protected static ?int    $navigationSort   = 4;

    /**
     * El rol member necesita pasar el gate viewAny para poder ver su propio
     * registro vía ViewFundMember, pero no debe ver el listado ni el nav item.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if ($user->hasRole('member')) {
            return $user->can('view_fund::member') && $user->fund_member_id !== null;
        }

        return parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (auth()->user()?->hasRole('member')) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->label('Tipo')
                ->options([
                    'capital'  => 'Aportante de Capital',
                    'in_kind'  => 'Aportante en Naturaleza',
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
                ->visible(fn (Get $get) => $get('type') === 'capital')
                ->live(onBlur: true)
                ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '', $state) : null),

            Placeholder::make('fund_percentage_preview')
                ->label('% del Fondo')
                ->content(function (Get $get): string {
                    $contribution = (float) str_replace(',', '', $get('contribution') ?? '0');
                    if ($contribution <= 0) {
                        return '—';
                    }
                    $pct = (new FundMemberService())->calculatePercentage($contribution);
                    return number_format($pct, 2) . '%';
                })
                ->visible(fn (Get $get) => $get('type') === 'capital'),

            DatePicker::make('joined_at')
                ->label('Miembro desde')
                ->required(),

            DatePicker::make('left_at')
                ->label('Fecha de salida')
                ->nullable(),

            Toggle::make('active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Datos del Miembro')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre'),

                    TextEntry::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'capital' => 'primary',
                            'in_kind' => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'capital' => 'Aportante de Capital',
                            'in_kind' => 'Aportante en Naturaleza',
                        }),

                    TextEntry::make('contribution')
                        ->label('Capital Aportado')
                        ->money('DOP', locale: 'es_DO'),

                    TextEntry::make('fund_percentage')
                        ->label('% del Fondo')
                        ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                        ->suffix('%'),

                    TextEntry::make('joined_at')
                        ->label('Miembro desde')
                        ->date('d M Y'),

                    TextEntry::make('active')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                        ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                ]),

            Section::make('Resumen de Ganancias')
                ->columns(3)
                ->schema([
                    TextEntry::make('total_earned')
                        ->label('Total Ganado')
                        ->getStateUsing(fn (FundMember $record): string =>
                            'RD$ ' . number_format($record->totalEarned(), 2, '.', ',')
                        )
                        ->color('success')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->size(TextEntry\TextEntrySize::Large),

                    TextEntry::make('total_disbursed')
                        ->label('Total Desembolsado')
                        ->getStateUsing(fn (FundMember $record): string =>
                            'RD$ ' . number_format($record->totalDisbursed(), 2, '.', ',')
                        )
                        ->color('warning')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->size(TextEntry\TextEntrySize::Large),

                    TextEntry::make('earnings_balance')
                        ->label('Balance Disponible')
                        ->getStateUsing(fn (FundMember $record): string =>
                            'RD$ ' . number_format($record->earningsBalance(), 2, '.', ',')
                        )
                        ->color(fn (FundMember $record): string =>
                            $record->earningsBalance() > 0 ? 'primary' : 'gray'
                        )
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->size(TextEntry\TextEntrySize::Large),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'capital'  => 'primary',
                        'in_kind'  => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'capital'  => 'Capital',
                        'in_kind'  => 'Naturaleza',
                    })
                    ->toggleable(),

                TextColumn::make('contribution')
                    ->label('Aportación')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('fund_percentage')
                    ->label('% del Fondo')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_earned')
                    ->label('Total Ganado')
                    ->getStateUsing(fn (FundMember $record): float => $record->totalEarned())
                    ->money('DOP', locale: 'es_DO')
                    ->toggleable(),

                TextColumn::make('earnings_balance')
                    ->label('Balance Ganancias')
                    ->getStateUsing(fn (FundMember $record): float => $record->earningsBalance())
                    ->money('DOP', locale: 'es_DO')
                    ->color(fn (FundMember $record): string =>
                        $record->earningsBalance() > 0 ? 'success' : 'gray'
                    )
                    ->toggleable(),

                TextColumn::make('joined_at')
                    ->label('Miembro desde')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FundMemberResource\RelationManagers\ClosingDistributionsRelationManager::class,
            FundMemberResource\RelationManagers\EarningsTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFundMembers::route('/'),
            'create' => Pages\CreateFundMember::route('/create'),
            'view'   => Pages\ViewFundMember::route('/{record}'),
        ];
    }
}
