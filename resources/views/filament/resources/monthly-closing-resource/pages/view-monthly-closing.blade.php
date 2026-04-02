<x-filament-panels::page>

    @php
        $record = $this->record->load(['distributions.fundMember', 'parametersSnapshot']);
        $executor = \App\Models\User::find($record->executed_by);
    @endphp

    <style>
        .closing-card { background: #fff; }
        .dark .closing-card { background: rgb(31, 41, 55); }

        .closing-header { border-bottom: 1px solid #f3f4f6; }
        .dark .closing-header { border-bottom-color: rgb(55, 65, 81); }

        .closing-title { color: #111827; }
        .dark .closing-title { color: #f9fafb; }

        .closing-subtitle { color: #6b7280; }
        .dark .closing-subtitle { color: #9ca3af; }

        .closing-label { color: #9ca3af; }
        .dark .closing-label { color: #9ca3af; }

        .closing-value { color: #111827; }
        .dark .closing-value { color: #f9fafb; }

        .closing-muted { color: #6b7280; }
        .dark .closing-muted { color: #9ca3af; }

        .closing-thead { background: #f9fafb; color: #6b7280; }
        .dark .closing-thead { background: rgb(55, 65, 81); color: #d1d5db; }

        .closing-border { border-top: 1px solid #f3f4f6; }
        .dark .closing-border { border-top-color: rgb(55, 65, 81); }

        .closing-divider { border-bottom: 1px solid #f3f4f6; }
        .dark .closing-divider { border-bottom-color: rgb(55, 65, 81); }

        .closing-grid-border { border-right: 1px solid #f3f4f6; }
        .dark .closing-grid-border { border-right-color: rgb(55, 65, 81); }

        .closing-reserve-row { background: #f9fafb; border-top: 1px solid #e5e7eb; }
        .dark .closing-reserve-row { background: rgb(55, 65, 81); border-top-color: rgb(75, 85, 99); }

        .closing-total-row { background: #f0fdf4; border-top: 2px solid #dcfce7; }
        .dark .closing-total-row { background: rgb(20, 83, 45); border-top-color: rgb(22, 101, 52); }

        .closing-total-value { color: #16a34a; }
        .dark .closing-total-value { color: #4ade80; }

        .closing-accent { color: #ea580c; }
        .dark .closing-accent { color: #fb923c; }

        .closing-footer { background: #f9fafb; border-top: 1px solid #e5e7eb; }
        .dark .closing-footer { background: rgb(55, 65, 81); border-top-color: rgb(75, 85, 99); }

        .closing-footer-name { color: #374151; }
        .dark .closing-footer-name { color: #f9fafb; }
    </style>

    {{-- Resumen general --}}
    <div class="fi-section rounded-xl closing-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden mb-6">
        <div class="px-6 py-4 closing-header">
            <h3 class="text-sm font-semibold closing-title">Resumen del Cierre</h3>
            <p class="text-xs closing-subtitle mt-0.5">Periodo {{ $record->period }}</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr)">
            <div class="closing-grid-border" style="padding:16px 20px">
                <div class="closing-label" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Comisiones</div>
                <div class="closing-value" style="font-size:18px;font-weight:700">RD$ {{ number_format($record->total_commissions, 2, '.', ',') }}</div>
            </div>
            <div class="closing-grid-border" style="padding:16px 20px">
                <div class="closing-label" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Fijo Pagado</div>
                <div class="closing-value" style="font-size:18px;font-weight:700">RD$ {{ number_format($record->total_fixed, 2, '.', ',') }}</div>
            </div>
            <div class="closing-grid-border" style="padding:16px 20px">
                <div class="closing-label" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Reserva</div>
                <div class="closing-value" style="font-size:18px;font-weight:700">RD$ {{ number_format($record->reserve, 2, '.', ',') }}</div>
            </div>
            <div class="closing-grid-border" style="padding:16px 20px">
                <div class="closing-label" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Naturaleza</div>
                <div class="closing-value" style="font-size:18px;font-weight:700">RD$ {{ number_format($record->in_kind_payment, 2, '.', ',') }}</div>
            </div>
            <div style="padding:16px 20px">
                <div class="closing-label" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Para Capital</div>
                <div class="closing-accent" style="font-size:18px;font-weight:700">RD$ {{ number_format($record->available_for_capital, 2, '.', ',') }}</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        {{-- Distribución por miembro --}}
        <div class="fi-section rounded-xl closing-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 closing-header">
                <h3 class="text-sm font-semibold closing-title">Distribución por Miembro</h3>
            </div>
            <table style="width:100%;font-size:13px">
                <thead>
                    <tr class="closing-thead" style="font-size:11px;font-weight:600;text-transform:uppercase">
                        <th style="padding:10px 20px;text-align:left">Miembro</th>
                        <th style="padding:10px 12px;text-align:right">Fijo</th>
                        <th style="padding:10px 12px;text-align:right">Variable</th>
                        <th style="padding:10px 20px;text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->distributions as $dist)
                    <tr class="closing-border">
                        <td class="closing-value" style="padding:12px 20px;font-weight:600">
                            {{ $dist->fundMember->name ?? '—' }}
                            <div class="closing-label" style="font-size:11px;font-weight:400">
                                {{ $dist->fundMember->type === 'capital' ? 'Capital' : 'Naturaleza' }}
                            </div>
                        </td>
                        <td class="closing-muted" style="padding:12px;text-align:right">
                            {{ $dist->fixed_amount > 0 ? 'RD$ ' . number_format($dist->fixed_amount, 2, '.', ',') : '—' }}
                        </td>
                        <td class="closing-muted" style="padding:12px;text-align:right">
                            RD$ {{ number_format($dist->proportional_amount, 2, '.', ',') }}
                        </td>
                        <td class="closing-value" style="padding:12px 20px;text-align:right;font-weight:700">
                            RD$ {{ number_format($dist->total_amount, 2, '.', ',') }}
                        </td>
                    </tr>
                    @endforeach
                    <tr class="closing-reserve-row">
                        <td colspan="3" class="closing-muted" style="padding:12px 20px;font-weight:600">Reserva del fondo</td>
                        <td class="closing-value" style="padding:12px 20px;text-align:right;font-weight:700">RD$ {{ number_format($record->reserve, 2, '.', ',') }}</td>
                    </tr>
                    <tr class="closing-total-row">
                        <td colspan="3" class="closing-value" style="padding:12px 20px;font-weight:700">Total</td>
                        <td class="closing-total-value" style="padding:12px 20px;text-align:right;font-weight:700">RD$ {{ number_format($record->total_commissions, 2, '.', ',') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Parámetros del cierre --}}
        <div class="fi-section rounded-xl closing-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 closing-header">
                <h3 class="text-sm font-semibold closing-title">Parámetros al Momento del Cierre</h3>
                <p class="text-xs closing-subtitle mt-0.5">Valores congelados — no se ven afectados por cambios posteriores</p>
            </div>
            <div>
                @foreach($record->parametersSnapshot as $param)
                <div class="closing-divider" style="display:flex;justify-content:space-between;padding:12px 20px">
                    <span class="closing-muted" style="font-size:13px">
                        @switch($param->key)
                            @case('commission_pct') Comisión por factura @break
                            @case('fixed_return_pct') Rendimiento fijo mensual @break
                            @case('reserve_pct') Reserva del fondo @break
                            @case('in_kind_pct') % Aportante en naturaleza @break
                            @case('default_term_days') Plazo estándar @break
                            @case('tax_pct') Impuesto sobre desembolso @break
                            @case('late_fee_pct') Mora por atraso @break
                            @default {{ $param->key }}
                        @endswitch
                    </span>
                    <span class="closing-accent" style="font-size:13px;font-weight:700">
                        {{ $param->key === 'default_term_days' ? number_format($param->value, 0) . ' días' : number_format($param->value, 2) . '%' }}
                    </span>
                </div>
                @endforeach
            </div>
            <div class="closing-footer" style="padding:12px 20px">
                <div class="closing-label" style="font-size:12px">
                    Ejecutado por <strong class="closing-footer-name">{{ $executor->name ?? '—' }}</strong>
                    el {{ \Carbon\Carbon::parse($record->closed_at)->format('d M Y, H:i') }}
                </div>
            </div>
        </div>

    </div>

</x-filament-panels::page>
