# Plan de Testing Manual — Cormart Factory v0.4.0

## Preparación

### Crear usuarios de prueba en staging

Desde el panel como `super_admin` (Administración → Usuarios):

| Usuario | Rol | Compañía |
|---|---|---|
| `operator@test.com` | operator | — |
| `member@test.com` | member | — |
| `cormart@test.com` | company_user | Cormart Soluciones |
| `ysetech@test.com` | company_user | Ysetech |

> Alternativa: `php artisan make:filament-user` + tinker para asignar rol:
> ```bash
> \App\Models\User::where('email','operator@test.com')->first()->assignRole('operator');
> ```

---

## Rol 1 — `super_admin` ✅

**Menú esperado:** Operaciones, Cierre Mensual, Administración completa.

### Escritorio
- [x] `CapitalSummaryWidget` muestra: Capital Total, Capital en Calle, Capital Disponible, Comisiones del Mes, Proyección de Comisiones, % de Cobro
- [x] `FinancingPipelineWidget` muestra todos los financiamientos por estado: Solicitados, Desembolsados, **Abonados**, Cobrados
- [x] ~~`ActiveFinancingsWidget`~~ — Widget eliminado (ya no es requerido)
- [x] `PendingTransactionsWidget` muestra transacciones pendientes de confirmar con botón "Confirmar"

### Compañías
- [x] Ver listado de compañías con columnas: nombre, RNC, contacto, email, teléfono, cantidad de clientes, estado
- [x] Crear nueva compañía
- [x] Editar compañía existente
- [x] Activar/desactivar compañía
- [x] Filtro ternario: activa/inactiva/todas

### Clientes (Deudores)
- [x] Ver clientes de todas las compañías con estadísticas (cantidad financiamientos, monto total, comisiones)
- [x] Crear cliente asignado a una compañía
- [x] El campo compañía filtra correctamente

### Financiamientos
- [x] Ver todos los financiamientos de todas las compañías
- [x] Crear financiamiento: comisión y monto neto se calculan automáticamente
- [x] El campo `amount` muestra formato de moneda `RD$` con separadores de miles — ✓ Resuelto: `$money()` mask aplicado
- [x] El código `FN000001` se genera al guardar
- [x] Selector de clientes se filtra dinámicamente al cambiar la compañía — ✓ Verificado: selector ya filtraba compañías activas
- [x] Fecha de vencimiento se calcula automáticamente según plazo en días
- [x] Adjuntar documento (OC o Factura, PDF/JPG/PNG) y verificar que se puede visualizar.
- [x] **Vista detalle**: muestra datos del financiamiento (código, estado, montos, fechas)
- [x] **Vista detalle**: lista de documentos con enlaces de preview
- [x] **Vista detalle**: transacciones asociadas con enlaces clickeables al detalle de cada transacción
- [x] **Vista detalle**: acciones contextuales disponibles según estado (Desembolsar, Cobrar, Cancelar)
- [x] Acción "Desembolsar" disponible en financiamientos con estado `solicitado`
- [x] Acción "Cobrar" disponible en financiamientos con estado `desembolsado` o `partially_collected`
- [x] Cancelar financiamiento: campo motivo de cancelación es obligatorio
- [x] Financiamiento solo se puede editar en estado `solicitado`
- [x] Acción en lote: seleccionar varios de la **misma compañía** → desembolsar en lote
- [x] Acción en lote: seleccionar financiamientos de **compañías distintas** → debe bloquearse o alertar

### Financiamientos — Cobros parciales (abonos)
- [x] Crear cobro parcial (abono) sobre un financiamiento `desembolsado` → estado cambia a `partially_collected`
- [x] El campo `collected_amount` acumula el monto de cada abono correctamente
- [x] Crear segundo abono que completa el saldo → estado pasa a `collected`
- [x] `collection_period` y `collected_at` se asignan **solo al completar** el cobro total (último abono)
- [x] El pipeline widget refleja el estado `Abonados` con el conteo y monto pendiente correctos

### Transacciones
- [x] Ver todas las transacciones con código `TX000001` auto-generado
- [x] El código TX aparece en la tabla y en la vista detalle
- [x] Crear transacción de **Desembolso**: selector de compañía → selector de financiamientos solicitados → monto calculado automáticamente (suma de `transfer_amount`)
- [x] Crear transacción de **Cobro completo**: financiamientos desembolsados → monto = balance restante — ✓ Resuelto: campo disabled y read-only cuando hay múltiples financiamientos
- [x] Crear transacción de **Abono parcial**: permite ingresar monto menor al balance restante
- [x] El campo `amount` muestra formato de moneda `RD$` con separadores de miles
- [x] Selector de banco, número de transacción, fecha y notas funcionan correctamente — ✓ Resuelto: filtro por banco + columna sortable
- [x] Transacciones creadas por operator se confirman automáticamente
- [x] Vista detalle: muestra datos de la transacción y financiamientos vinculados con enlaces clickeables — ✓ Resuelto: comisión solo visible en desembolsos
- [x] Filtro por estado: pendiente/confirmada
- [x] Acción "Confirmar" disponible para transacciones pendientes

