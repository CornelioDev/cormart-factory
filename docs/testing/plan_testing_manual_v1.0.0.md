# Plan de Testing Manual — Cormart Factory v1.0.0

> Versión: v1.0.0 — QA completo pre-lanzamiento en staging

## Resumen de la sesión

| Campo     | Valor                                 |
| --------- | ------------------------------------- |
| Tester    | —                                     |
| Fecha     | —                                     |
| Instancia | `cormart_staging`                     |
| Rol       | Todos (`super_admin`, `operator`, `member`, `company_user`) |
| Resultado | —                                     |

## Preparación

Verificar que las migraciones están al día:

```bash
php artisan migrate:status
```

Verificar que los seeders de producción están ejecutados:

```bash
php artisan tinker --execute="
echo 'Parámetros: ' . \App\Models\Parameter::count();
echo PHP_EOL . 'Roles: ' . \Spatie\Permission\Models\Role::count();
echo PHP_EOL . 'Companies: ' . \App\Models\Company::count();
echo PHP_EOL . 'FundMembers: ' . \App\Models\FundMember::count();
echo PHP_EOL . 'Users: ' . \App\Models\User::count();
"
```

Verificar que Shield generó todos los permisos:

```bash
php artisan shield:generate --all
```

Verificar que los tests automatizados pasan:

```bash
php artisan test
```

---

## 1. Login y acceso por rol

> Probar con cada uno de los 4 roles. Verificar que el menú de navegación muestra solo lo permitido.

- [ ] **super_admin** ve: Escritorio, Dashboard Financiero, Financiamientos, Transacciones, Compañías, Clientes, Miembros del Fondo, Cierres Mensuales, Cierre Mensual (ejecución), Cuentas por Cobrar, Cuentas por Pagar, Parámetros, Usuarios, Roles
  - _Notas: —_

- [ ] **operator** ve: Escritorio, Financiamientos, Transacciones, Compañías, Clientes, Cuentas por Cobrar, Cuentas por Pagar
  - _Notas: —_
  - [ ] NO ve: Dashboard Financiero, Miembros del Fondo, Cierres Mensuales, Cierre Mensual (ejecución), Parámetros, Usuarios, Roles
    - _Notas: —_

- [ ] **member** ve: Escritorio, Estado de Cuenta (redirige a su FundMember), Cierres Mensuales (solo lectura)
  - _Notas: —_
  - [ ] NO ve: Financiamientos, Transacciones, Compañías, Clientes, Parámetros, Usuarios
    - _Notas: —_

- [ ] **company_user** ve: Escritorio, Financiamientos (solo su compañía), Transacciones (solo collections de su compañía), Cuentas por Cobrar (solo su compañía)
  - _Notas: —_
  - [ ] NO ve: Dashboard Financiero, Miembros del Fondo, Cierres Mensuales, Cuentas por Pagar, Parámetros, Usuarios
    - _Notas: —_

---

## 2. Dashboard (Escritorio) — Widgets

> Iniciar sesión como `super_admin`.

- [ ] Widget **Pipeline de Financiamientos** muestra conteos correctos por estado (excluye solicitados y cobrados del conteo de activos)
  - _Notas: —_

- [ ] Widget **Transacciones Pendientes** muestra transacciones sin confirmar (si las hay)
  - _Notas: —_

- [ ] Widget **Cuentas por Cobrar** muestra stats correctos
  - _Notas: —_

- [ ] Widget **Cuentas por Pagar** muestra stats correctos
  - _Notas: —_

- [ ] Widget **Financiamientos Vencidos** muestra financiamientos pasados de fecha (si los hay)
  - _Notas: —_

- [ ] Widget **Financiamientos Solicitados** muestra pendientes de desembolso (si los hay)
  - _Notas: —_

> Cambiar a `company_user`.

- [ ] Pipeline de Financiamientos filtrado a **solo su compañía**
  - _Notas: —_

- [ ] Cuentas por Cobrar filtrado a **solo su compañía**
  - _Notas: —_

- [ ] NO ve widgets de Transacciones Pendientes ni Cuentas por Pagar
  - _Notas: —_

---

## 3. Compañías (CRUD)

