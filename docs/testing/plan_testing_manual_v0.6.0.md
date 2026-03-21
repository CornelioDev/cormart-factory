# Plan de Testing Manual — Cormart Factory v0.6.0

> Versión: v0.6.0 — Cuenta de Ganancias del Fondo y Desembolsos a Miembros

## Resumen de la sesión

| Campo | Valor |
|---|---|
| Tester | José Cornelio |
| Fecha | 3/20/26 |
| Instancia | `cormart_factory` / `cormart_staging` |
| Rol | `super_admin` |
| Resultado | — |

---

## Preparación

Verificar que las migraciones se aplicaron:

```bash
php artisan migrate:status
```

Verificar que la tabla `fund_account` existe y tiene un registro con balance:

```bash
php artisan tinker --execute="echo \App\Models\FundAccount::instance()->balance;"
```

Debe retornar un valor numérico (balance histórico calculado por el seeder).

Verificar que la columna `fund_member_id` existe en `transactions`:

```bash
php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'fund_member_id') ? 'OK' : 'FALTA';"
```

Debe retornar `OK`.

---

## 1. Dashboard — Widget de Balance Bancario

- [x] Ir al **Dashboard** como `super_admin` → widget **BankBalanceWidget** visible con 5 stats
  - _Notas: —_
- [x] Stat **Capital de Miembros**: muestra suma de aportaciones activas
  - _Notas: —_ Esto es redundante, ya existe un widget de `capital total`
- [x] Stat **Capital Desplegado**: muestra suma de `transfer_amount` de financiamientos desembolsados/parcialmente cobrados
  - _Notas: —_ Esto es redundante, ya existe un widget `capital en calle`
- [x] Stat **Ganancias del Fondo**: muestra `FundAccount.balance`
  - _Notas: —_
- [x] Stat **Ganancias de Miembros**: muestra distribuido sin desembolsar
  - _Notas: —_ Esto no debe de estar en el dashboard
- [x] Stat **Saldo Estimado Banco**: = Capital − Desplegado + Ganancias Fondo + Ganancias Miembros
  - _Notas: —_

---

## 2. Desembolso a compañía — Comisión acreditada al fondo

> Requiere un financiamiento en estado `solicited`.

- [x] Anotar el valor actual de `FundAccount.balance` antes de desembolsar
  - _Notas: —_
- [x] Crear un financiamiento (RD$ 100,000, plazo 15 días) → comisión = RD$ 5,000
  - _Notas: —_
- [x] Desembolsar desde **Cuentas por Pagar** → llenar banco, nro. transacción, fecha
  - _Notas: —_
- [x] Verificar que `FundAccount.balance` aumentó por la comisión (+RD$ 5,000)
  - _Notas: —_
- [x] Verificar que `FundAccount.balance` disminuyó por el tax expense automático (−RD$ 7.50)
  - _Notas: —_ el tax expense automático fue de 142.5
- [x] En **Transacciones**: aparecen el desembolso + gasto DGII automático
  - _Notas: —_

---

## 3. Gasto manual — Débito del fondo

- [x] Anotar el valor actual de `FundAccount.balance`
  - _Notas: —_
- [x] Crear transacción tipo **Gasto Operativo** (RD$ 1,000, proveedor, banco, notas)
  - _Notas: —_
- [x] Verificar que `FundAccount.balance` disminuyó en RD$ 1,000
  - _Notas: —_

---

## 4. Cobro completo — La cuenta del fondo NO cambia

- [x] Anotar el valor actual de `FundAccount.balance`
  - _Notas: —_
- [x] Registrar cobro completo de un financiamiento desembolsado
  - _Notas: —_
- [x] Verificar que `FundAccount.balance` **no cambió** (comisión ya fue acreditada al desembolsar)
  - _Notas: —_

---

## 5. Cierre mensual — Distribuciones por miembro

> Usar un período con al menos un financiamiento cobrado.

- [x] Ir a **MonthlyClosingPage** → calcular distribución para el período
  - _Notas: —_
- [x] Ejecutar el cierre → `ClosingDistribution` creadas (fórmula sin cambios)
  - _Notas: —_
- [x] Verificar que se creó una transacción `earning_distribution` por cada miembro con `total_amount > 0`
  - _Notas: —_
