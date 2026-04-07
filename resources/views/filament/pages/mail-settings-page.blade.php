<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <div style="display:flex;gap:12px">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Guardar configuración
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    <x-filament-panels::form wire:submit="sendTestEmail">
        {{ $this->testForm }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" color="gray">
                Enviar correo de prueba
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
