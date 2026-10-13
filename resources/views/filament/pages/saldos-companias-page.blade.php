<x-filament-panels::page>
    @php
        $resumen  = $this->resumen;
        $selected = $this->selectedCompany;
        $money    = fn (float $value): string => 'RD$ ' . number_format($value, 2, '.', ',');

        $totalCheques     = round($resumen->sum('checks'), 2);
        $totalSobreDesemb = round($resumen->sum('overdisbursed'), 2);

        $th    = 'padding:10px 14px;font-size:.8rem;font-weight:600;text-align:left;vertical-align:bottom;white-space:nowrap';
        $thNum = $th . ';text-align:right';
        $hint  = 'display:block;font-size:.72rem;font-weight:400;opacity:.65;white-space:normal;max-width:240px';
        $td    = 'padding:10px 14px;border-top:1px solid rgba(148,163,184,.25);vertical-align:middle';
        $tdNum = $td . ';text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap';
        $cell  = 'background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:3px';
    @endphp

    {{-- ── Una fila por compañía, una columna por deuda ──────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Lo que las compañías le deben al fondo</x-slot>
        <x-slot name="description">
            Son dos deudas distintas y se saldan de forma distinta; por eso van en columnas separadas.
            Pulse un monto para ver sus movimientos abajo.
        </x-slot>

        @if ($resumen->isEmpty())
            <div style="opacity:.7">Ninguna compañía le debe dinero al fondo.</div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="{{ $th }}">Compañía</th>
                            <th style="{{ $thNum }}">
                                Cheques en su mano
                                <span style="{{ $hint }};margin-left:auto">
                                    Un deudor le pagó con cheque. Se liquida por transferencia o se descuenta de un desembolso.
                                </span>
                            </th>
                            <th style="{{ $thNum }}">
                                Sobre-desembolsos
                                <span style="{{ $hint }};margin-left:auto">
                                    El fondo le transfirió de más. Solo se salda devolviendo el dinero.
                                </span>
                            </th>
                            <th style="{{ $thNum }}">Total</th>
                            <th style="{{ $thNum }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumen as $item)
                            @php
                                $id       = $item['company']->id;
                                $isActive = $this->companyId === $id;
                            @endphp
                            <tr wire:key="saldo-{{ $id }}" @if ($isActive) style="background:rgba(148,163,184,.12)" @endif>
                                <td style="{{ $td }};font-weight:600">{{ $item['company']->name }}</td>

                                <td style="{{ $tdNum }}">
                                    @if ($item['checks'] > 0.001)
                                        <button type="button" style="{{ $cell }}" wire:click="verMovimientos({{ $id }}, 'checks')">
                                            {{ $money($item['checks']) }}
                                        </button>
                                        @if ($item['checks_late_fee'] > 0.001)
                                            <div style="font-size:.72rem;opacity:.65">incluye {{ $money($item['checks_late_fee']) }} de mora</div>
                                        @endif
                                    @else
                                        <span style="opacity:.4">—</span>
                                    @endif
                                </td>

                                <td style="{{ $tdNum }}">
                                    @if ($item['overdisbursed'] > 0.001)
                                        <button type="button" style="{{ $cell }}" wire:click="verMovimientos({{ $id }}, 'overdisbursed')">
                                            {{ $money($item['overdisbursed']) }}
                                        </button>
                                    @else
                                        <span style="opacity:.4">—</span>
                                    @endif
                                </td>

                                <td style="{{ $tdNum }};font-weight:600">{{ $money($item['total']) }}</td>

                                <td style="{{ $tdNum }}">
                                    <x-filament::dropdown placement="bottom-end">
                                        <x-slot name="trigger">
                                            <x-filament::button size="xs" color="gray" icon="heroicon-m-chevron-down" icon-position="after">
                                                Saldar
                                            </x-filament::button>
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            @if ($item['checks'] > 0.001)
                                                <x-filament::dropdown.list.item
                                                    tag="a"
                                                    icon="heroicon-o-arrow-down-tray"
                                                    href="{{ $this->liquidacionUrl($id) }}"
                                                >
                                                    Cheques: registrar liquidación
                                                </x-filament::dropdown.list.item>

                                                <x-filament::dropdown.list.item
                                                    tag="a"
                                                    icon="heroicon-o-arrows-right-left"
                                                    href="{{ $this->desembolsoUrl($id) }}"
                                                >
                                                    Cheques: descontar de un desembolso
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if ($item['overdisbursed'] > 0.001)
                                                <x-filament::dropdown.list.item
                                                    icon="heroicon-o-arrow-uturn-left"
                                                    wire:click="mountAction('registrarDevolucion', { company: {{ $id }} })"
                                                >
                                                    Sobre-desembolso: registrar devolución
                                                </x-filament::dropdown.list.item>
                                            @endif
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    @if ($resumen->count() > 1)
                        <tfoot>
                            <tr>
                                <td style="{{ $td }};font-weight:600">Total · {{ $resumen->count() }} compañías</td>
                                <td style="{{ $tdNum }};font-weight:600">{{ $money($totalCheques) }}</td>
                                <td style="{{ $tdNum }};font-weight:600">{{ $money($totalSobreDesemb) }}</td>
                                <td style="{{ $tdNum }};font-weight:700">{{ $money(round($totalCheques + $totalSobreDesemb, 2)) }}</td>
                                <td style="{{ $td }}"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Historial: una pestaña por deuda ──────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Movimientos</x-slot>
        <x-slot name="description">
            @if ($selected)
                Solo {{ $selected->name }}.
            @else
                De todas las compañías.
            @endif
        </x-slot>

        @if ($selected)
            <x-slot name="headerEnd">
                <x-filament::button size="xs" color="gray" icon="heroicon-m-x-mark" wire:click="verMovimientos(null)">
                    Ver todas las compañías
                </x-filament::button>
            </x-slot>
        @endif

        <x-filament::tabs>
            <x-filament::tabs.item :active="$tab === 'checks'" wire:click="$set('tab', 'checks')">
                Cheques
            </x-filament::tabs.item>

            <x-filament::tabs.item :active="$tab === 'overdisbursed'" wire:click="$set('tab', 'overdisbursed')">
                Sobre-desembolsos
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div style="margin-top:16px">
            @if ($tab === 'checks')
                @livewire(
                    \App\Filament\Widgets\CompanyCreditMovementsWidget::class,
                    ['companyId' => $this->companyId],
                    key('credit-movements-' . ($this->companyId ?? 'all'))
                )
            @else
                {{ $this->table }}
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
