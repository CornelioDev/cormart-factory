# Plan de Testing Manual — Cormart Factory v0.4.0

## Resumen de la sesión

| Campo | Valor |
|---|---|
| Tester | — |
| Fecha | — |
| Instancia | `cormart_factory` / `cormart_staging` |
| Resultado | ☐ Aprobado ☐ Con observaciones ☐ Fallido |

---

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
  - _Notas: —_
- [x] `FinancingPipelineWidget` muestra todos los financiamientos por estado: Solicitados, Desembolsados, **Abonados**, Cobrados
  - _Notas: —_
- [x] ~~`ActiveFinancingsWidget`~~ — Widget eliminado (ya no es requerido)
  - _Notas: —_
- [x] `PendingTransactionsWidget` muestra transacciones pendientes de confirmar con botón "Confirmar"
  - _Notas: —_

### Compañías

- [x] Ver listado de compañías con columnas: nombre, RNC, contacto, email, teléfono, cantidad de clientes, estado
  - _Notas: —_
- [x] Crear nueva compañía
  - _Notas: —_
- [x] Editar compañía existente
  - _Notas: —_
- [x] Activar/desactivar compañía
  - _Notas: —_
- [x] Filtro ternario: activa/inactiva/todas
  - _Notas: —_

### Clientes (Deudores)

- [x] Ver clientes de todas las compañías con estadísticas (cantidad financiamientos, monto total, comisiones)
  - _Notas: —_
- [x] Crear cliente asignado a una compañía
  - _Notas: —_
- [x] El campo compañía filtra correctamente
  - _Notas: —_

### Financiamientos

- [x] Ver todos los financiamientos de todas las compañías
  - _Notas: —_
- [x] Crear financiamiento: comisión y monto neto se calculan automáticamente
  - _Notas: —_
- [x] El campo `amount` muestra formato de moneda `RD$` con separadores de miles
  - _Notas: Resuelto — `$money()` mask aplicado_
- [x] El código `FN000001` se genera al guardar
  - _Notas: —_
- [x] Selector de clientes se filtra dinámicamente al cambiar la compañía
  - _Notas: Verificado — selector ya filtraba compañías activas_
- [x] Fecha de vencimiento se calcula automáticamente según plazo en días
  - _Notas: —_
- [x] Adjuntar documento (OC o Factura, PDF/JPG/PNG) y verificar que se puede visualizar
  - _Notas: —_
- [x] **Vista detalle**: muestra datos del financiamiento (código, estado, montos, fechas)
  - _Notas: —_
- [x] **Vista detalle**: lista de documentos con enlaces de preview
  - _Notas: —_
- [x] **Vista detalle**: transacciones asociadas con enlaces clickeables al detalle de cada transacción
  - _Notas: —_
- [x] **Vista detalle**: acciones contextuales disponibles según estado (Desembolsar, Cobrar, Cancelar)
  - _Notas: —_
- [x] Acción "Desembolsar" disponible en financiamientos con estado `solicitado`
  - _Notas: —_
- [x] Acción "Cobrar" disponible en financiamientos con estado `desembolsado` o `partially_collected`
  - _Notas: —_
- [x] Cancelar financiamiento: campo motivo de cancelación es obligatorio
  - _Notas: —_
- [x] Financiamiento solo se puede editar en estado `solicitado`
  - _Notas: —_
- [x] Acción en lote: seleccionar varios de la **misma compañía** → desembolsar en lote
  - _Notas: —_
- [x] Acción en lote: seleccionar financiamientos de **compañías distintas** → debe bloquearse o alertar
  - _Notas: —_

### Financiamientos — Cobros parciales (abonos)

- [x] Crear cobro parcial (abono) sobre un financiamiento `desembolsado` → estado cambia a `partially_collected`
  - _Notas: —_
- [x] El campo `collected_amount` acumula el monto de cada abono correctamente
  - _Notas: —_
- [x] Crear segundo abono que completa el saldo → estado pasa a `collected`
  - _Notas: —_
- [x] `collection_period` y `collected_at` se asignan **solo al completar** el cobro total (último abono)
  - _Notas: —_
