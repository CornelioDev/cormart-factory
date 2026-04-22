<x-filament-panels::page>

    <style>
        /* Reduce Filament page spacing */
        .fi-page > section { gap: 12px !important; padding-top: 12px !important; padding-bottom: 12px !important; }
        .fi-page > section > div > div.grid { gap: 0 !important; }

        .fd-card { background: #fff; }
        .dark .fd-card { background: rgb(31, 41, 55); }

        .fd-header { border-bottom: 1px solid #f3f4f6; }
        .dark .fd-header { border-bottom-color: rgb(55, 65, 81); }

        .fd-title { color: #111827; }
        .dark .fd-title { color: #f9fafb; }

        .fd-subtitle { color: #6b7280; }
        .dark .fd-subtitle { color: #9ca3af; }

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

        .fd-kpi {
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            border: 1px solid rgba(0,0,0,.05);
            padding: 12px 16px;
        }
        .dark .fd-kpi {
            background: rgb(31, 41, 55);
            border-color: rgba(255,255,255,.1);
        }

        .fd-kpi-value { font-size: 20px; font-weight: 700; line-height: 1.2; }
        .fd-kpi-label { font-size: 12px; font-weight: 500; margin-bottom: 4px; }
        .fd-kpi-desc { font-size: 11px; margin-top: 4px; }

        .fd-color-primary { color: #ea580c; }
        .dark .fd-color-primary { color: #fb923c; }

        .fd-color-success { color: #15803d; }
        .dark .fd-color-success { color: #4ade80; }

        .fd-color-danger { color: #dc2626; }
        .dark .fd-color-danger { color: #f87171; }

        .fd-color-warning { color: #d97706; }
        .dark .fd-color-warning { color: #fbbf24; }

        .fd-color-info { color: #2563eb; }
        .dark .fd-color-info { color: #60a5fa; }

        .fd-badge-closed { background: #dcfce7; color: #15803d; }
        .dark .fd-badge-closed { background: rgb(20, 83, 45); color: #4ade80; }

        .fd-badge-open { background: #fef3c7; color: #92400e; }
        .dark .fd-badge-open { background: rgb(67, 40, 14); color: #fbbf24; }

        .fd-row-alt { background: #f9fafb; }
        .dark .fd-row-alt { background: rgb(55, 65, 81); }

        .fd-row-green { background: #f0fdf4; }
        .dark .fd-row-green { background: rgb(20, 83, 45); }

        .fd-thead { background: #f9fafb; color: #6b7280; }
        .dark .fd-thead { background: rgb(55, 65, 81); color: #d1d5db; }

        .fd-tbody-divider > tr + tr { border-top: 1px solid #f3f4f6; }
        .dark .fd-tbody-divider > tr + tr { border-top-color: rgb(55, 65, 81); }

        .fd-badge-capital { background: #ffedd5; color: #9a3412; }
        .dark .fd-badge-capital { background: rgb(67, 40, 14); color: #fdba74; }

        .fd-badge-inkind { background: #f3f4f6; color: #4b5563; }
        .dark .fd-badge-inkind { background: rgb(55, 65, 81); color: #d1d5db; }

        .fd-section-title { font-size: 14px; font-weight: 600; }
        .fd-section-subtitle { font-size: 12px; margin-top: 2px; }

        .fd-section-heading { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; margin-top: 4px; }

        /* Responsive grids */
        .fd-grid-3 { display:grid; gap:10px; grid-template-columns:1fr; }
        .fd-grid-2 { display:grid; gap:12px; grid-template-columns:1fr; }
        @media (min-width:640px) {
            .fd-grid-3 { grid-template-columns:repeat(2,1fr); }
            .fd-grid-2 { grid-template-columns:repeat(2,1fr); }
        }
        @media (min-width:1024px) {
            .fd-grid-3 { grid-template-columns:repeat(3,1fr); }
        }
    </style>

    {{-- Selector de período --}}
    <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden" style="padding:12px 20px;margin-bottom:10px">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div style="flex:1;max-width:320px">
                <label class="fd-label" style="display:block;font-size:14px;font-weight:500;margin-bottom:4px">Período</label>
                <select wire:model.live="selectedPeriod"
                    class="fd-select" style="width:100%;border-radius:8px;border:1px solid;padding:8px 12px;font-size:14px">
                    @foreach($this->getPeriodOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($snapshot)
                <div style="padding-top:20px">
                    @if($snapshot['is_closed'])
                        <span class="fd-badge-closed" style="display:inline-flex;align-items:center;border-radius:9999px;padding:4px 12px;font-size:12px;font-weight:600">
                            Cierre ejecutado
                        </span>
                    @else
                        <span class="fd-badge-open" style="display:inline-flex;align-items:center;border-radius:9999px;padding:4px 12px;font-size:12px;font-weight:600">
                            En curso
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($snapshot)
        {{-- ══ CAPITAL ══ --}}
        <h3 class="fd-section-heading fd-muted">Capital</h3>
        <div class="fd-grid-3" style="margin-bottom:10px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Capital Total</div>
                <div class="fd-kpi-value fd-color-primary">RD$ {{ number_format($snapshot['capital_total'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Aportación activa del fondo</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Capital en Calle</div>
                <div class="fd-kpi-value fd-color-warning">RD$ {{ number_format($snapshot['capital_in_street'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Desembolsos pendientes de cobro</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Capital Disponible</div>
                <div class="fd-kpi-value fd-color-success">RD$ {{ number_format($snapshot['capital_available'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Listo para nuevos financiamientos</div>
            </div>
        </div>

        {{-- ══ FONDO ══ --}}
        <h3 class="fd-section-heading fd-muted">Fondo</h3>
        <div class="fd-grid-3" style="margin-bottom:6px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Ganancias del Fondo</div>
                <div class="fd-kpi-value {{ $snapshot['fund_balance'] >= 0 ? 'fd-color-success' : 'fd-color-danger' }}">RD$ {{ number_format($snapshot['fund_balance'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Comisiones − gastos − distribuciones</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Saldo Estimado Banco</div>
                <div class="fd-kpi-value fd-color-primary">RD$ {{ number_format($snapshot['estimated_bank'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Total esperado en cuenta bancaria</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Ganancias Acumuladas</div>
                @php $accNetProfit = (float) \App\Models\MonthlyClosing::sum('net_profit') + ($snapshot['is_closed'] ? 0 : $snapshot['net_profit']); @endphp
                <div class="fd-kpi-value {{ $accNetProfit >= 0 ? 'fd-color-success' : 'fd-color-danger' }}">RD$ {{ number_format($accNetProfit, 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Ganancia neta histórica acumulada</div>
            </div>
        </div>
        <div class="fd-grid-3" style="margin-bottom:10px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Ganancias por Pagar a Miembros</div>
                <div class="fd-kpi-value {{ $snapshot['pending_earnings'] > 0 ? 'fd-color-warning' : 'fd-color-success' }}">
                    RD$ {{ number_format($snapshot['pending_earnings'], 2, '.', ',') }}
                </div>
                <div class="fd-kpi-desc fd-muted">Distribuciones pendientes de desembolso</div>
            </div>
        </div>

        {{-- ══ COMISIONES DEL PERÍODO ══ --}}
        <h3 class="fd-section-heading fd-muted">Comisiones del Período</h3>
        <div class="fd-grid-3" style="margin-bottom:6px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Comisiones del Mes</div>
                <div class="fd-kpi-value fd-color-primary">RD$ {{ number_format($snapshot['total_commissions'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Comisiones generadas por desembolsos del período</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Gastos del Mes</div>
                <div class="fd-kpi-value fd-color-danger">RD$ {{ number_format($snapshot['total_expenses'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Gastos operativos del período</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Ganancia Neta</div>
                <div class="fd-kpi-value {{ $snapshot['net_profit'] >= 0 ? 'fd-color-success' : 'fd-color-danger' }}">RD$ {{ number_format($snapshot['net_profit'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Comisiones − gastos − rendimiento fijo</div>
            </div>
        </div>
        <div class="fd-grid-3" style="margin-bottom:10px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Reserva del Fondo</div>
                <div class="fd-kpi-value fd-color-warning">RD$ {{ number_format($snapshot['reserve'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Fondo de reserva del período</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Rendimiento Fijo Total</div>
                <div class="fd-kpi-value fd-color-info">RD$ {{ number_format($snapshot['total_fixed'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Rendimiento fijo de miembros de capital</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">Disponible para Capital</div>
                <div class="fd-kpi-value fd-color-success">RD$ {{ number_format($snapshot['available_for_capital'], 2, '.', ',') }}</div>
                <div class="fd-kpi-desc fd-muted">Post-reserva para distribución proporcional</div>
            </div>
        </div>

        {{-- ══ INDICADORES OPERATIVOS ══ --}}
        <h3 class="fd-section-heading fd-muted">Indicadores Operativos</h3>
        <div class="fd-grid-3" style="margin-bottom:10px">
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">% de Cobro Global</div>
                @php
                    $pct = $snapshot['collection_pct'];
                    $pctColor = $pct >= 75 ? 'fd-color-success' : ($pct >= 40 ? 'fd-color-warning' : 'fd-color-danger');
                @endphp
                <div class="fd-kpi-value {{ $pctColor }}">{{ $pct }}%</div>
                <div class="fd-kpi-desc fd-muted">{{ $snapshot['collected_count'] }} cobrados de {{ $snapshot['active_financings'] }} totales</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">ROI del Período</div>
                <div class="fd-kpi-value {{ $snapshot['roi_period'] >= 0 ? 'fd-color-success' : 'fd-color-danger' }}">{{ number_format($snapshot['roi_period'], 2) }}%</div>
                <div class="fd-kpi-desc fd-muted">Retorno sobre capital del mes</div>
            </div>
            <div class="fd-kpi">
                <div class="fd-kpi-label fd-muted">ROI Acumulado</div>
                <div class="fd-kpi-value fd-color-primary">{{ number_format($snapshot['roi_accumulated'], 2) }}%</div>
                <div class="fd-kpi-desc fd-muted">Retorno histórico acumulado</div>
            </div>
        </div>

        {{-- ══ CUENTAS POR COBRAR / PAGAR ══ --}}
        <div class="fd-grid-2" style="margin-bottom:10px">
            {{-- CxC --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">Cuentas por Cobrar</h3>
                    <p class="fd-subtitle fd-section-subtitle">Financiamientos pendientes de cobro</p>
                </div>
                <div style="padding:10px 16px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <div class="fd-kpi-label fd-muted">Total por Cobrar</div>
                        <div class="fd-kpi-value fd-color-primary" style="font-size:18px">RD$ {{ number_format($snapshot['cxc_total'] ?? 0, 2, '.', ',') }}</div>
                        <div class="fd-kpi-desc fd-muted">{{ $snapshot['cxc_count'] ?? 0 }} financiamiento{{ ($snapshot['cxc_count'] ?? 0) !== 1 ? 's' : '' }}</div>
                    </div>
                    <div>
                        <div class="fd-kpi-label fd-muted">Al Día</div>
                        <div class="fd-kpi-value fd-color-success" style="font-size:18px">RD$ {{ number_format($snapshot['cxc_al_dia'] ?? 0, 2, '.', ',') }}</div>
                        <div class="fd-kpi-desc fd-muted">{{ $snapshot['cxc_al_dia_count'] ?? 0 }} registro{{ ($snapshot['cxc_al_dia_count'] ?? 0) !== 1 ? 's' : '' }}</div>
                    </div>
                    <div>
                        <div class="fd-kpi-label fd-muted">Vencidos</div>
                        <div class="fd-kpi-value {{ ($snapshot['cxc_overdue_count'] ?? 0) > 0 ? 'fd-color-danger' : 'fd-muted' }}" style="font-size:18px">RD$ {{ number_format($snapshot['cxc_overdue'] ?? 0, 2, '.', ',') }}</div>
                        <div class="fd-kpi-desc fd-muted">{{ $snapshot['cxc_overdue_count'] ?? 0 }} registro{{ ($snapshot['cxc_overdue_count'] ?? 0) !== 1 ? 's' : '' }}</div>
                    </div>
                </div>
            </div>

            {{-- CxP --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">Cuentas por Pagar</h3>
                    <p class="fd-subtitle fd-section-subtitle">Financiamientos pendientes de desembolso</p>
                </div>
                <div style="padding:10px 16px 16px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <div class="fd-kpi-label fd-muted">Total por Desembolsar</div>
                        <div class="fd-kpi-value fd-color-primary" style="font-size:18px">RD$ {{ number_format($snapshot['cxp_total'] ?? 0, 2, '.', ',') }}</div>
                        <div class="fd-kpi-desc fd-muted">Neto después de comisiones</div>
                    </div>
                    <div>
                        <div class="fd-kpi-label fd-muted">Monto Solicitado</div>
                        <div class="fd-kpi-value fd-muted" style="font-size:18px">RD$ {{ number_format($snapshot['cxp_amount'] ?? 0, 2, '.', ',') }}</div>
                        <div class="fd-kpi-desc fd-muted">Monto bruto</div>
                    </div>
                    <div>
                        <div class="fd-kpi-label fd-muted">Solicitudes Pendientes</div>
                        <div class="fd-kpi-value {{ ($snapshot['cxp_count'] ?? 0) > 0 ? 'fd-color-warning' : 'fd-muted' }}" style="font-size:18px">{{ $snapshot['cxp_count'] ?? 0 }}</div>
                        <div class="fd-kpi-desc fd-muted">financiamiento{{ ($snapshot['cxp_count'] ?? 0) !== 1 ? 's' : '' }} solicitados</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ GRÁFICOS: Tendencia + Financiamientos por mes ══ --}}
        <div class="fd-grid-2" style="margin-bottom:10px">
            {{-- Tendencia Financiera --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">Tendencia Financiera</h3>
                    <p class="fd-subtitle fd-section-subtitle">Comisiones, gastos y ganancia neta</p>
                </div>
                <div style="padding:10px 16px 16px">
                    @if(count($chartData['labels'] ?? []) > 0)
                        <canvas id="financialChart" style="width:100%;height:300px"></canvas>
                    @else
                        <div style="text-align:center;padding:48px 0">
                            <p class="fd-muted" style="font-size:14px">No hay datos de cierres anteriores.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Financiamientos por Mes --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">Financiamientos por Mes</h3>
                    <p class="fd-subtitle fd-section-subtitle">Comparativo de desembolsos vs cobros por período cerrado</p>
                </div>
                <div style="padding:10px 16px 16px">
                    @if(count($financingsChartData['labels'] ?? []) > 0)
                        <canvas id="financingsBarChart" style="width:100%;height:300px"></canvas>
                    @else
                        <div style="text-align:center;padding:48px 0">
                            <p class="fd-muted" style="font-size:14px">No hay datos de cierres anteriores.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ CASHFLOW (ancho completo) ══ --}}
        <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden" style="margin-bottom:10px">
            <div class="fd-header" style="padding:10px 16px">
                <h3 class="fd-title fd-section-title">Flujo de Caja</h3>
                <p class="fd-subtitle fd-section-subtitle">Saldo bancario estimado día a día · Entradas vs salidas</p>
                <div style="display:flex;gap:16px;margin-top:6px;font-size:11px;flex-wrap:wrap">
                    <span class="fd-muted">Movido: <strong class="fd-text">RD$ {{ number_format($cashflowChartData['total_moved'] ?? 0, 2, '.', ',') }}</strong></span>
                    <span class="fd-muted">Entradas: <strong class="fd-color-success">RD$ {{ number_format($cashflowChartData['total_inflows'] ?? 0, 2, '.', ',') }}</strong></span>
                    <span class="fd-muted">Salidas: <strong class="fd-color-danger">RD$ {{ number_format($cashflowChartData['total_outflows'] ?? 0, 2, '.', ',') }}</strong></span>
                    <span class="fd-muted">Promedio: <strong class="fd-color-primary">RD$ {{ number_format($cashflowChartData['avg_balance'] ?? 0, 2, '.', ',') }}</strong></span>
                </div>
            </div>
            <div style="padding:10px 16px 16px">
                @if(count($cashflowChartData['labels'] ?? []) > 0)
                    <canvas id="cashflowChart" style="width:100%;height:280px"></canvas>
                @else
                    <div style="text-align:center;padding:48px 0">
                        <p class="fd-muted" style="font-size:14px">No hay transacciones en este período.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ══ Tabla de distribuciones + Gráfico ROI ══ --}}
        <div class="fd-grid-2" style="margin-bottom:10px">
            {{-- Tabla de distribuciones --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">Desglose por Miembro</h3>
                    <p class="fd-subtitle fd-section-subtitle">Distribución del período seleccionado</p>
                </div>

                @if(count($snapshot['distributions'] ?? []) > 0)
                    <table style="width:100%;font-size:14px">
                        <thead>
                            <tr class="fd-thead" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                                <th style="padding:12px 16px;text-align:left">Miembro</th>
                                <th style="padding:12px 12px;text-align:left">Tipo</th>
                                <th style="padding:12px 12px;text-align:right">Fijo</th>
                                <th style="padding:12px 12px;text-align:right">Variable</th>
                                <th style="padding:12px 16px;text-align:right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="fd-tbody-divider">
                            @foreach($snapshot['distributions'] as $dist)
                            <tr>
                                <td class="fd-text" style="padding:10px 16px;font-weight:600">{{ $dist['name'] }}</td>
                                <td style="padding:10px 12px">
                                    <span class="{{ $dist['type'] === 'capital' ? 'fd-badge-capital' : 'fd-badge-inkind' }}"
                                        style="display:inline-flex;align-items:center;border-radius:9999px;padding:2px 8px;font-size:12px;font-weight:600">
                                        {{ $dist['type'] === 'capital' ? 'Capital' : 'Naturaleza' }}
                                    </span>
                                </td>
                                <td class="fd-muted" style="padding:10px 12px;text-align:right">
                                    {{ $dist['fixed_amount'] > 0 ? 'RD$ ' . number_format($dist['fixed_amount'], 2, '.', ',') : '—' }}
                                </td>
                                <td class="fd-muted" style="padding:10px 12px;text-align:right">
                                    RD$ {{ number_format($dist['proportional_amount'], 2, '.', ',') }}
                                </td>
                                <td class="fd-text" style="padding:10px 16px;text-align:right;font-weight:700">
                                    RD$ {{ number_format($dist['total_amount'], 2, '.', ',') }}
                                </td>
                            </tr>
                            @endforeach
                            <tr class="fd-row-alt">
                                <td colspan="4" class="fd-muted" style="padding:10px 16px;font-weight:600">Reserva del fondo</td>
                                <td class="fd-text" style="padding:10px 16px;text-align:right;font-weight:700">RD$ {{ number_format($snapshot['reserve'], 2, '.', ',') }}</td>
                            </tr>
                            @php
                                $totalDistributed = collect($snapshot['distributions'])->sum('total_amount') + $snapshot['reserve'];
                            @endphp
                            <tr class="fd-row-green">
                                <td colspan="4" class="fd-text" style="padding:10px 16px;font-weight:700">Total Distribuido</td>
                                <td class="{{ $totalDistributed >= 0 ? 'fd-color-success' : 'fd-color-danger' }}" style="padding:10px 16px;text-align:right;font-weight:700">RD$ {{ number_format($totalDistributed, 2, '.', ',') }}</td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <div style="text-align:center;padding:48px 0">
                        <p class="fd-muted" style="font-size:14px">No hay distribuciones para este período.</p>
                    </div>
                @endif
            </div>

            {{-- Gráfico ROI histórico --}}
            <div class="fi-section rounded-xl fd-card shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="fd-header" style="padding:10px 16px">
                    <h3 class="fd-title fd-section-title">ROI por Período</h3>
                    <p class="fd-subtitle fd-section-subtitle">Retorno mensual sobre capital</p>
                </div>
                <div style="padding:10px 16px 16px">
                    @if(count($roiChartData['labels'] ?? []) > 0)
                        <canvas id="roiChart" style="width:100%;height:300px"></canvas>
                    @else
                        <div style="text-align:center;padding:48px 0">
                            <p class="fd-muted" style="font-size:14px">No hay datos de cierres anteriores.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        const chartData = $wire.chartData;
        const roiChartData = $wire.roiChartData;
        const financingsChartData = $wire.financingsChartData;
        const cashflowChartData = $wire.cashflowChartData;

        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        const textColor = isDark ? '#9ca3af' : '#6b7280';

        async function loadChartJs() {
            if (window.Chart) return;
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        await loadChartJs();

        const moneyFormat = (value) => 'RD$ ' + value.toLocaleString('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // ── Line chart: Tendencia Financiera ──
        const lineCtx = document.getElementById('financialChart');
        if (lineCtx && chartData.labels && chartData.labels.length > 0) {
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: chartData.datasets.map(ds => ({
                        ...ds,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { color: textColor, padding: 16, usePointStyle: true } },
                        tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + moneyFormat(ctx.parsed.y) } }
                    },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { color: textColor } },
                        y: { grid: { color: gridColor }, ticks: { color: textColor, callback: (v) => 'RD$ ' + v.toLocaleString('es-DO') } },
                    },
                },
            });
        }

        // ── Bar chart: Financiamientos por Mes ──
        const barCtx = document.getElementById('financingsBarChart');
        if (barCtx && financingsChartData.labels && financingsChartData.labels.length > 0) {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: financingsChartData.labels,
                    datasets: [
                        {
                            label: 'Desembolsados',
                            data: financingsChartData.disbursed,
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                            barPercentage: 0.8,
                            categoryPercentage: 0.5,
                        },
                        {
                            label: 'Cobrados',
                            data: financingsChartData.collected,
                            backgroundColor: '#22c55e',
                            borderRadius: 4,
                            barPercentage: 0.8,
                            categoryPercentage: 0.5,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: textColor, padding: 16, usePointStyle: true } },
                        tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + ctx.parsed.y } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor } },
                        y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 }, beginAtZero: true },
                    },
                },
            });
        }

        // ── ROI chart ──
        const roiCtx = document.getElementById('roiChart');
        if (roiCtx && roiChartData.labels && roiChartData.labels.length > 0) {
            new Chart(roiCtx, {
                type: 'bar',
                data: {
                    labels: roiChartData.labels,
                    datasets: [{
                        label: 'ROI mensual',
                        data: roiChartData.rois,
                        backgroundColor: roiChartData.rois.map(v => v >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)'),
                        borderRadius: 4,
                        barPercentage: 0.6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => 'ROI: ' + ctx.parsed.y.toFixed(2) + '%' } },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor } },
                        y: { grid: { color: gridColor }, ticks: { color: textColor, callback: (v) => v.toFixed(1) + '%' } },
                    },
                },
            });
        }

        // ── Cashflow chart ──
        const cashCtx = document.getElementById('cashflowChart');
        if (cashCtx && cashflowChartData.labels && cashflowChartData.labels.length > 0) {
            new Chart(cashCtx, {
                type: 'bar',
                data: {
                    labels: cashflowChartData.labels,
                    datasets: [
                        {
                            label: 'Saldo Banco',
                            data: cashflowChartData.balances,
                            type: 'line',
                            borderColor: '#ea580c',
                            backgroundColor: 'rgba(234, 88, 12, 0.08)',
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            tension: 0.3,
                            order: 0,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Entradas',
                            data: cashflowChartData.inflows,
                            backgroundColor: 'rgba(34, 197, 94, 0.7)',
                            borderRadius: 3,
                            barPercentage: 0.6,
                            categoryPercentage: 0.7,
                            order: 1,
                            yAxisID: 'y1',
                        },
                        {
                            label: 'Salidas',
                            data: cashflowChartData.outflows,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderRadius: 3,
                            barPercentage: 0.6,
                            categoryPercentage: 0.7,
                            order: 1,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { color: textColor, padding: 12, usePointStyle: true, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const val = ctx.parsed.y;
                                    if (ctx.dataset.label === 'Salidas') {
                                        return ctx.dataset.label + ': ' + moneyFormat(Math.abs(val));
                                    }
                                    return ctx.dataset.label + ': ' + moneyFormat(val);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 15 } },
                        y: {
                            position: 'left',
                            grid: { color: gridColor },
                            ticks: { color: textColor, font: { size: 10 }, callback: (v) => 'RD$ ' + (v / 1000).toFixed(0) + 'k' },
                            title: { display: true, text: 'Saldo', color: textColor, font: { size: 10 } },
                        },
                        y1: {
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { color: textColor, font: { size: 10 }, callback: (v) => 'RD$ ' + (Math.abs(v) / 1000).toFixed(0) + 'k' },
                            title: { display: true, text: 'Movimientos', color: textColor, font: { size: 10 } },
                        },
                    },
                },
            });
        }
    </script>
    @endscript

</x-filament-panels::page>
