# TODO — Cormart Factory

> Checklist de avance hacia v1.0.0. Actualizar con `[x]` al completar cada ítem tras recibir visto bueno.
> Ver [ROADMAP.md](ROADMAP.md) para detalles de implementación de cada versión.

---

## v0.5.0 — Gastos e Impuesto de Desembolso ✅

### Base de datos
- [x] Migración: agregar `expense` al enum `transactions.type`
- [x] Migración: agregar `total_expenses decimal(15,2) default 0` a `monthly_closings`
- [x] Seeder/parámetro: agregar `tax_pct = 0.15` a la tabla `parameters`
- [x] Migración: crear tabla `suppliers` con proveedor DGII por defecto
- [x] Migración: agregar `supplier_id` (nullable FK) a `transactions`
- [x] Migración: agregar constraint UNIQUE a `transactions.transaction_number`

### Parámetros
- [x] `ParametrosPage`: agregar campo `tax_pct` con helper text

### Services
- [x] `TransactionService`: auto-generar expense en disbursements a compañía
- [x] `TransactionService`: nuevo método `createExpense()`
- [x] `DistributionService`: descontar gastos del período en `calculate()`
- [x] `DistributionService`: incluir `total_expenses` en `executeClosing()`

### Modelos
- [x] `MonthlyClosing`: agregar `total_expenses` a `$fillable` y `$casts`
- [x] `Supplier`: nuevo modelo con relación a `Transaction`
- [x] `Transaction`: relación `supplier()` y `supplier_id` en fillable

### Filament UI
- [x] `TransactionResource`: tipo `expense` (super_admin, sin company/financings, requiere notes)
- [x] `TransactionResource`: selector de proveedor con creación inline
- [x] `TransactionResource`: filtro por tipo y proveedor en la tabla
- [x] `MonthlyClosingPage`: línea de gastos en el preview del cierre
- [x] `CapitalSummaryWidget`: stat de gastos del mes

### Cierre de versión
- [x] Testing manual completado (super_admin)
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.5.0` + `git push --tags`

---

## v0.6.0 — Cuenta de Ganancias y Desembolsos a Miembros ✅

### Base de datos
- [x] Migración: agregar `member_disbursement` al enum `transactions.type`
- [x] Migración: agregar `fund_member_id` (nullable FK → fund_members) a `transactions`

### Modelos
- [x] `FundMember`: método `earningsBalance(): float`
- [x] `FundMember`: método `totalEarned(): float`
- [x] `FundMember`: método `earningsDisbursements()` (HasMany)
- [x] `Transaction`: relación `fundMember()` (BelongsTo)

### Services
- [x] `TransactionService`: método `createMemberDisbursement()` con validación de balance
- [x] `TransactionService`: auto-generar expense en `member_disbursement` (mismo `tax_pct`)

### Filament UI
- [x] `FundMemberResource`: columnas `earnings_balance` y `total_earned` en tabla
- [x] `FundMemberResource`: action "Desembolsar Ganancias" (super_admin, modal con monto/banco/referencia)

### Cierre de versión
- [x] Tests actualizados para todos los cambios de v0.6.0
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.6.0` + `git push --tags`

---

## v0.7.0 — Perfil de Miembro (Estado de Cuenta) ✅

### Filament UI
- [x] `ClosingDistributionsRelationManager`: tabla de historial de distribuciones en ViewFundMember
- [x] `MemberAccountPage`: página de redirección "Estado de Cuenta" para rol `member`
- [x] `FundMemberPolicy`: acceso de `member` restringido a su propio registro
- [x] `RolePermissionsSeeder`: permisos `view_fund::member` y `page_MemberAccountPage` para rol `member`
- [x] `MonthlyClosingResource` ViewPage: tabla de distribuciones por miembro en el cierre