- [x] Verificar código de transacción: `DIST-{período}-{memberId}`
  - _Notas: —_
- [x] Verificar que `FundAccount.balance` disminuyó por el total distribuido
  - _Notas: —_
- [x] Widget **Ganancias de Miembros** aumentó por el total distribuido
  - _Notas: —_
- [x] La reserva permanece en `FundAccount.balance` (no se distribuye)
  - _Notas: —_

---

## 6. FundMemberResource — Lista

- [x] Ir a **Administración → Miembros del Fondo**
  - _Notas: —_
- [x] Columna **Total Ganado** visible con valor correcto (`totalEarned()`)
  - _Notas: —_
- [x] Columna **Balance Ganancias** visible con valor correcto (`earningsBalance()`)
  - _Notas: —_
- [x] Click en un miembro → navega a **ViewFundMember** (no a EditPage)
  - _Notas: —_

---

## 7. FundMemberResource — ViewPage

### 7a. Infolist

- [x] Sección **Datos del Miembro**: nombre, tipo (badge), capital, %, fecha, estado
  - _Notas: —_
- [x] Sección **Resumen de Ganancias**: Total Ganado, Total Desembolsado, Balance Disponible
  - _Notas: —_
- [x] Montos coinciden con las columnas de la lista
  - _Notas: —_

### 7b. Tabla de transacciones (RelationManager)

- [x] Muestra transacciones `earning_distribution` y `member_disbursement` en tabla unificada
  - _Notas: —_
- [x] Badge verde **Distribución** / naranja **Desembolso**
  - _Notas: —_
- [x] Filtro por tipo funciona
  - _Notas: —_
- [x] Ordenada por fecha descendente
  - _Notas: —_

### 7c. Action "Editar"

- [x] Modal carga datos actuales del miembro
  - _Notas: —_
- [x] Cambiar nombre → se actualiza correctamente
  - _Notas: —_
- [x] Cambiar aportación → `FundMemberService::recalculateAllPercentages()` ejecutado, % actualizado
  - _Notas: —_ El % de aportación tiene demasiados pasos 00.0000, sebe mostrarse como 00.00
 
### 7d. Action "Desembolsar Ganancias"

> Requiere un miembro con `earningsBalance() > 0` (ejecutar cierre primero si es necesario).

- [x] Action visible solo si `earningsBalance() > 0`
  - _Notas: —_
- [x] Placeholder muestra balance disponible correcto
  - _Notas: —_
- [x] Validación: monto > balance → error
  - _Notas: —_
- [x] Validación: monto ≤ 0 → error
  - _Notas: —_
- [x] Validación: `transaction_number` duplicado → error unique
  - _Notas: —_
- [x] Desembolso exitoso → notificación + transacción `member_disbursement` creada
  - _Notas: —_ Para ver los cambios en la vista, es necesario recargar la página, sería bueno si esto puede ser actualizado en tiempo real.
- [x] Tax expense DGII auto-generado y debitado de `FundAccount`
  - _Notas: —_
- [x] `earningsBalance()` del miembro disminuyó correctamente
  - _Notas: —_

---

## 8. TransactionResource — Nuevos tipos

- [x] Badge: `earning_distribution` → **Distribución** (verde)
  - _Notas: —_
- [x] Badge: `member_disbursement` → **Desembolso a Miembro** (naranja)
  - _Notas: —_
- [x] `SelectFilter` tipo: incluye ambos tipos nuevos
  - _Notas: —_ En los selectores de filtro está `proveedor` esto debe ser cambiado por `beneficiario`
- [x] Búsqueda por beneficiario: encuentra transacciones por nombre de `FundMember`
  - _Notas: —_
- [x] Vista detalle: sección **Financiamientos Asociados** oculta para ambos tipos
  - _Notas: —_

---

## 9. Casos borde

- [x] Miembro sin ganancias: action **Desembolsar Ganancias** no visible
  - _Notas: —_
- [x] Miembro `in_kind`: distribución correcta tras cierre
  - _Notas: —_
- [x] Doble cierre del mismo período: excepción con mensaje de error
  - _Notas: —_
- [x] **CuentasPorPagarPage**: no afectada por nuevos tipos (query sobre `Financing`, no `Transaction`)
  - _Notas: —_

---

## Observaciones generales

-