- [x] El pipeline widget refleja el estado `Abonados` con el conteo y monto pendiente correctos
  - _Notas: —_

### Transacciones

- [x] Ver todas las transacciones con código `TX000001` auto-generado
  - _Notas: —_
- [x] El código TX aparece en la tabla y en la vista detalle
  - _Notas: —_
- [x] Crear transacción de **Desembolso**: selector de compañía → selector de financiamientos solicitados → monto calculado automáticamente (suma de `transfer_amount`)
  - _Notas: —_
- [x] Crear transacción de **Cobro completo**: financiamientos desembolsados → monto = balance restante
  - _Notas: Resuelto — campo disabled y read-only cuando hay múltiples financiamientos_
- [x] Crear transacción de **Abono parcial**: permite ingresar monto menor al balance restante
  - _Notas: —_
- [x] El campo `amount` muestra formato de moneda `RD$` con separadores de miles
  - _Notas: —_
- [x] Selector de banco, número de transacción, fecha y notas funcionan correctamente
  - _Notas: Resuelto — filtro por banco + columna sortable_
- [x] Transacciones creadas por operator se confirman automáticamente
  - _Notas: —_
- [x] Vista detalle: muestra datos de la transacción y financiamientos vinculados con enlaces clickeables
  - _Notas: Resuelto — comisión solo visible en desembolsos_
- [x] Filtro por estado: pendiente/confirmada
  - _Notas: —_
- [x] Acción "Confirmar" disponible para transacciones pendientes
  - _Notas: —_

### Cuentas por Cobrar

- [x] Muestra financiamientos en estado `desembolsado`
  - _Notas: —_
- [x] Los widgets de stats muestran: Total por Cobrar, Al Día, Vencidos (con monto), Cobrado en [Mes]
  - _Notas: —_
- [x] Acción en lote "Cobrar seleccionados" redirige a crear transacción de cobro con los IDs preseleccionados
  - _Notas: —_

### Cuentas por Pagar

- [x] Muestra solo financiamientos en estado `solicitado`
  - _Notas: —_
- [x] Los widgets de stats muestran: Total por Desembolsar, Monto Total Solicitado, Desembolsos del Mes, Antigüedad Promedio
  - _Notas: Resuelto — widget actualizado_
- [x] Acción en lote "Desembolsar seleccionados" redirige correctamente
  - _Notas: —_

### Cierre Mensual

- [x] Seleccionar un período con financiamientos cobrados → preview muestra todos los cálculos
  - _Notas: Resuelto — dark mode + padding corregidos_
- [x] Verificar que la cascada de distribución es matemáticamente correcta (diff = 0)
  - _Notas: —_
- [x] Ejecutar cierre → se crea el registro histórico
  - _Notas: —_
- [x] Intentar cerrar el mismo período dos veces → debe bloquearse con mensaje claro
  - _Notas: —_
- [x] El historial de cierres muestra: período, comisiones, fijo, reserva, naturaleza, disponible para capital, ejecutado por, fecha
  - _Notas: —_

### Parámetros

- [x] Los valores actuales se muestran sin ceros innecesarios (`5.00`, no `5.0000`)
  - _Notas: —_
- [x] Modificar un parámetro y guardar → notificación de éxito
  - _Notas: —_
- [x] Verificar que el cambio quedó en el historial de parámetros
  - _Notas: —_

### Miembros del Fondo

- [x] Crear miembro tipo Capital: muestra campos contribución (formato `RD$`) y porcentaje del fondo
  - _Notas: Resuelto — % auto-calculado y read-only_
- [x] Crear miembro tipo Naturaleza: campos contribución y porcentaje **no aparecen**
  - _Notas: —_
- [x] El campo `contribution` muestra formato de moneda `RD$` con separadores de miles
  - _Notas: —_
- [x] Editar miembro: activar/desactivar, modificar fechas
  - _Notas: —_

### Usuarios

- [x] Crear usuario con cada rol
  - _Notas: Resuelto — selector de miembro del fondo para `member` (requerido)_
- [x] Asignar compañía a un `company_user`
  - _Notas: Resuelto — selector de compañía condicional_
