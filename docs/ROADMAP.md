# Roadmap Cormart Factory → v1.0.0

> Actualizado: 2026-03-24 | Estado actual: **v0.8.0**

---

## Contexto

El sistema v0.4.1 tiene el flujo core completo: financiamientos, transacciones, cierres mensuales con distribución y RBAC con 4 roles. Las versiones restantes hasta v1.0.0 agregan nuevas funcionalidades de negocio centradas en gastos operativos, impuesto automático de desembolso, cuentas de ganancias por miembro, perfiles y dashboard financiero.

---

## Nuevos requerimientos (v0.5.0 → v1.0.0)

| # | Requerimiento | Versión |
|---|---|---|
| 1 | Transacciones de gastos operativos (tipo `expense`) | v0.5.0 |
| 2 | Impuesto automático en todo desembolso (compañía o miembro): `tax_pct` × monto | v0.5.0 |
| 3 | Cuenta de ganancias por miembro (separada del capital) | v0.6.0 |
| 4 | Desembolsos de ganancias a miembros (`member_disbursement`) | v0.6.0 |
| 5 | Perfil de miembro con estado de cuenta (ingresos de cierres + desembolsos) | v0.7.0 |
| 6 | Dashboard financiero con KPIs, gráficos y comparativos | v0.8.0 |

---

## Impacto en la fórmula de distribución mensual

La fórmula actualizada incluye gastos del período:

```
Comisiones cobradas del período
  − Gastos del período (type=expense, transaction_date en el período)
  = Base real de ganancias
    − Rendimiento fijo (capital × fixed_return_pct) por miembro activo de capital
    = Ganancia neta
      − Reserva (ganancia × reserve_pct)
      = Post-reserva
          − Aporte en especie (post-reserva × in_kind_pct)
          = Disponible para capital → reparto proporcional por fund_percentage

Verificación: comisiones = gastos + total_fijo + reserva + naturaleza + capital → diff = 0
```

---

## v0.5.0 — Gastos e Impuesto de Desembolso

**Objetivo:** Registrar gastos operativos y auto-calcular impuesto en desembolsos. Actualizar cierre mensual.

### Cambios de BD
- `transactions.type` enum: agregar `expense`
- `monthly_closings`: agregar `total_expenses decimal(15,2) default 0`
- `parameters`: agregar `tax_pct = 0.15`

### Archivos clave
| Archivo | Cambio |
|---|---|
| `app/Services/TransactionService.php` | Auto-generar expense en disbursement; nuevo `createExpense()` |
| `app/Services/DistributionService.php` | Descontar gastos del período en `calculate()` |
| `app/Models/MonthlyClosing.php` | Agregar `total_expenses` |
| `app/Filament/Resources/TransactionResource.php` | Tipo `expense` (super_admin, sin company/financings) |
| `app/Filament/Pages/MonthlyClosingPage.php` | Línea de gastos en preview |
| `app/Filament/Pages/ParametrosPage.php` | Campo `tax_pct` |
| `app/Filament/Widgets/CapitalSummaryWidget.php` | Stat de gastos del mes |

> **Regla:** Todo desembolso del fondo (a compañía O a miembro) genera automáticamente un gasto de `tax_pct` sobre el monto.

---

## v0.6.0 — Cuenta de Ganancias y Desembolsos a Miembros

**Objetivo:** Balance de ganancias acumuladas por miembro. Super_admin puede registrar pagos de ganancias.

### Cambios de BD
- `transactions.type` enum: agregar `member_disbursement`
- `transactions`: agregar `fund_member_id` (nullable FK → fund_members)

### Archivos clave
| Archivo | Cambio |
|---|---|
| `app/Models/FundMember.php` | `earningsBalance()`, `totalEarned()`, `earningsDisbursements()` |
| `app/Models/Transaction.php` | Relación `fundMember()` |
| `app/Services/TransactionService.php` | `createMemberDisbursement()` con validación de balance |
| `app/Filament/Resources/FundMemberResource.php` | Columnas earnings_balance, total_earned; action "Desembolsar Ganancias" |

---

## v0.7.0 — Perfil de Miembro (Estado de Cuenta)

**Objetivo:** Página dedicada con historial de ingresos (cierres) y desembolsos de ganancias por miembro.

### Archivos clave
| Archivo | Cambio |
|---|---|
| `app/Filament/Pages/MemberAccountPage.php` | Nueva página: stats, tabla de cierres, tabla de desembolsos |
| `app/Filament/Resources/MonthlyClosingResource.php` | ViewPage: tabla de distribuciones por miembro |

**Acceso:** `super_admin` (cualquier miembro), `member` (su propio perfil vía `user->fund_member_id`)

---

## v0.8.0 — Dashboard Financiero

**Objetivo:** Página dedicada de Dashboard Financiero con KPIs, gráficos y comparativos para super_admin y operator.

### Archivos clave
| Archivo | Cambio |
|---|---|
| `app/Filament/Pages/FinancialDashboardPage.php` | Nueva página: KPIs (Capital, Fondo, Comisiones, Proyecciones), gráficos (tendencia, barras, doughnut), tabla de distribuciones |
| `resources/views/filament/pages/financial-dashboard-page.blade.php` | Vista Blade con Chart.js y diseño responsive |
| `app/Filament/Widgets/CapitalSummaryWidget.php` | Eliminado (reemplazado por el dashboard) |
| `app/Filament/Widgets/BankBalanceWidget.php` | Eliminado (reemplazado por el dashboard) |
| `database/seeders/MonthlyClosingSeeder.php` | Nuevo: cierres de enero y febrero 2026 |
| `database/seeders/FinancingAndTransactionSeeder.php` | Datos multi-mes con gastos operativos |

---

## v0.9.0 — Calidad y Preparación para Producción

**Objetivo:** Tests de nuevos features, corrección de bugs conocidos, checklist de producción.

### Trabajo
- **Bug fix:** `app/Policies/RolePolicy.php` — reemplazar placeholders de template
- **Tests:** TransactionService (expense, member_disbursement), DistributionService (con gastos), FundMember (earningsBalance), Auth (scope por rol)
- **Producción:** Separar seeders dev/prod, documentar checklist en CLAUDE.md

---

## v1.0.0 — Lanzamiento

**Objetivo:** QA final en staging + deploy.

- QA completo en `cormart_staging`
- Corrección de issues del QA
- Actualizar CLAUDE.md al estado v1.0.0
- `git tag -a v1.0.0` + `git push --tags`
- `gh release create v1.0.0`
- Deploy a Namecheap

---

## Resumen

| Versión | Foco | Estado |
|---|---|---|
| **v0.5.0** | Gastos + impuesto automático de desembolso | ✅ Completado |
| **v0.6.0** | Cuenta de ganancias + desembolsos a miembros | ✅ Completado |
| **v0.7.0** | Perfil de miembro — estado de cuenta | ✅ Completado |
| **v0.8.0** | Dashboard financiero | ✅ Completado |
| **v0.9.0** | Tests, bug fixes, preparación producción | Pendiente |
| **v1.0.0** | QA y lanzamiento | Pendiente |