> Iniciar sesión como `super_admin` o `operator`.

- [ ] Listar compañías — tabla muestra nombre, RNC, estado, cantidad de clientes
  - _Notas: —_

- [ ] Crear compañía nueva con todos los campos obligatorios
  - _Notas: —_

- [ ] Editar compañía existente — cambios se guardan correctamente
  - _Notas: —_

- [ ] Ver detalle de compañía — información completa
  - _Notas: —_

---

## 4. Clientes (CRUD)

> Iniciar sesión como `super_admin` o `operator`.

- [ ] Listar clientes — tabla muestra nombre, compañía, estado
  - _Notas: —_

- [ ] Crear cliente asociado a una compañía
  - _Notas: —_

- [ ] Editar cliente existente
  - _Notas: —_

- [ ] Verificar que clientes inactivos no aparecen en selectores de financiamiento
  - _Notas: —_

---

## 5. Financiamientos — Ciclo completo

> Iniciar sesión como `super_admin` o `operator`.

### Crear financiamiento

- [ ] Crear financiamiento con compañía, cliente, monto, plazo → status queda en `solicited`
  - _Notas: —_

- [ ] Código auto-generado en formato `FN000XXX`
  - _Notas: —_

- [ ] Comisión calculada al 5% del monto
  - _Notas: —_

- [ ] Monto de transferencia = monto − comisión
  - _Notas: —_

- [ ] Fecha de vencimiento = fecha de emisión + plazo en días
  - _Notas: —_

### Documentos

- [ ] Subir documento (OC o factura) al financiamiento
  - _Notas: —_

- [ ] Documento visible en la vista de detalle
  - _Notas: —_

- [ ] Descargar documento subido
  - _Notas: —_

### Desembolso

- [ ] Crear transacción de desembolso → status cambia a `disbursed`
  - _Notas: —_

- [ ] Monto del desembolso = suma de transfer_amount de los financiamientos seleccionados
  - _Notas: —_

- [ ] `issue_period` asignado automáticamente
  - _Notas: —_

- [ ] Expense de impuesto (tax) auto-generado con monto correcto (`amount × tax_pct`)
  - _Notas: —_

- [ ] Expense de impuesto vinculado a proveedor DGII
  - _Notas: —_

### Cobro parcial

> Requiere un financiamiento en estado `disbursed`.

- [ ] Crear cobro parcial (monto menor al total) → status cambia a `partially_collected`
  - _Notas: —_

- [ ] `collected_amount` se actualiza con el abono
  - _Notas: —_

- [ ] `collection_period` y `collected_at` NO se asignan aún
  - _Notas: —_

### Cobro completo

- [ ] Crear cobro por el monto restante → status cambia a `collected`
  - _Notas: —_

- [ ] `collected_amount` = `amount` del financiamiento
  - _Notas: —_

- [ ] `collection_period` y `collected_at` asignados al completar
  - _Notas: —_

### Cancelación

> Requiere un financiamiento en estado `solicited`, `disbursed` o `partially_collected`.

- [ ] Acción "Cancelar" disponible solo en la **vista de detalle** (no en la tabla)
  - _Notas: —_

- [ ] Cancelar requiere `cancellation_reason` obligatorio
  - _Notas: —_

- [ ] Status cambia a `cancelled` tras confirmar
  - _Notas: —_

---

## 6. Financiamientos — company_user

> Iniciar sesión como `company_user`.

- [ ] Solo ve financiamientos de **su compañía**
  - _Notas: —_

- [ ] Puede crear solicitud de financiamiento
  - _Notas: —_

- [ ] NO puede desembolsar ni cancelar
  - _Notas: —_

- [ ] Puede crear cobros (collections) — quedan en estado `pending` hasta confirmación del operator
  - _Notas: —_

---

## 7. Transacciones

> Iniciar sesión como `super_admin` o `operator`.

### Listado y filtros

- [ ] Tabla muestra tipo, monto, banco, referencia, fecha, status
  - _Notas: —_

- [ ] Filtro por tipo funciona (disbursement, collection, expense, member_disbursement)
  - _Notas: —_

- [ ] Filtro por proveedor funciona (para expenses)
  - _Notas: —_

### Gastos (expense)

