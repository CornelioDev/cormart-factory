# Plan de Testing Manual — Cormart Factory v0.8.2

> Versión: v0.8.2 — Distribución basada en desembolsos

## Resumen de la sesión

| Campo     | Valor                                 |
| --------- | ------------------------------------- |
| Tester    | José Cornelio                         |
| Fecha     | 25/3/26                               |
| Instancia | `cormart_factory` / `cormart_staging` |
| Rol       | `super_admin`                         |
| Resultado | —                                     |

## Preparación

Verificar que las migraciones están al día:

```bash
php artisan migrate:status
```

Verificar que existen financiamientos desembolsados y cobrados en el mismo período:

```bash
php artisan tinker --execute="
\$period = '2026-03';
\$disbursed = \App\Models\Financing::where('issue_period', \$period)->whereNotIn('status', ['solicited','cancelled'])->count();
\$collected = \App\Models\Financing::where('issue_period', \$period)->where('status', 'collected')->count();
echo \"Período {\$period}: {\$disbursed} desembolsados, {\$collected} cobrados\";
"
```

Debe haber más desembolsados que cobrados para verificar el cambio de lógica.

Verificar que los tests automatizados pasan:

```bash
php artisan test
```

## 1. Cierre mensual — comisiones basadas en desembolsos

> Iniciar sesión como `super_admin`. Ir a **Cierre Mensual → Ejecutar Cierre**.

* [x] Seleccionar un período con financiamientos desembolsados que no todos estén cobrados (ej: marzo 2026)

  * *Notas: —*

* [x] Verificar que el campo **"Comisiones"** incluye las comisiones de **todos** los financiamientos desembolsados en el período, no solo los cobrados

  * *Notas: —*

* [ ] Verificar que el monto de comisiones es **mayor** que si solo se contaran los cobrados

  * *Notas: —*

  * [ ] Comparar contra la suma manual: `SELECT SUM(commission) FROM financings WHERE issue_period = '2026-03' AND status NOT IN ('solicited','cancelled')`

    * *Notas: Estas verificaciones técnicas deberían de hacerse en un test automatizado, no manual*

* [ ] Verificar que `verification_diff` es **0.00** en el preview del cierre

  * *Notas: —*

## 2. Cierre mensual — distribuciones por miembro

> Continuar en el preview del cierre del mismo período.

* [x] Verificar que la tabla de distribuciones muestra montos **positivos** para todos los miembros (si las comisiones superan el rendimiento fijo)

  * *Notas: —*

* [x] Verificar que el **Aportante Naturaleza** recibe monto positivo (no negativo)

  * *Notas: —*

* [x] Verificar que los montos fijos siguen siendo correctos: Capital A × 3%, Capital B × 3%

  * *Notas: —*

* [x] Verificar que la suma de distribuciones + reserva = total comisiones − gastos

  * *Notas: —*

## 3. Dashboard Financiero — KPIs de comisiones

> Ir a **Dashboard Financiero**. Seleccionar marzo 2026.

* [x] Verificar que **"Comisiones del Mes"** muestra el total de comisiones de financiamientos desembolsados en el período

  * *Notas: —*

* [x] Verificar que la descripción dice **"Comisiones generadas por desembolsos del período"**

  * *Notas: —*

* [x] Verificar que **"Ganancia Neta"** refleja el cálculo correcto: comisiones − gastos − rendimiento fijo

  * *Notas: —*

* [x] Verificar que todos los montos tienen formato `RD$` con **2 posiciones decimales**

  * *Notas: —*

## 4. Dashboard Financiero — KPI "Desembolsos del Período"

> Continuar en el Dashboard Financiero con marzo 2026 seleccionado.

* [x] Verificar que la sección **"Proyecciones"** muestra **"Desembolsos del Período"** (ya no "Proyección de Comisiones")

  * *Notas: La sección de proyecciones ya no sigue siendo necesaria*

* [x] Verificar que el monto mostrado es la suma de `transfer_amount` de los financiamientos desembolsados en el período

  * *Notas: —*

* [x] Verificar que la descripción muestra la cantidad correcta de financiamientos (ej: "6 financiamientos desembolsados")

  * *Notas: —*