- [x] Editar contraseña de un usuario
  - _Notas: —_
- [x] Badges de rol con colores: super_admin=rojo, operator=naranja, member=verde, company_user=azul
  - _Notas: Resuelto_

---

## Rol 2 — `operator`

**Menú esperado:** Operaciones completas, sin Usuarios, sin Parámetros, sin Cierre Mensual.

### Escritorio

- [ ] `CapitalSummaryWidget` **sí visible**
  - _Notas: —_
- [ ] `FinancingPipelineWidget` visible con todos los estados
  - _Notas: —_
- [ ] `PendingTransactionsWidget` visible con botón "Confirmar"
  - _Notas: —_

### Accesos restringidos

- [ ] **No aparece** Cierre Mensual en el menú
  - _Notas: —_
- [ ] **No aparece** Parámetros en el menú
  - _Notas: —_
- [ ] **No aparece** Usuarios en el menú
  - _Notas: —_
- [ ] Intentar acceder por URL directa → debe redirigir o mostrar error de permisos
  - _Notas: —_

### Operaciones

- [ ] Puede ver y crear financiamientos de cualquier compañía
  - _Notas: —_
- [ ] Puede crear transacciones de desembolso y cobro
  - _Notas: —_
- [ ] Transacciones creadas por operator se confirman automáticamente
  - _Notas: —_
- [ ] Puede confirmar transacciones de cobro pendientes creadas por `company_user`
  - _Notas: —_
- [ ] Puede crear cobros parciales (abonos) y verificar acumulación de `collected_amount`
  - _Notas: —_
- [ ] Campos monetarios muestran formato `RD$` correctamente
  - _Notas: —_

---

## Rol 3 — `member`

**Menú esperado:** Solo lectura. Financiamientos y Cierres Mensuales.

### Escritorio

- [ ] `CapitalSummaryWidget` **sí visible**
  - _Notas: —_
- [ ] `FinancingPipelineWidget` visible (incluye estado Abonados)
  - _Notas: —_
- [ ] `PendingTransactionsWidget` **no visible**
  - _Notas: —_

### Accesos

- [ ] Puede **ver** financiamientos pero no crear ni editar (sin botón "Nuevo")
  - _Notas: —_
- [ ] Puede acceder a la **vista detalle** de financiamientos (solo lectura, sin acciones)
  - _Notas: —_
- [ ] Puede **ver** historial de cierres mensuales
  - _Notas: —_
- [ ] **No aparece** Transacciones en el menú
  - _Notas: —_
- [ ] **No aparece** Compañías, Clientes, Usuarios, Parámetros
  - _Notas: —_
- [ ] Intentar acceder por URL directa a crear financiamiento → error de permisos
  - _Notas: —_

---

## Rol 4 — `company_user`

Probar con `cormart@test.com` **y** con `ysetech@test.com` por separado para verificar aislamiento.

**Menú esperado:** Solo Operaciones acotadas a su compañía.

### Escritorio

- [ ] `CapitalSummaryWidget` **no visible**
  - _Notas: —_
- [ ] `FinancingPipelineWidget` muestra solo financiamientos de su compañía (incluye Abonados)
  - _Notas: —_

### Aislamiento de datos

- [ ] En Financiamientos: solo ve registros de su compañía
  - _Notas: —_
- [ ] En Clientes: solo ve clientes de su compañía
  - _Notas: —_
- [ ] Al crear un financiamiento, el selector de clientes solo ofrece los de su compañía
  - _Notas: —_
- [ ] Iniciar sesión con `cormart@test.com`: no ve datos de Ysetech
  - _Notas: —_
- [ ] Iniciar sesión con `ysetech@test.com`: no ve datos de Cormart
  - _Notas: —_

### Flujo de cobro

- [ ] Puede crear solicitud de cobro (transacción tipo `collection`)
  - _Notas: —_
- [ ] La transacción queda en estado `pendiente` esperando confirmación del operator
  - _Notas: —_
- [ ] Puede crear cobros parciales (abonos) sobre financiamientos de su compañía
  - _Notas: —_
