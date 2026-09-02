<x-filament-panels::page>

    <style>
        .mc-card { background: #fff; }
        .dark .mc-card { background: rgb(31, 41, 55); }

        .mc-header { border-bottom: 1px solid #f3f4f6; }
        .dark .mc-header { border-bottom-color: rgb(55, 65, 81); }

        .mc-title { color: #111827; }
        .dark .mc-title { color: #f9fafb; }

        .mc-subtitle { color: #6b7280; }
        .dark .mc-subtitle { color: #9ca3af; }

        .mc-text { color: #111827; }
        .dark .mc-text { color: #f9fafb; }

        .mc-muted { color: #6b7280; }
        .dark .mc-muted { color: #9ca3af; }

        .mc-label { color: #4b5563; }
        .dark .mc-label { color: #d1d5db; }

        .mc-select {
            background: #fff; border-color: #d1d5db; color: #111827;
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
            padding-right: 32px;
        }
        .dark .mc-select {
            background-color: rgb(55, 65, 81); border-color: rgb(75, 85, 99); color: #f9fafb;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='%239ca3af'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px;
        }

        .mc-btn-secondary { background: #fff; border: 1px solid #d1d5db; color: #374151; }
        .mc-btn-secondary:hover { background: #f9fafb; }
        .dark .mc-btn-secondary { background: rgb(55, 65, 81); border-color: rgb(75, 85, 99); color: #f9fafb; }
        .dark .mc-btn-secondary:hover { background: rgb(75, 85, 99); }

        .mc-divider { border-color: #f3f4f6; }
        .dark .mc-divider { border-color: rgb(55, 65, 81); }

        .mc-row-alt { background: #f9fafb; }
        .dark .mc-row-alt { background: rgb(55, 65, 81); }

        .mc-row-accent { background: #fff7ed; }
        .dark .mc-row-accent { background: rgb(67, 40, 14); }

        .mc-row-green { background: #f0fdf4; }
        .dark .mc-row-green { background: rgb(20, 83, 45); }

        .mc-accent { color: #c2410c; }
        .dark .mc-accent { color: #fb923c; }

        .mc-red { color: #dc2626; }
        .dark .mc-red { color: #f87171; }

        .mc-green { color: #15803d; }
        .dark .mc-green { color: #4ade80; }

        .mc-alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
        .dark .mc-alert-info { background: rgb(30, 58, 95); border-color: rgb(37, 99, 235); color: #93c5fd; }

        .mc-alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .dark .mc-alert-danger { background: rgb(69, 26, 26); border-color: rgb(153, 27, 27); color: #fca5a5; }

        .mc-thead { background: #f9fafb; color: #6b7280; }
        .dark .mc-thead { background: rgb(55, 65, 81); color: #d1d5db; }

        .mc-tbody-divider > tr + tr { border-top: 1px solid #f3f4f6; }
        .dark .mc-tbody-divider > tr + tr { border-top-color: rgb(55, 65, 81); }

        .mc-badge-capital { background: #ffedd5; color: #9a3412; }
        .dark .mc-badge-capital { background: rgb(67, 40, 14); color: #fdba74; }

        .mc-badge-inkind { background: #f3f4f6; color: #4b5563; }
        .dark .mc-badge-inkind { background: rgb(55, 65, 81); color: #d1d5db; }

        .mc-btn-primary { background: #ea580c; color: white; border: none; cursor: pointer; }
        .mc-btn-primary:hover { background: #c2410c; }
        .dark .mc-btn-primary { background: #ea580c; }
        .dark .mc-btn-primary:hover { background: #f97316; }
    </style>

    {{-- Selector de periodo --}}
    <div class="fi-section rounded-xl mc-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden mb-6" style="padding:24px">
        <div style="display:flex;align-items:flex-end;gap:16px">
            <div style="flex:1;max-width:320px">
                <label class="mc-label" style="display:block;font-size:14px;font-weight:500;margin-bottom:4px">Periodo a cerrar</label>
                <select wire:model="selectedPeriod"
                    class="mc-select" style="width:100%;border-radius:8px;border:1px solid;padding:8px 12px;font-size:14px">
                    <option value="">Selecciona un periodo</option>
                    @foreach($this->getPeriodOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="calculate"
                class="mc-btn-secondary" style="border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500">
                Calcular distribución
            </button>
        </div>
    </div>

    @php($preview = $this->preview)

    @if($preview)
        @if($alreadyClosed)
        <div class="mc-alert-danger" style="border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:14px">
            ⚠ Este periodo ya fue cerrado. Solo se muestra como referencia.
        </div>
        @else
        <div class="mc-alert-info" style="border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:14px">
            ℹ <strong>{{ $preview['financings_count'] }} financiamientos cobrados</strong> en el periodo. Revisa la distribución antes de ejecutar el cierre.
        </div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            {{-- Cascada --}}
            <div class="fi-section rounded-xl mc-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="mc-header" style="padding:16px 24px">
                    <h3 class="mc-title" style="font-size:14px;font-weight:600">Cascada de Distribución</h3>
                    <p class="mc-subtitle" style="font-size:12px;margin-top:2px">{{ $preview['period'] }}</p>
                </div>
                <div>
                    <div class="mc-divider" style="display:flex;align-items:baseline;padding:12px 24px;border-bottom:1px solid">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Comisiones del mes</div>
                        <div class="mc-text" style="font-size:14px;font-weight:700">RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-alt mc-divider" style="display:flex;align-items:baseline;padding:12px 32px;border-bottom:1px solid">
                        <div class="mc-muted" style="flex:1;font-size:14px">− Gastos del período</div>
                        <div class="mc-red" style="font-size:14px;font-weight:700">−RD$ {{ number_format($preview['total_expenses'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-accent mc-divider" style="display:flex;align-items:baseline;padding:12px 24px;border-bottom:1px solid">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Base real de ganancias</div>
                        <div class="mc-accent" style="font-size:14px;font-weight:700">RD$ {{ number_format($preview['base_earnings'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-alt mc-divider" style="display:flex;align-items:baseline;padding:12px 32px;border-bottom:1px solid">
                        <div class="mc-muted" style="flex:1;font-size:14px">− Rendimiento fijo ({{ number_format((float) $preview['parameters']['fixed_return_pct'], 2) }}%)</div>
                        <div class="mc-red" style="font-size:14px;font-weight:700">−RD$ {{ number_format($preview['total_fixed'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-accent mc-divider" style="display:flex;align-items:baseline;padding:12px 24px;border-bottom:1px solid">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Ganancia neta</div>
                        <div class="mc-accent" style="font-size:14px;font-weight:700">RD$ {{ number_format($preview['net_profit'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-alt mc-divider" style="display:flex;align-items:baseline;padding:12px 32px;border-bottom:1px solid">
                        <div class="mc-muted" style="flex:1;font-size:14px">− Reserva del fondo ({{ number_format((float) $preview['parameters']['reserve_pct'], 2) }}%)</div>
                        <div class="mc-red" style="font-size:14px;font-weight:700">−RD$ {{ number_format($preview['reserve'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-accent mc-divider" style="display:flex;align-items:baseline;padding:12px 24px;border-bottom:1px solid">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Post-reserva</div>
                        <div class="mc-accent" style="font-size:14px;font-weight:700">RD$ {{ number_format($preview['post_reserve'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-alt mc-divider" style="display:flex;align-items:baseline;padding:12px 32px;border-bottom:1px solid">
                        <div class="mc-muted" style="flex:1;font-size:14px">− Aportante en naturaleza ({{ number_format((float) $preview['parameters']['in_kind_pct'], 2) }}%)</div>
                        <div class="mc-red" style="font-size:14px;font-weight:700">−RD$ {{ number_format($preview['in_kind_payment'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-accent mc-divider" style="display:flex;align-items:baseline;padding:12px 24px;border-bottom:1px solid">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Disponible para capital</div>
                        <div class="mc-accent" style="font-size:14px;font-weight:700">RD$ {{ number_format($preview['available_for_capital'], 2, '.', ',') }}</div>
                    </div>
                    <div class="mc-row-green" style="display:flex;align-items:baseline;padding:12px 24px">
                        <div class="mc-text" style="flex:1;font-size:14px;font-weight:600">Verificación</div>
                        <div class="mc-green" style="font-size:14px;font-weight:700">
                            RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}
                            {{ $preview['verification_diff'] == 0 ? '✓' : '⚠ Error: ' . $preview['verification_diff'] }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Desglose por miembro --}}
            <div class="fi-section rounded-xl mc-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="mc-header" style="padding:16px 24px">
                    <h3 class="mc-title" style="font-size:14px;font-weight:600">Desglose por Miembro</h3>
                    <p class="mc-subtitle" style="font-size:12px;margin-top:2px">{{ $preview['period'] }}</p>
                </div>
                <table style="width:100%;font-size:14px">
                    <thead>
                        <tr class="mc-thead" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                            <th style="padding:12px 24px;text-align:left">Miembro</th>
                            <th style="padding:12px 16px;text-align:left">Tipo</th>
                            <th style="padding:12px 16px;text-align:right">Fijo</th>
                            <th style="padding:12px 16px;text-align:right">Variable</th>
                            <th style="padding:12px 24px;text-align:right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="mc-tbody-divider">
                        @foreach($preview['distributions'] as $dist)
                        <tr>
                            <td class="mc-text" style="padding:12px 24px;font-weight:600">{{ $dist['name'] }}</td>
                            <td style="padding:12px 16px">
                                <span class="{{ $dist['type'] === 'capital' ? 'mc-badge-capital' : 'mc-badge-inkind' }}"
                                    style="display:inline-flex;align-items:center;border-radius:9999px;padding:2px 8px;font-size:12px;font-weight:600">
                                    {{ $dist['type'] === 'capital' ? 'Capital' : 'Naturaleza' }}
                                </span>
                            </td>
                            <td class="mc-muted" style="padding:12px 16px;text-align:right">
                                {{ $dist['fixed_amount'] > 0 ? 'RD$ ' . number_format($dist['fixed_amount'], 2, '.', ',') : '—' }}
                            </td>
                            <td class="mc-muted" style="padding:12px 16px;text-align:right">
                                RD$ {{ number_format($dist['proportional_amount'], 2, '.', ',') }}
                            </td>
                            <td class="mc-text" style="padding:12px 24px;text-align:right;font-weight:700">
                                RD$ {{ number_format($dist['total_amount'], 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                        <tr class="mc-row-alt">
                            <td colspan="4" class="mc-muted" style="padding:12px 24px;font-weight:600">Reserva del fondo</td>
                            <td class="mc-text" style="padding:12px 24px;text-align:right;font-weight:700">RD$ {{ number_format($preview['reserve'], 2, '.', ',') }}</td>
                        </tr>
                        <tr class="mc-row-green">
                            <td colspan="4" class="mc-text" style="padding:12px 24px;font-weight:700">Total</td>
                            <td class="mc-green" style="padding:12px 24px;text-align:right;font-weight:700">RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Botón fuera del grid --}}
        @if(!$alreadyClosed)
            <div style="display:flex;justify-content:flex-end;margin-top:16px">
                <button wire:click="executeClosing"
                    wire:confirm="¿Confirmas ejecutar el cierre de {{ $preview['period'] }}? Esta acción no se puede deshacer."
                    class="mc-btn-primary" style="padding:10px 24px;border-radius:6px;font-size:14px;font-weight:500">
                    Ejecutar Cierre
                </button>
            </div>
        @endif
    @endif

</x-filament-panels::page>