## 5. Dashboard Financiero — % de Cobro

> Continuar en el Dashboard Financiero.

* [x] Verificar que **"% de Cobro"** muestra cobrados / total desembolsados del período (no global)

  * *Notas: —*

* [x] Verificar que el texto descriptivo muestra "X cobrados de Y activos" con valores correctos

  * *Notas: —*

## 6. Dashboard Financiero — Desglose por Miembro

> Continuar en el Dashboard Financiero con marzo 2026.

* [x] Verificar que la tabla **"Desglose por Miembro"** refleja las distribuciones con la nueva lógica

  * *Notas: Crear un gráfico al lado que tenga el histórico del ROI por mes vs el ROI del periodo actual*

* [x] Verificar que las columnas **Fijo** y **Variable** tienen valores coherentes

  * *Notas: —*

* [x] Verificar que **Total Comisiones** (fila verde) coincide con las comisiones de desembolsos del período

  * *Notas: —*

## 7. Dashboard Financiero — secciones no afectadas

> Verificar que las secciones que no debían cambiar siguen funcionando correctamente.

* [x] **Capital**: Capital Total, Capital en Calle, Capital Disponible — sin cambios

  * *Notas: —*

* [x] **Fondo**: Ganancias del Fondo, Saldo Estimado Banco, ROI Acumulado — sin cambios en lógica (valores pueden cambiar por nuevas distribuciones)

  * *Notas: —*

* [x] **Gráfico "Tendencia Financiera"** — se renderiza correctamente con datos de cierres previos

  * *Notas: Solamente muestra cierres anteriores pero el periodo en curso*

* [x] **Gráfico "Financiamientos por Mes"** — barras de desembolsados vs cobrados siguen correctas

  * *Notas: —*

* [x] **Gráfico "Flujo de Caja"** — se renderiza correctamente, datos basados en transacciones reales

  * *Notas: —*

## 8. Dashboard Financiero — período cerrado vs en curso

* [x] Seleccionar un período **cerrado** (ej: enero 2026) → badge muestra "Cierre ejecutado"

  * *Notas: —*

* [x] Verificar que los datos se cargan desde el `MonthlyClosing` persistido (valores históricos, no recalculados)

  * *Notas: —*

* [x] Seleccionar el período **en curso** (marzo 2026) → badge muestra "En curso"

  * *Notas: —*

* [x] Verificar que los datos se calculan en tiempo real desde `DistributionService`

  * *Notas: —*

## 9. Widgets — sin impacto

> Verificar que los widgets operativos no fueron afectados.

* [x] Ir a **Cuentas por Cobrar** → verificar que el stat "Cobrado este mes" sigue basándose en `collection_period` (financiamientos efectivamente cobrados)

  * *Notas: Estos widgets también deben mostrarse en una sección del Dashboard Financiero*

* [x] Ir al **Dashboard (Escritorio)** → verificar que el widget **Pipeline de Financiamientos** muestra datos correctos

  * *Notas: El conteo de financiamientos activos debe de ser excluir los solicitados y cobrados. También creo que hay un error en el conteo del % de cobro en el dashboard financiero, pero no estoy seguro.En*

## 10. Ejecución de cierre real (opcional)

> Solo si se desea probar la persistencia completa. **Precaución:** esto crea un cierre que no se puede deshacer.

* [ ] Ejecutar el cierre de marzo 2026

  * *Notas: —*

* [ ] Verificar que se creó el registro `MonthlyClosing` con `total_commissions` basado en desembolsos

  * *Notas: —*

* [ ] Verificar que se crearon las `ClosingDistribution` por miembro con montos correctos

  * *Notas: —*

* [ ] Verificar que se creó el `ClosingParametersSnapshot` con los 6 parámetros

  * *Notas: —*

* [ ] Verificar que el Dashboard muestra el badge "Cierre ejecutado" para marzo 2026

  * *Notas: —*

## Observaciones generales

* En la lista de financiamientos, elimina las acciones y ponlas dentro de la vista de talle de cada financiamiento.

* Se debe poder organizar la lista de financiamientos por estado.
