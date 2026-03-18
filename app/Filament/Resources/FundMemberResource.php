<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundMemberResource\Pages;
use App\Models\FundMember;
use App\Services\FundMemberService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\RawJs;
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

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
                    }),

                TextColumn::make('contribution')
                    ->label('Aportación')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable(),

                TextColumn::make('fund_percentage')
                    ->label('% del Fondo')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('joined_at')
                    ->label('Miembro desde')
                    ->date('d M Y')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFundMembers::route('/'),
            'create' => Pages\CreateFundMember::route('/create'),
            'edit'   => Pages\EditFundMember::route('/{record}/edit'),
        ];
    }
}