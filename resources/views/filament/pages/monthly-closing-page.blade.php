<x-filament-panels::page>

    {{-- Selector de periodo --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 p-6 mb-6">
        <div class="flex items-end gap-4">
            <div class="flex-1 max-w-xs">
                <label class="block text-sm font-medium text-gray-700 mb-1">Periodo a cerrar</label>
                <select wire:model="selectedPeriod"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Selecciona un periodo</option>
                    @foreach($this->getPeriodOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1 hidden">Un periodo solo puede cerrarse una vez</p>
            </div>
            <button wire:click="calculate"
                class="rounded-lg bg-white border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Calcular distribución
            </button>
        </div>
    </div>

    @if($preview)
        @if($alreadyClosed)
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 mb-6 text-sm text-red-700">
            ⚠ Este periodo ya fue cerrado. Solo se muestra como referencia.
        </div>
        @else
        <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 mb-6 text-sm text-blue-700">
            ℹ <strong>{{ $preview['financings_count'] }} financiamientos cobrados</strong> en el periodo. Revisa la distribución antes de ejecutar el cierre.
        </div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            {{-- Cascada --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Cascada de Distribución</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $preview['period'] }}</p>
                </div>
                <div class="divide-y divide-gray-100">
                    <div class="flex items-baseline px-6 py-3">
                        <div class="flex-1 text-sm text-gray-900 font-semibold">Comisiones del mes</div>
                        <div class="text-sm font-bold">RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-8 py-3 bg-gray-50">
                        <div class="flex-1 text-sm text-gray-600">− Rendimiento fijo (3%)</div>
                        <div class="text-sm font-bold text-red-600">−RD$ {{ number_format($preview['total_fixed'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-6 py-3 bg-orange-50">
                        <div class="flex-1 text-sm text-gray-900 font-semibold">Ganancia neta</div>
                        <div class="text-sm font-bold text-orange-700">RD$ {{ number_format($preview['net_profit'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-8 py-3 bg-gray-50">
                        <div class="flex-1 text-sm text-gray-600">− Reserva del fondo (20%)</div>
                        <div class="text-sm font-bold text-red-600">−RD$ {{ number_format($preview['reserve'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-6 py-3 bg-orange-50">
                        <div class="flex-1 text-sm text-gray-900 font-semibold">Post-reserva</div>
                        <div class="text-sm font-bold text-orange-700">RD$ {{ number_format($preview['post_reserve'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-8 py-3 bg-gray-50">
                        <div class="flex-1 text-sm text-gray-600">− Aportante en naturaleza (50%)</div>
                        <div class="text-sm font-bold text-red-600">−RD$ {{ number_format($preview['in_kind_payment'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-6 py-3 bg-orange-50">
                        <div class="flex-1 text-sm text-gray-900 font-semibold">Disponible para capital</div>
                        <div class="text-sm font-bold text-orange-700">RD$ {{ number_format($preview['available_for_capital'], 2, '.', ',') }}</div>
                    </div>
                    <div class="flex items-baseline px-6 py-3 bg-green-50">
                        <div class="flex-1 text-sm text-gray-900 font-semibold">Verificación</div>
                        <div class="text-sm font-bold text-green-700">
                            RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}
                            {{ $preview['verification_diff'] == 0 ? '✓' : '⚠ Error: ' . $preview['verification_diff'] }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Desglose por miembro --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Desglose por Miembro</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $preview['period'] }}</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-6 py-3 text-left">Miembro</th>
                            <th class="px-4 py-3 text-left">Tipo</th>
                            <th class="px-4 py-3 text-right">Fijo</th>
                            <th class="px-4 py-3 text-right">Variable</th>
                            <th class="px-6 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($preview['distributions'] as $dist)
                        <tr>
                            <td class="px-6 py-3 font-semibold text-gray-900">{{ $dist['name'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $dist['type'] === 'capital' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $dist['type'] === 'capital' ? 'Capital' : 'Naturaleza' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">
                                {{ $dist['fixed_amount'] > 0 ? 'RD$ ' . number_format($dist['fixed_amount'], 2, '.', ',') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">
                                RD$ {{ number_format($dist['proportional_amount'], 2, '.', ',') }}
                            </td>
                            <td class="px-6 py-3 text-right font-bold text-gray-900">
                                RD$ {{ number_format($dist['total_amount'], 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                        <tr class="bg-gray-50">
                            <td colspan="4" class="px-6 py-3 font-semibold text-gray-500">Reserva del fondo</td>
                            <td class="px-6 py-3 text-right font-bold text-gray-900">RD$ {{ number_format($preview['reserve'], 2, '.', ',') }}</td>
                        </tr>
                        <tr class="bg-green-50">
                            <td colspan="4" class="px-6 py-3 font-bold text-gray-900">Total</td>
                            <td class="px-6 py-3 text-right font-bold text-green-700">RD$ {{ number_format($preview['total_commissions'], 2, '.', ',') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        {{-- Botón fuera del grid, siempre visible --}}
        @if(!$alreadyClosed)
            <div style="display:flex;justify-content:flex-end;margin-top:16px">
                <button wire:click="executeClosing"
                    wire:confirm="¿Confirmas ejecutar el cierre de {{ $preview['period'] }}? Esta acción no se puede deshacer."
                    style="background:#ea580c;color:white;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:500;border:none;cursor:pointer">
                    Ejecutar Cierre
                </button>
            </div>
        @endif
    @endif

</x-filament-panels::page>