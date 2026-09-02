<?php

namespace App\Filament\Pages;

use App\Models\MonthlyClosing;
use App\Services\DistributionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class MonthlyClosingPage extends Page
{
    protected static ?string $navigationIcon   = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Cierre Mensual';
    protected static ?string $navigationGroup = 'Contabilidad';
    protected static ?string $title           = 'Cierre Mensual';
    protected static ?int    $navigationSort  = 1;

    protected static string $view = 'filament.pages.monthly-closing-page';

    public ?string $selectedPeriod = null;
    public bool $showPreview = false;
    public bool $alreadyClosed = false;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function getPeriodOptions(): array
    {
        $options = [];
        $current = now()->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $date = $current->copy()->subMonths($i);
            $key  = $date->format('Y-m');
            $options[$key] = $date->translatedFormat('F Y');
        }

        return $options;
    }

    /**
     * La distribución se recalcula en cada render en lugar de viajar dentro del
     * snapshot de Livewire. Mantenerla fuera del estado deja el payload en unos
     * pocos bytes y evita que el checksum dependa de su contenido.
     */
    #[Computed]
    public function preview(): ?array
    {
        if (! $this->showPreview || ! $this->selectedPeriod) {
            return null;
        }

        return (new DistributionService())->calculate($this->selectedPeriod);
    }

    public function calculate(): void
    {
        if (!$this->selectedPeriod) {
            Notification::make()
                ->title('Selecciona un periodo')
                ->warning()
                ->send();
            return;
        }

        $this->alreadyClosed = MonthlyClosing::where('period', $this->selectedPeriod)->exists();

        $this->showPreview = true;

        unset($this->preview);
    }

    public function executeClosing(): void
    {
        if (!$this->selectedPeriod || !$this->showPreview) {
            return;
        }

        if ($this->alreadyClosed) {
            Notification::make()
                ->title('Este periodo ya fue cerrado')
                ->danger()
                ->send();
            return;
        }

        try {
            $service = new DistributionService();
            $service->executeClosing($this->selectedPeriod, auth()->id());

            $this->showPreview   = false;
            $this->alreadyClosed = true;

            unset($this->preview);

            Notification::make()
                ->title('Cierre ejecutado correctamente')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedPeriod')
                ->label('Periodo a cerrar')
                ->options($this->getPeriodOptions())
                ->placeholder('Selecciona un periodo')
                ->helperText('Un periodo solo puede cerrarse una vez'),
        ];
    }
}