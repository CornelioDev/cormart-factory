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
- [ ] `git commit` + `git tag v0.5.0` + `git push --tags`

---

## v0.6.0 — Cuenta de Ganancias y Desembolsos a Miembros

### Base de datos
- [ ] Migración: agregar `member_disbursement` al enum `transactions.type`
- [ ] Migración: agregar `fund_member_id` (nullable FK → fund_members) a `transactions`

### Modelos
- [ ] `FundMember`: método `earningsBalance(): float`
- [ ] `FundMember`: método `totalEarned(): float`
- [ ] `FundMember`: método `earningsDisbursements()` (HasMany)
- [ ] `Transaction`: relación `fundMember()` (BelongsTo)

### Services
- [ ] `TransactionService`: método `createMemberDisbursement()` con validación de balance
- [ ] `TransactionService`: auto-generar expense en `member_disbursement` (mismo `tax_pct`)

### Filament UI
- [ ] `FundMemberResource`: columnas `earnings_balance` y `total_earned` en tabla
- [ ] `FundMemberResource`: action "Desembolsar Ganancias" (super_admin, modal con monto/banco/referencia)

### Cierre de versión
- [ ] Tests actualizados para todos los cambios de v0.6.0
- [ ] Visto bueno del usuario
- [ ] `git commit` + `git tag v0.6.0` + `git push --tags`

---

## v0.7.0 — Perfil de Miembro (Estado de Cuenta)

### Filament UI
- [ ] `MemberAccountPage`: nueva página con stats de capital, ganancias, balance
- [ ] `MemberAccountPage`: tabla de ClosingDistributions (historial de ingresos)
- [ ] `MemberAccountPage`: tabla de member_disbursements (historial de pagos recibidos)
- [ ] `MemberAccountPage`: action "Registrar Desembolso" (super_admin only)
- [ ] `MemberAccountPage`: acceso de `member` restringido a su propio perfil
- [ ] `MonthlyClosingResource` ViewPage: tabla de distribuciones por miembro en el cierre

### Cierre de versión
- [ ] Tests actualizados para todos los cambios de v0.7.0
- [ ] Visto bueno del usuario
- [ ] `git commit` + `git tag v0.7.0` + `git push --tags`

---

## v0.8.0 — Dashboard Financiero para Miembros

### Widgets
- [ ] `MemberAccountWidget`: nuevo widget StatsOverview para rol `member`
- [ ] `MemberDistributionsWidget`: nuevo widget tabla de últimos cierres del miembro
- [ ] `CapitalSummaryWidget`: agregar stat de gastos/ganancia neta real
- [ ] `CapitalSummaryWidget`: ajustar acceso para excluir rol `member`

### Cierre de versión
- [ ] Tests actualizados para todos los cambios de v0.8.0
- [ ] Visto bueno del usuario
- [ ] `git commit` + `git tag v0.8.0` + `git push --tags`

---

## v0.9.0 — Calidad y Preparación para Producción

### Bug fixes
- [ ] `RolePolicy`: reemplazar placeholders de template por strings de permiso reales

### Tests
- [ ] `TransactionServiceTest`: tests para tipo `expense` y auto-generación de impuesto
- [ ] `DistributionServiceTest`: tests con gastos en la fórmula de cierre
- [ ] `FundMemberTest` (nuevo): tests de `earningsBalance()`, `totalEarned()`, `earningsDisbursements()`
- [ ] `TransactionServiceTest`: tests de `createMemberDisbursement()` con validación de balance
- [ ] `AuthorizationTest` (nuevo): scope por rol (company_user, member)

### Producción
- [ ] Separar seeders de desarrollo vs producción
- [ ] Documentar checklist de deployment en CLAUDE.md

### Cierre de versión
- [ ] Visto bueno del usuario
- [ ] `git commit` + `git tag v0.9.0` + `git push --tags`

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
