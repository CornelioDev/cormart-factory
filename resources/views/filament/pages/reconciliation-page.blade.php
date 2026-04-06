<x-filament-panels::page>

    <style>
        .fi-page > section { gap: 12px !important; padding-top: 12px !important; padding-bottom: 12px !important; }
        .fi-page > section > div > div.grid { gap: 0 !important; }

        .fd-card { background: #fff; }
        .dark .fd-card { background: rgb(31, 41, 55); }

        .fd-title { color: #111827; }
        .dark .fd-title { color: #f9fafb; }

        .fd-text { color: #111827; }
        .dark .fd-text { color: #f9fafb; }

        .fd-muted { color: #6b7280; }
        .dark .fd-muted { color: #9ca3af; }

        .fd-label { color: #4b5563; }
        .dark .fd-label { color: #d1d5db; }

        .fd-select {
            background: #fff; border-color: #d1d5db; color: #111827;
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
            padding-right: 32px;
        }
        .dark .fd-select {
            background-color: rgb(55, 65, 81); border-color: rgb(75, 85, 99); color: #f9fafb;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='%239ca3af'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E");
        }

        .fd-thead { background: #f9fafb; color: #6b7280; }
        .dark .fd-thead { background: rgb(55, 65, 81); color: #d1d5db; }

        .fd-tbody-divider > tr + tr { border-top: 1px solid #f3f4f6; }
        .dark .fd-tbody-divider > tr + tr { border-top-color: rgb(55, 65, 81); }

        .fd-row-alt { background: #f9fafb; }
        .dark .fd-row-alt { background: rgb(55, 65, 81); }

        .fd-section-heading { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; margin-top: 4px; }

        .rc-check-pass { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .dark .rc-check-pass { background: rgb(20, 83, 45); border-color: rgb(34, 117, 60); }

        .rc-check-fail { background: #fef2f2; border: 1px solid #fecaca; }
        .dark .rc-check-fail { background: rgb(127, 29, 29); border-color: rgb(153, 27, 27); }

        .rc-icon-pass { color: #15803d; }
        .dark .rc-icon-pass { color: #4ade80; }

        .rc-icon-fail { color: #dc2626; }
        .dark .rc-icon-fail { color: #f87171; }

        .rc-total-row { background: #f9fafb; font-weight: 700; }
        .dark .rc-total-row { background: rgb(55, 65, 81); }

        .rc-badge {
            display: inline-block; padding: 2px 8px; border-radius: 9999px;
            font-size: 11px; font-weight: 600;
        }
        .rc-badge-disbursement { background: #dbeafe; color: #1e40af; }
        .dark .rc-badge-disbursement { background: rgb(30, 58, 138); color: #93c5fd; }
        .rc-badge-collection { background: #dcfce7; color: #15803d; }
        .dark .rc-badge-collection { background: rgb(20, 83, 45); color: #4ade80; }
        .rc-badge-expense { background: #fef3c7; color: #92400e; }
        .dark .rc-badge-expense { background: rgb(67, 40, 14); color: #fbbf24; }
        .rc-badge-earning_distribution { background: #f3e8ff; color: #6b21a8; }
        .dark .rc-badge-earning_distribution { background: rgb(59, 7, 100); color: #d8b4fe; }
        .rc-badge-member_disbursement { background: #ffedd5; color: #9a3412; }
        .dark .rc-badge-member_disbursement { background: rgb(67, 40, 14); color: #fdba74; }
    </style>

    {{-- Selector de período --}}
    <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden" style="padding:12px 20px;margin-bottom:10px">
        <div style="display:flex;align-items:center;gap:16px">
            <div style="flex:1;max-width:320px">
                <label class="fd-label" style="display:block;font-size:14px;font-weight:500;margin-bottom:4px">Período</label>
                <select wire:model.live="selectedPeriod"
                    class="fd-select" style="width:100%;border-radius:8px;border:1px solid;padding:8px 12px;font-size:14px">
                    @foreach($this->getPeriodOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ══ VERIFICACIÓN DE LEDGERS ══ --}}
    <h3 class="fd-section-heading fd-muted">Verificación de Ledgers</h3>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:10px">
        @foreach($checks as $check)
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden {{ $check['pass'] ? 'rc-check-pass' : 'rc-check-fail' }}" style="padding:16px 20px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                    <span class="fd-title" style="font-size:14px;font-weight:600">{{ $check['name'] }}</span>
                    @if($check['pass'])
                        <svg xmlns="http://www.w3.org/2000/svg" class="rc-icon-pass" style="width:24px;height:24px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="rc-icon-fail" style="width:24px;height:24px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @endif
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px">
                    <div>
                        <div class="fd-muted" style="font-size:11px;font-weight:500">Esperado</div>
                        <div class="fd-text" style="font-size:14px;font-weight:600">{{ number_format($check['expected'], 2, '.', ',') }}</div>
                    </div>
                    <div>
                        <div class="fd-muted" style="font-size:11px;font-weight:500">Actual</div>
                        <div class="fd-text" style="font-size:14px;font-weight:600">{{ number_format($check['actual'], 2, '.', ',') }}</div>
                    </div>
                    <div>
                        <div class="fd-muted" style="font-size:11px;font-weight:500">Diferencia</div>
                        <div style="font-size:14px;font-weight:600" class="{{ $check['pass'] ? 'rc-icon-pass' : 'rc-icon-fail' }}">{{ number_format($check['diff'], 2, '.', ',') }}</div>
                    </div>
                </div>
                <div class="fd-muted" style="font-size:11px">{{ $check['detail'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ══ RESUMEN DEL PERÍODO ══ --}}
    <h3 class="fd-section-heading fd-muted">Resumen del Período</h3>
    <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden" style="margin-bottom:10px">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr class="fd-thead">
                    <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Concepto</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Entradas</th>
                    <th style="text-align:right;padding:10px 16px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Salidas</th>
                </tr>
            </thead>
            <tbody class="fd-tbody-divider">
                @foreach($summary as $row)
                    <tr class="{{ ($row['is_total'] ?? false) ? 'rc-total-row' : '' }}">
                        <td class="fd-text" style="padding:8px 16px">{{ $row['concept'] }}</td>
                        <td style="text-align:right;padding:8px 16px;{{ $row['inflow'] > 0 ? 'color:#15803d;font-weight:600' : '' }}">
                            {{ $row['inflow'] > 0 ? 'RD$ ' . number_format($row['inflow'], 2, '.', ',') : '—' }}
                        </td>
                        <td style="text-align:right;padding:8px 16px;{{ $row['outflow'] > 0 ? 'color:#dc2626;font-weight:600' : '' }}">
                            {{ $row['outflow'] > 0 ? 'RD$ ' . number_format($row['outflow'], 2, '.', ',') : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ══ DETALLE DE TRANSACCIONES ══ --}}
    <h3 class="fd-section-heading fd-muted">Detalle de Transacciones</h3>
    <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
        @if(count($transactions) === 0)
            <div class="fd-muted" style="padding:24px;text-align:center;font-size:13px">
                No hay transacciones confirmadas en este período.
            </div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr class="fd-thead">
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Fecha</th>
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Tipo</th>
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">N° Transacción</th>
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Entidad</th>
                            <th style="text-align:right;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Monto</th>
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Banco</th>
                            <th style="text-align:left;padding:10px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Financiamientos</th>
                        </tr>
                    </thead>
                    <tbody class="fd-tbody-divider">
                        @foreach($transactions as $index => $txn)
                            <tr class="{{ $index % 2 === 1 ? 'fd-row-alt' : '' }}">
                                <td class="fd-text" style="padding:8px 12px;white-space:nowrap">{{ $txn['date'] }}</td>
                                <td style="padding:8px 12px">
                                    <span class="rc-badge rc-badge-{{ $txn['type_raw'] }}">{{ $txn['type'] }}</span>
                                </td>
                                <td class="fd-text" style="padding:8px 12px;font-family:monospace;font-size:12px">{{ $txn['number'] }}</td>
                                <td class="fd-text" style="padding:8px 12px">{{ $txn['entity'] }}</td>
                                <td class="fd-text" style="text-align:right;padding:8px 12px;font-weight:600;white-space:nowrap">RD$ {{ number_format($txn['amount'], 2, '.', ',') }}</td>
                                <td class="fd-muted" style="padding:8px 12px">{{ $txn['bank'] }}</td>
                                <td class="fd-muted" style="padding:8px 12px;font-family:monospace;font-size:12px">{{ $txn['financings'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-filament-panels::page>