### Cierre de versión
- [x] Tests actualizados para todos los cambios de v0.7.0
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.7.0` + `git push --tags`

---

## v0.8.0 — Dashboard Financiero ✅

### Filament UI
- [x] `FinancialDashboardPage`: página custom con KPIs por sección (Capital, Fondo, Comisiones, Proyecciones)
- [x] Gráfico de tendencia financiera (line chart: comisiones, gastos, ganancia neta)
- [x] Gráfico comparativo de financiamientos por mes (bar chart: desembolsados vs cobrados)
- [x] Gráfico de participación por compañía (doughnut chart)
- [x] Tabla de desglose por miembro (distribuciones del período)
- [x] Selector de período con badge de estado (cerrado/en curso)
- [x] Eliminar `CapitalSummaryWidget` y `BankBalanceWidget` (reemplazados por el dashboard)

### Seeders
- [x] `MonthlyClosingSeeder`: datos de cierres para enero y febrero 2026
- [x] `FinancingAndTransactionSeeder`: datos multi-mes con gastos operativos
- [x] `FundMemberSeeder`: agregar miembro tipo naturaleza

### Cierre de versión
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.8.0` + `git push --tags`

---

## v0.8.2 — Distribución basada en desembolsos y mejoras del Dashboard ✅

### Lógica de negocio
- [x] Distribución basada en `issue_period` (desembolsos) en lugar de `collection_period` (cobros)
- [x] `DistributionService`: query usa `whereNotIn('status', ['solicited', 'cancelled'])` + `issue_period`

### Dashboard Financiero
- [x] Eliminar sección "Proyecciones" (ya no necesaria)
- [x] Nueva sección "Indicadores Operativos": % de Cobro global, ROI del período, ROI acumulado
- [x] Gráfico "Tendencia Financiera" incluye período en curso
- [x] Nuevo gráfico "ROI por Período" (barras con colores positivo/negativo)
- [x] Nuevas secciones de Cuentas por Cobrar y Cuentas por Pagar integradas
- [x] "Ganancias Acumuladas" reemplaza ROI duplicado en sección Fondo

### Financiamientos
- [x] Acción "Cancelar" movida de la tabla a la vista detalle (con confirmación)
- [x] Tabla simplificada — solo acción "Ver"

### Infraestructura de tests
- [x] Migraciones compatibles con SQLite (guard `DB::getDriverName()`)
- [x] 74 tests pasando

### Cierre de versión
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.8.2` + `git push --tags`

---

## v0.9.0 — Calidad y Preparación para Producción ✅

### Bug fixes
- [x] `RolePolicy`: reemplazar placeholders de template por strings de permiso reales

### Tests
- [x] `TransactionServiceTest`: tests para tipo `expense` y auto-generación de impuesto
- [x] `DistributionServiceTest`: tests con gastos en la fórmula de cierre
- [x] `FundMemberTest` (nuevo): tests de `earningsBalance()`, `totalEarned()`, `earningsDisbursements()`
- [x] `TransactionServiceTest`: tests de `createMemberDisbursement()` con validación de balance
- [x] `AuthorizationTest` (nuevo): scope por rol (company_user, member)

### Producción
- [x] Separar seeders de desarrollo vs producción
- [x] Documentar checklist de deployment en CLAUDE.md

### Cierre de versión
- [x] Visto bueno del usuario
- [x] `git commit` + `git tag v0.9.0` + `git push --tags`

---

## v1.0.0 — Lanzamiento

- [ ] QA completo en instancia `cormart_staging`
- [ ] Corrección de todos los issues encontrados en QA
- [ ] Actualizar CLAUDE.md: estado v1.0.0, fecha de lanzamiento
- [ ] Visto bueno final del usuario
- [ ] `git tag -a v1.0.0 -m "Release v1.0.0"`
- [ ] `git push origin master --tags`
- [ ] `gh release create v1.0.0 --title "Cormart Factory v1.0.0" --notes "..."`
- [ ] Deploy a Namecheap
