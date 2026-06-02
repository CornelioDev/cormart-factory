<x-filament-panels::page>
    <x-filament-panels::form wire:submit="saveGlobals">
        {{ $this->globalsForm }}

        <div style="display:flex;gap:12px">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Guardar configuración global
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    <x-filament-panels::form wire:submit="savePerUser">
        {{ $this->perUserForm }}

        <div style="display:flex;gap:12px">
            <x-filament::button type="submit" icon="heroicon-o-check" color="gray">
                Guardar preferencias por usuario
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