- [ ] Crear expense con monto, banco, referencia, notas (obligatorio), proveedor
  - _Notas: —_

- [ ] Expense se auto-confirma al crear
  - _Notas: —_

- [ ] Balance de FundAccount se debita correctamente
  - _Notas: —_

- [ ] Crear proveedor inline desde el selector
  - _Notas: —_

### Confirmación de transacciones

- [ ] Transacción pendiente (collection de company_user) muestra acción "Confirmar"
  - _Notas: —_

- [ ] Confirmar cambia status a `confirmed`
  - _Notas: —_

- [ ] Confirmar transacción ya confirmada muestra error
  - _Notas: —_

### company_user en transacciones

> Iniciar sesión como `company_user`.

- [ ] Solo ve **collections** de su compañía (no ve disbursements ni expenses)
  - _Notas: —_

---

## 8. Miembros del Fondo

> Iniciar sesión como `super_admin`.

- [ ] Listar miembros — tabla muestra nombre, tipo (capital/in_kind), porcentaje, capital aportado, balance de ganancias, total ganado
  - _Notas: —_

- [ ] `earnings_balance` = total ganado − total desembolsado a miembro
  - _Notas: —_

- [ ] Todos los montos con formato **2 posiciones decimales**
  - _Notas: —_

### Desembolso de ganancias

- [ ] Acción "Desembolsar Ganancias" visible para miembros con balance > 0
  - _Notas: —_

- [ ] Modal solicita monto, banco, referencia
  - _Notas: —_

- [ ] Desembolso exitoso con monto ≤ balance
  - _Notas: —_

- [ ] Error si monto > balance disponible
  - _Notas: —_

- [ ] Expense de impuesto auto-generado por el desembolso al miembro
  - _Notas: —_

---

## 9. Estado de Cuenta — member

> Iniciar sesión como `member`.

- [ ] Redirige automáticamente a la página de su FundMember
  - _Notas: —_

- [ ] Ve su capital aportado, porcentaje, tipo, balance de ganancias
  - _Notas: —_

- [ ] Tabla de historial de distribuciones (por cierre mensual)
  - _Notas: —_

- [ ] NO puede ver datos de otros miembros
  - _Notas: —_

- [ ] NO puede editar su información
  - _Notas: —_

---

## 10. Cierres Mensuales

> Iniciar sesión como `super_admin`.

### Listado (MonthlyClosingResource)

- [ ] Tabla de cierres ejecutados con período, comisiones, gastos, ganancia neta, reserva
  - _Notas: —_

- [ ] Vista de detalle muestra tabla de distribuciones por miembro
  - _Notas: —_

- [ ] Snapshot de parámetros visible en la vista de detalle
  - _Notas: —_

### Ejecución de cierre (MonthlyClosingPage)

> Seleccionar un período con financiamientos desembolsados.

- [ ] Preview muestra: comisiones, gastos, base real, rendimiento fijo, ganancia neta, reserva, post-reserva, naturaleza, capital
  - _Notas: —_

- [ ] Comisiones basadas en **desembolsos del período** (`issue_period`), no en cobros
  - _Notas: —_

- [ ] Gastos del período se descuentan correctamente
  - _Notas: —_

- [ ] `verification_diff` es **0.00**
  - _Notas: —_

- [ ] Distribuciones por miembro con montos coherentes
  - _Notas: —_

- [ ] Miembro naturaleza recibe su porcentaje del post-reserva, fijo = 0
  - _Notas: —_

- [ ] Período ya cerrado NO puede cerrarse de nuevo
  - _Notas: —_

### Ejecutar cierre real

> **Precaución:** crea un cierre que no se puede deshacer.

- [ ] Confirmar ejecución del cierre
  - _Notas: —_

- [ ] Se crea registro `MonthlyClosing`
  - _Notas: —_

- [ ] Se crean `ClosingDistribution` por cada miembro activo
  - _Notas: —_

- [ ] Se crea `ClosingParametersSnapshot` con los 6 parámetros
  - _Notas: —_

---

## 11. Dashboard Financiero

> Iniciar sesión como `super_admin`.

### KPIs por sección

- [ ] **Capital**: Capital Total, Capital en Calle, Capital Disponible — valores correctos
  - _Notas: —_

