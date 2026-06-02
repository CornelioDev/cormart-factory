<?php

namespace App\Filament\Resources\FinancingResource\Pages;

use App\Filament\Resources\FinancingResource;
use App\Services\FinancingService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewFinancing extends ViewRecord
{
    protected static string $resource = FinancingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->status === 'solicited'),

            Actions\Action::make('disburse')
                ->label(fn (): string => $this->record->status === 'partially_disbursed' ? 'Registrar partida' : 'Desembolsar')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn (): bool => in_array($this->record->status, ['solicited', 'partially_disbursed'])
                    && auth()->user()->hasAnyRole(['super_admin', 'operator']))
                ->url(fn (): string => '/admin/transactions/create?' . http_build_query([
                    'type'          => 'disbursement',
                    'company_id'    => $this->record->company_id,
                    'financing_ids' => (string) $this->record->id,
                ])),

            Actions\Action::make('collect')
                ->label(fn (): string => auth()->user()->hasRole('company_user') ? 'Pagar' : 'Cobrar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, ['disbursed', 'partially_collected'])
                    && auth()->user()->hasAnyRole(['super_admin', 'operator', 'company_user']))
                ->url(fn (): string => '/admin/transactions/create?' . http_build_query([
                    'type'          => 'collection',
                    'company_id'    => $this->record->company_id,
                    'financing_ids' => (string) $this->record->id,
                ])),

            Actions\Action::make('confirmReceipt')
                ->label('Confirmar recepción')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (): bool {
                    if (! in_array($this->record->status, ['disbursed', 'partially_collected'], true)) {
                        return false;
                    }
                    if ($this->record->confirmed_at !== null) {
                        return false;
                    }
                    $user = auth()->user();
                    if ($user->hasRole('super_admin')) {
                        return true;
                    }
                    return $user->hasRole('company_user')
                        && (int) $user->company_id === (int) $this->record->company_id;
                })
                ->requiresConfirmation()
                ->modalHeading('Confirmar Recepción de Mercancía')
                ->modalDescription(fn (): string => sprintf(
                    'Al confirmar, el plazo de %d días comenzará hoy y la fecha de vencimiento quedará fijada al %s. Esta acción no se puede deshacer.',
                    (int) $this->record->term_days,
                    Carbon::today()->addDays((int) $this->record->term_days)->format('d/m/Y'),
                ))
                ->modalSubmitActionLabel('Sí, confirmar recepción')
                ->action(function (): void {
                    try {
                        app(FinancingService::class)->confirmReceipt($this->record, auth()->user());

                        Notification::make()
                            ->success()
                            ->title('Recepción confirmada')
                            ->body('El plazo del financiamiento ha comenzado.')
                            ->send();

                        $this->redirect(FinancingResource::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo confirmar')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'solicited'
                    && auth()->user()->hasAnyRole(['super_admin', 'operator']))
                ->requiresConfirmation()
                ->modalHeading('Cancelar Financiamiento')
                ->modalDescription('Esta acción no se puede deshacer. ¿Está seguro?')
                ->form([
                    Textarea::make('cancellation_reason')
                        ->label('Motivo de Cancelación')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status'              => 'cancelled',
                        'cancellation_reason' => $data['cancellation_reason'],
                    ]);
                    $this->redirect(FinancingResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