- [ ] **No puede** crear transacciones de desembolso
  - _Notas: —_
- [ ] Campos monetarios muestran formato `RD$` correctamente
  - _Notas: —_

### Cuentas por Cobrar (company_user)

- [ ] `CuentasPorCobrarStatsWidget` muestra datos filtrados por su compañía
  - _Notas: —_
- [ ] Métricas: Total por Cobrar, Al Día, Vencidos, Cobrado en [Mes]
  - _Notas: —_

### Accesos bloqueados

- [ ] **No aparece** Compañías, Usuarios, Parámetros, Cierre Mensual en el menú
  - _Notas: —_
- [ ] **No puede** desembolsar financiamientos (sin botón de acción)
  - _Notas: —_

---

## Flujos completos

### Flujo A — Ciclo completo de un financiamiento (cobro total)

- [ ] `company_user` crea financiamiento → estado `solicitado`, código `FN` auto-generado
  - _Notas: —_
- [ ] `operator` lo desembolsa desde Cuentas por Pagar → estado `desembolsado`, transacción con código `TX` auto-generado
  - _Notas: —_
- [ ] `company_user` solicita cobro desde Cuentas por Cobrar → transacción en `pendiente`
  - _Notas: —_
- [ ] `operator` confirma la transacción → estado `cobrado`, `collected_at` se asigna
  - _Notas: —_
- [ ] `super_admin` ejecuta cierre mensual → verifica que el financiamiento aparece en el cálculo
  - _Notas: —_
- [ ] Verificar distribución por miembro en el historial de cierres
  - _Notas: —_

### Flujo B — Desembolso en lote

- [ ] Crear 3 financiamientos de la misma compañía en estado `solicitado`
  - _Notas: —_
- [ ] Seleccionarlos en lote desde Cuentas por Pagar → acción Desembolsar
  - _Notas: —_
- [ ] Crear la transacción de desembolso con los 3 vinculados → monto = suma de `transfer_amount`
  - _Notas: —_
- [ ] Verificar que los 3 pasan a `desembolsado`
  - _Notas: —_
- [ ] Abrir la transacción creada → vista detalle muestra los 3 financiamientos con sus enlaces
  - _Notas: —_

### Flujo C — Rechazo de lote entre compañías

- [ ] Seleccionar financiamientos de Cormart y Ysetech mezclados
  - _Notas: —_
- [ ] Intentar acción en lote → el sistema debe rechazarlo o alertar
  - _Notas: —_

### Flujo D — Ciclo con cobros parciales (abonos)

- [ ] `operator` desembolsa un financiamiento de RD$ 100,000 → estado `desembolsado`
  - _Notas: —_
- [ ] `company_user` crea cobro parcial por RD$ 40,000 → transacción `pendiente`
  - _Notas: —_
- [ ] `operator` confirma → financiamiento pasa a `partially_collected`, `collected_amount` = 40,000
  - _Notas: —_
- [ ] `company_user` crea segundo abono por RD$ 60,000 → transacción `pendiente`
  - _Notas: —_
- [ ] `operator` confirma → financiamiento pasa a `collected`, `collected_amount` = 100,000
  - _Notas: —_
- [ ] `collection_period` y `collected_at` se asignaron **solo en el último abono**
  - _Notas: —_
- [ ] El financiamiento ya no aparece en Cuentas por Cobrar
  - _Notas: —_

### Flujo E — Formateo de moneda en formularios

- [ ] Abrir formulario de Nuevo Financiamiento → ingresar monto → se muestra con formato `RD$` y separadores de miles
  - _Notas: —_
- [ ] Guardar → el valor se almacenó correctamente (sin prefijo `RD$`)
  - _Notas: —_
- [ ] Abrir formulario de Nueva Transacción → verificar formato en campo monto
  - _Notas: —_
- [ ] Abrir formulario de Miembro del Fondo → verificar formato en campo contribución
  - _Notas: —_
- [ ] Editar cada registro guardado → los valores se cargan con formato correcto
  - _Notas: —_

---

## Observaciones generales

> _Escribe aquí cualquier hallazgo no cubierto por los casos anteriores._