### Cuentas por Cobrar
- [x] Muestra financiamientos en estado `desembolsado`
- [x] Los widgets de stats muestran: Total por Cobrar, Al Día, Vencidos (con monto), Cobrado en [Mes]
- [x] Acción en lote "Cobrar seleccionados" redirige a crear transacción de cobro con los IDs preseleccionados

### Cuentas por Pagar
- [x] Muestra solo financiamientos en estado `solicitado`
- [x] Los widgets de stats muestran: Total por Desembolsar, Monto Total Solicitado, Desembolsos del Mes, Antigüedad Promedio — ✓ Resuelto: widget actualizado
- [x] Acción en lote "Desembolsar seleccionados" redirige correctamente

### Cierre Mensual
- [x] Seleccionar un período con financiamientos cobrados → preview muestra todos los cálculos — ✓ Resuelto: dark mode + padding corregidos
- [x] Verificar que la cascada de distribución es matemáticamente correcta (diff = 0)
- [x] Ejecutar cierre → se crea el registro histórico
- [x] Intentar cerrar el mismo período dos veces → debe bloquearse con mensaje claro
- [x] El historial de cierres muestra: período, comisiones, fijo, reserva, naturaleza, disponible para capital, ejecutado por, fecha

### Parámetros
- [x] Los valores actuales se muestran sin ceros innecesarios (`5.00`, no `5.0000`)
- [x] Modificar un parámetro y guardar → notificación de éxito
- [x] Verificar que el cambio quedó en el historial de parámetros

### Miembros del Fondo
- [x] Crear miembro tipo Capital: muestra campos contribución (formato `RD$`) y porcentaje del fondo — ✓ Resuelto: % auto-calculado y read-only
- [x] Crear miembro tipo Naturaleza: campos contribución y porcentaje **no aparecen**
- [x] El campo `contribution` muestra formato de moneda `RD$` con separadores de miles
- [x] Editar miembro: activar/desactivar, modificar fechas

### Usuarios
- [x] Crear usuario con cada rol — ✓ Resuelto: selector de miembro del fondo para `member` (requerido)
- [x] Asignar compañía a un `company_user` — ✓ Resuelto: selector de compañía condicional
- [x] Editar contraseña de un usuario
- [x] Badges de rol con colores: super_admin=rojo, operator=naranja, member=verde, company_user=azul — ✓ Resuelto

---

## Rol 2 — `operator`

**Menú esperado:** Operaciones completas, sin Usuarios, sin Parámetros, sin Cierre Mensual.

### Escritorio
- [ ] `CapitalSummaryWidget` **sí visible**
- [ ] `FinancingPipelineWidget` visible con todos los estados
- [ ] `PendingTransactionsWidget` visible con botón "Confirmar"

### Accesos restringidos
- [ ] **No aparece** Cierre Mensual en el menú
- [ ] **No aparece** Parámetros en el menú
- [ ] **No aparece** Usuarios en el menú
- [ ] Intentar acceder por URL directa → debe redirigir o mostrar error de permisos

### Operaciones
- [ ] Puede ver y crear financiamientos de cualquier compañía
- [ ] Puede crear transacciones de desembolso y cobro
- [ ] Transacciones creadas por operator se confirman automáticamente
- [ ] Puede confirmar transacciones de cobro pendientes creadas por `company_user`
- [ ] Puede crear cobros parciales (abonos) y verificar acumulación de `collected_amount`
- [ ] Campos monetarios muestran formato `RD$` correctamente

---

## Rol 3 — `member`

**Menú esperado:** Solo lectura. Financiamientos y Cierres Mensuales.

### Escritorio
- [ ] `CapitalSummaryWidget` **sí visible**
- [ ] `FinancingPipelineWidget` visible (incluye estado Abonados)
- [ ] `PendingTransactionsWidget` **no visible**

### Accesos
- [ ] Puede **ver** financiamientos pero no crear ni editar (sin botón "Nuevo")
- [ ] Puede acceder a la **vista detalle** de financiamientos (solo lectura, sin acciones)
- [ ] Puede **ver** historial de cierres mensuales
- [ ] **No aparece** Transacciones en el menú
- [ ] **No aparece** Compañías, Clientes, Usuarios, Parámetros
- [ ] Intentar acceder por URL directa a crear financiamiento → error de permisos

---

## Rol 4 — `company_user`