- [ ] **Fondo**: Ganancias del Fondo, Saldo Estimado Banco, Ganancias Acumuladas — valores correctos
  - _Notas: —_

- [ ] **Comisiones**: Comisiones del Mes (basadas en desembolsos del período), Ganancia Neta
  - _Notas: —_

- [ ] **Indicadores Operativos**: % de Cobro, ROI del período, ROI acumulado
  - _Notas: —_

### Gráficos

- [ ] **Tendencia Financiera** (line chart) — incluye período en curso, datos coherentes
  - _Notas: —_

- [ ] **Financiamientos por Mes** (bar chart) — desembolsados vs cobrados
  - _Notas: —_

- [ ] **Participación por Compañía** (doughnut chart) — proporciones correctas
  - _Notas: —_

- [ ] **ROI por Período** (bar chart) — colores positivo/negativo
  - _Notas: —_

### Tabla y selector

- [ ] **Desglose por Miembro** — columnas Fijo, Variable, Total coherentes
  - _Notas: —_

- [ ] Selector de período funciona, badge muestra "Cierre ejecutado" o "En curso"
  - _Notas: —_

- [ ] Período cerrado carga datos del `MonthlyClosing` persistido
  - _Notas: —_

- [ ] Período en curso calcula en tiempo real
  - _Notas: —_

### Formato

- [ ] Todos los montos en formato `RD$` con **2 posiciones decimales**
  - _Notas: —_

---

## 12. Cuentas por Cobrar

> Iniciar sesión como `super_admin` o `operator`.

- [ ] Página muestra financiamientos pendientes de cobro con montos, vencimiento, días de atraso
  - _Notas: —_

- [ ] Totales calculados correctamente
  - _Notas: —_

- [ ] Financiamientos vencidos resaltados visualmente
  - _Notas: —_

> Cambiar a `company_user`.

- [ ] Solo ve cuentas por cobrar de **su compañía**
  - _Notas: —_

---

## 13. Cuentas por Pagar

> Iniciar sesión como `super_admin` o `operator`.

- [ ] Página muestra montos pendientes de pagar a compañías
  - _Notas: —_

- [ ] Totales correctos
  - _Notas: —_

> Verificar acceso denegado.

- [ ] `company_user` NO puede acceder a Cuentas por Pagar
  - _Notas: —_

- [ ] `member` NO puede acceder a Cuentas por Pagar
  - _Notas: —_

---

## 14. Parámetros del Sistema

> Iniciar sesión como `super_admin`.

- [ ] Página muestra los 6 parámetros con valores actuales
  - _Notas: —_

- [ ] Editar un parámetro — valor se guarda correctamente
  - _Notas: —_

- [ ] Cambio queda registrado en el historial con valor anterior, nuevo, usuario y fecha
  - _Notas: —_

> Verificar acceso denegado.

- [ ] `operator` NO puede acceder a Parámetros
  - _Notas: —_

- [ ] `member` NO puede acceder a Parámetros
  - _Notas: —_

---

## 15. Usuarios y Roles

> Iniciar sesión como `super_admin`.

- [ ] Listar usuarios — tabla con nombre, email, rol asignado
  - _Notas: —_

- [ ] Crear usuario nuevo con rol asignado
  - _Notas: —_

- [ ] Editar usuario — cambiar rol funciona correctamente
  - _Notas: —_

- [ ] Usuario `company_user` tiene `company_id` asignado
  - _Notas: —_

- [ ] Usuario `member` tiene `fund_member_id` asignado
  - _Notas: —_

---

## 16. Formato y UX general

- [ ] Todos los montos monetarios muestran `RD$` con **2 posiciones decimales** en toda la aplicación
  - _Notas: —_

- [ ] Labels, placeholders y mensajes en **español**
  - _Notas: —_

- [ ] Navegación fluida, sin errores 500 ni pantallas en blanco
  - _Notas: —_

- [ ] Notificaciones de éxito/error visibles al crear, editar, eliminar
  - _Notas: —_

- [ ] Responsive: la interfaz se ve correcta en pantallas medianas y grandes
  - _Notas: —_

---

## Observaciones generales

-
