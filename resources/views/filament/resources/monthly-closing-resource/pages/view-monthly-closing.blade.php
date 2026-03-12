<x-filament-panels::page>

    @php 
        $record = $this->record->load(['distributions.fundMember', 'parametersSnapshot']);
        $executor = \App\Models\User::find($record->executed_by);
    @endphp

    {{-- Resumen general --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">Resumen del Cierre</h3>
            <p class="text-xs text-gray-500 mt-0.5">Periodo {{ $record->period }}</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr)">
            <div style="padding:16px 20px;border-right:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Comisiones</div>
                <div style="font-size:18px;font-weight:700;color:#111827">RD$ {{ number_format($record->total_commissions, 2, '.', ',') }}</div>
            </div>
            <div style="padding:16px 20px;border-right:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Fijo Pagado</div>
                <div style="font-size:18px;font-weight:700;color:#111827">RD$ {{ number_format($record->total_fixed, 2, '.', ',') }}</div>
            </div>
            <div style="padding:16px 20px;border-right:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Reserva</div>
                <div style="font-size:18px;font-weight:700;color:#111827">RD$ {{ number_format($record->reserve, 2, '.', ',') }}</div>
            </div>
            <div style="padding:16px 20px;border-right:1px solid #f3f4f6">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Naturaleza</div>
                <div style="font-size:18px;font-weight:700;color:#111827">RD$ {{ number_format($record->in_kind_payment, 2, '.', ',') }}</div>
            </div>
            <div style="padding:16px 20px">
                <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Para Capital</div>
                <div style="font-size:18px;font-weight:700;color:#ea580c">RD$ {{ number_format($record->available_for_capital, 2, '.', ',') }}</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        {{-- Distribución por miembro --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Distribución por Miembro</h3>
            </div>
            <table style="width:100%;font-size:13px">
                <thead>
                    <tr style="background:#f9fafb;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase">
                        <th style="padding:10px 20px;text-align:left">Miembro</th>
                        <th style="padding:10px 12px;text-align:right">Fijo</th>
                        <th style="padding:10px 12px;text-align:right">Variable</th>
                        <th style="padding:10px 20px;text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($record->distributions as $dist)
                    <tr style="border-top:1px solid #f3f4f6">
                        <td style="padding:12px 20px;font-weight:600;color:#111827">
                            {{ $dist->fundMember->name ?? '—' }}
                            <div style="font-size:11px;font-weight:400;color:#9ca3af">
                                {{ $dist->fundMember->type === 'capital' ? 'Capital' : 'Naturaleza' }}
                            </div>
                        </td>
                        <td style="padding:12px;text-align:right;color:#6b7280">
                            {{ $dist->fixed_amount > 0 ? 'RD$ ' . number_format($dist->fixed_amount, 2, '.', ',') : '—' }}
                        </td>
                        <td style="padding:12px;text-align:right;color:#6b7280">
                            RD$ {{ number_format($dist->proportional_amount, 2, '.', ',') }}
                        </td>
                        <td style="padding:12px 20px;text-align:right;font-weight:700;color:#111827">
                            RD$ {{ number_format($dist->total_amount, 2, '.', ',') }}
                        </td>
                    </tr>
                    @endforeach
                    <tr style="background:#f9fafb;border-top:1px solid #e5e7eb">
                        <td colspan="3" style="padding:12px 20px;font-weight:600;color:#6b7280">Reserva del fondo</td>
                        <td style="padding:12px 20px;text-align:right;font-weight:700">RD$ {{ number_format($record->reserve, 2, '.', ',') }}</td>
                    </tr>
                    <tr style="background:#f0fdf4;border-top:2px solid #dcfce7">
                        <td colspan="3" style="padding:12px 20px;font-weight:700;color:#111827">Total</td>
                        <td style="padding:12px 20px;text-align:right;font-weight:700;color:#16a34a">RD$ {{ number_format($record->total_commissions, 2, '.', ',') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Parámetros del cierre --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Parámetros al Momento del Cierre</h3>
                <p class="text-xs text-gray-500 mt-0.5">Valores congelados — no se ven afectados por cambios posteriores</p>
            </div>
            <div>
                @foreach($record->parametersSnapshot as $param)
                <div style="display:flex;justify-content:space-between;padding:12px 20px;border-bottom:1px solid #f3f4f6">
                    <span style="font-size:13px;color:#6b7280">
                        @switch($param->key)
                            @case('commission_pct') Comisión por factura @break
                            @case('fixed_return_pct') Rendimiento fijo mensual @break
                            @case('reserve_pct') Reserva del fondo @break
                            @case('in_kind_pct') % Aportante en naturaleza @break
                            @case('default_term_days') Plazo estándar @break
                            @default {{ $param->key }}
                        @endswitch
                    </span>
                    <span style="font-size:13px;font-weight:700;color:#ea580c">
                        {{ $param->key === 'default_term_days' ? number_format($param->value, 0) . ' días' : number_format($param->value, 2) . '%' }}
                    </span>
                </div>
                @endforeach
            </div>
            <div style="padding:12px 20px;background:#f9fafb;border-top:1px solid #e5e7eb">
                <div style="font-size:12px;color:#9ca3af">
                    Ejecutado por <strong style="color:#374151">{{ $executor->name ?? '—' }}</strong>
                    el {{ \Carbon\Carbon::parse($record->closed_at)->format('d M Y, H:i') }}
                </div>
            </div>
        </div>

    </div>

</x-filament-panels::page>