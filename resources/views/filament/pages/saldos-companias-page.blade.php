<x-filament-panels::page>
    @php
        $saldos = $this->saldos;
        $total  = $this->totalPendiente;
    @endphp

    @if ($saldos->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Saldo pendiente por compañía</x-slot>
            <x-slot name="description">
                Efectivo del fondo que está en manos de las compañías. No es cuenta por cobrar
                a los deudores: es saldo contra la compañía.
            </x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
                @foreach ($saldos as $row)
                    <div style="border:1px solid rgba(148,163,184,.3);border-radius:10px;padding:16px">
                        <div style="font-size:.8rem;opacity:.7;margin-bottom:6px">
                            {{ $row['company']->name }}
                        </div>
                        <div style="font-size:1.5rem;font-weight:600;font-variant-numeric:tabular-nums">
                            RD$ {{ number_format($row['balance'], 2, '.', ',') }}
                        </div>
                    </div>
                @endforeach

                <div style="border:1px solid rgba(148,163,184,.45);border-radius:10px;padding:16px">
                    <div style="font-size:.8rem;opacity:.7;margin-bottom:6px">Total pendiente</div>
                    <div style="font-size:1.5rem;font-weight:700;font-variant-numeric:tabular-nums">
                        RD$ {{ number_format($total, 2, '.', ',') }}
                    </div>
                </div>
            </div>
        </x-filament::section>
    @endif

    <div style="margin-top:24px">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