Probar con `cormart@test.com` **y** con `ysetech@test.com` por separado para verificar aislamiento.

**Menú esperado:** Solo Operaciones acotadas a su compañía.

### Escritorio
- [ ] `CapitalSummaryWidget` **no visible**
- [ ] `FinancingPipelineWidget` muestra solo financiamientos de su compañía (incluye Abonados)

### Aislamiento de datos
- [ ] En Financiamientos: solo ve registros de su compañía
- [ ] En Clientes: solo ve clientes de su compañía
- [ ] Al crear un financiamiento, el selector de clientes solo ofrece los de su compañía
- [ ] Iniciar sesión con `cormart@test.com`: no ve datos de Ysetech
- [ ] Iniciar sesión con `ysetech@test.com`: no ve datos de Cormart

### Flujo de cobro
- [ ] Puede crear solicitud de cobro (transacción tipo `collection`)
- [ ] La transacción queda en estado `pendiente` esperando confirmación del operator
- [ ] Puede crear cobros parciales (abonos) sobre financiamientos de su compañía
- [ ] **No puede** crear transacciones de desembolso
- [ ] Campos monetarios muestran formato `RD$` correctamente

### Cuentas por Cobrar (company_user)
- [ ] `CuentasPorCobrarStatsWidget` muestra datos filtrados por su compañía
- [ ] Métricas: Total por Cobrar, Al Día, Vencidos, Cobrado en [Mes]

### Accesos bloqueados
- [ ] **No aparece** Compañías, Usuarios, Parámetros, Cierre Mensual en el menú
- [ ] **No puede** desembolsar financiamientos (sin botón de acción)

---

## Flujos completos

### Flujo A — Ciclo completo de un financiamiento (cobro total)

1. `company_user` crea financiamiento → estado `solicitado`, código `FN` auto-generado
2. `operator` lo desembolsa desde Cuentas por Pagar → estado `desembolsado`, transacción con código `TX` auto-generado
3. `company_user` solicita cobro desde Cuentas por Cobrar → transacción en `pendiente`
4. `operator` confirma la transacción → estado `cobrado`, `collected_at` se asigna
5. `super_admin` ejecuta cierre mensual → verifica que el financiamiento aparece en el cálculo
6. Verificar distribución por miembro en el historial de cierres

### Flujo B — Desembolso en lote

1. Crear 3 financiamientos de la misma compañía en estado `solicitado`
2. Seleccionarlos en lote desde Cuentas por Pagar → acción Desembolsar
3. Crear la transacción de desembolso con los 3 vinculados → monto = suma de `transfer_amount`
4. Verificar que los 3 pasan a `desembolsado`
5. Abrir la transacción creada → vista detalle muestra los 3 financiamientos con sus enlaces

### Flujo C — Rechazo de lote entre compañías

1. Seleccionar financiamientos de Cormart y Ysetech mezclados
2. Intentar acción en lote → el sistema debe rechazarlo o alertar

### Flujo D — Ciclo con cobros parciales (abonos)

1. `operator` desembolsa un financiamiento de RD$ 100,000 → estado `desembolsado`
2. `company_user` crea cobro parcial por RD$ 40,000 → transacción `pendiente`
3. `operator` confirma → financiamiento pasa a `partially_collected`, `collected_amount` = 40,000
4. `company_user` crea segundo abono por RD$ 60,000 → transacción `pendiente`
5. `operator` confirma → financiamiento pasa a `collected`, `collected_amount` = 100,000
6. Verificar que `collection_period` y `collected_at` se asignaron solo en el paso 5
7. El financiamiento ya no aparece en Cuentas por Cobrar

### Flujo E — Formateo de moneda en formularios

1. Abrir formulario de Nuevo Financiamiento → ingresar monto → verificar que se muestra con formato `RD$` y separadores de miles
2. Guardar → verificar que el valor se almacenó correctamente (sin prefijo `RD$`)
3. Abrir formulario de Nueva Transacción → verificar formato en campo monto
4. Abrir formulario de Miembro del Fondo → verificar formato en campo contribución
5. Editar cada registro guardado → verificar que los valores se cargan con formato correcto

---

## Notas para recopilar requerimientos

Durante las pruebas, documentar:

- **Flujos poco intuitivos**: pasos que confunden o requieren demasiados clics
- **Información faltante**: datos que se necesitan ver en una vista pero no están
- **Filtros o columnas**: columnas útiles que faltan en las tablas
- **Permisos**: acciones que un rol debería poder hacer y no puede, o viceversa
- **Validaciones**: casos borde que el sistema no maneja correctamente
- **Rendimiento**: pantallas o acciones que cargan lento
- **Formateo**: campos monetarios que no muestran el formato `RD$` esperado
