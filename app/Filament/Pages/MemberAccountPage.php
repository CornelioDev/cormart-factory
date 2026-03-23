<?php

namespace App\Filament\Pages;

use App\Filament\Resources\FundMemberResource;
use Filament\Pages\Page;

class MemberAccountPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Estado de Cuenta';
    protected static ?string $navigationGroup = 'Miembros';
    protected static ?string $title           = 'Estado de Cuenta';
    protected static ?int    $navigationSort  = 5;

    protected static string $view = 'filament.pages.member-account-page';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user->hasRole('member') && $user->fund_member_id !== null;
    }

    public function mount(): void
    {
        $this->redirect(
            FundMemberResource::getUrl('view', ['record' => auth()->user()->fund_member_id])
        );
    }
}
