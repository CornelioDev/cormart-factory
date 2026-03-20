<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationLabel  = 'Clientes (Deudores)';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?string $modelLabel       = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?int    $navigationSort   = 2;

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
        return $form->schema([
            Select::make('company_id')
                ->label('Compañía')
                ->required()
                ->searchable()
                ->preload()
                ->options(fn () => Company::where('active', true)->pluck('name', 'id')->toArray()),

            TextInput::make('name')
                ->label('Nombre / Razón Social')
                ->required()
                ->maxLength(255),

            Toggle::make('active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('company.name')
                    ->label('Compañía')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Deudor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('financings_count')
                    ->label('Financiamientos')
                    ->counts('financings')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('financings_sum_amount')
                    ->label('Monto Total')
                    ->sum('financings', 'amount')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('financings_sum_commission')
                    ->label('Comisiones')
                    ->sum('financings', 'commission')
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Compañía')
                    ->relationship('company', 'name'),

                TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit'   => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
