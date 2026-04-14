<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon   = 'heroicon-o-users';
    protected static ?string $navigationLabel  = 'Usuarios';
    protected static ?string $navigationGroup  = 'Administración';
    protected static ?string $modelLabel       = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?int    $navigationSort   = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Correo electrónico')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),

            Select::make('roles')
                ->label('Rol')
                ->relationship('roles', 'name')
                ->options(Role::all()->pluck('name', 'id'))
                ->preload()
                ->required()
                ->live(),

            Select::make('fund_member_id')
                ->label('Miembro del Fondo')
                ->relationship('fundMember', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool =>
                    collect($get('roles') ?? [])
                        ->contains(fn ($v) => Role::find($v)?->name === 'member')
                )
                ->required(fn (Get $get): bool =>
                    collect($get('roles') ?? [])
                        ->contains(fn ($v) => Role::find($v)?->name === 'member')
                )
                ->helperText('Asociar este usuario a un miembro del fondo'),

            Select::make('company_id')
                ->label('Compañía')
                ->relationship('company', 'name', fn ($query) => $query->where('active', true))
                ->searchable()
                ->preload()
                ->nullable()
                ->visible(fn (Get $get): bool =>
                    collect($get('roles') ?? [])
                        ->contains(fn ($v) => Role::find($v)?->name === 'company_user')
                )
                ->required(fn (Get $get): bool =>
                    collect($get('roles') ?? [])
                        ->contains(fn ($v) => Role::find($v)?->name === 'company_user')
                )
                ->helperText('Compañía asociada para este usuario externo'),

            Toggle::make('is_active')
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
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin'  => 'danger',
                        'operator'     => 'warning',
                        'member'       => 'success',
                        'company_user' => 'info',
                        default        => 'gray',
                    })
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}