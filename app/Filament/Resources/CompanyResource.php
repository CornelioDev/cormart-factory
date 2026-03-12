<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon   = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel  = 'Compañías';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?string $modelLabel       = 'Compañía';
    protected static ?string $pluralModelLabel = 'Compañías';
    protected static ?int    $navigationSort   = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nombre / Razón Social')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('rnc')
                ->label('RNC')
                ->maxLength(20)
                ->nullable(),

            TextInput::make('contact_name')
                ->label('Nombre de Contacto')
                ->maxLength(255)
                ->nullable(),

            TextInput::make('contact_email')
                ->label('Correo de Contacto')
                ->email()
                ->maxLength(255)
                ->nullable(),

            TextInput::make('contact_phone')
                ->label('Teléfono de Contacto')
                ->tel()
                ->maxLength(30)
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
                    ->label('Compañía')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rnc')
                    ->label('RNC')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('contact_email')
                    ->label('Correo')
                    ->placeholder('—'),

                TextColumn::make('contact_phone')
                    ->label('Teléfono')
                    ->placeholder('—'),

                TextColumn::make('clients_count')
                    ->label('Deudores')
                    ->counts('clients')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Estado')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Estado')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
