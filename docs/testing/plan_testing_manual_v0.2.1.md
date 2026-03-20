# Plan de Testing Manual — Cormart Factory v0.2.1

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

## Rol 1 — `super_admin`

**Menú esperado:** Operaciones, Cierre Mensual, Administración completa.

### Escritorio

- [ ] `CapitalSummaryWidget` muestra: Capital Total, Capital en Calle, Capital Disponible, Comisiones del Mes, Proyección de Comisiones, % de Cobro
  - _Notas: —_
- [ ] `FinancingPipelineWidget` muestra todos los financiamientos por estado
  - _Notas: —_
- [ ] `ActiveFinancingsWidget` muestra la tabla de financiamientos activos
  - _Notas: —_
- [ ] `PendingTransactionsWidget` muestra transacciones pendientes de confirmar
  - _Notas: —_

### Compañías

- [ ] Ver listado de compañías
  - _Notas: —_
- [ ] Crear nueva compañía
  - _Notas: —_
- [ ] Editar compañía existente
  - _Notas: —_
- [ ] Activar/desactivar compañía
  - _Notas: —_

### Clientes

- [ ] Ver clientes de todas las compañías
  - _Notas: —_
- [ ] Crear cliente asignado a una compañía
  - _Notas: —_
- [ ] El campo compañía filtra correctamente
  - _Notas: —_

### Financiamientos

- [ ] Ver todos los financiamientos de todas las compañías
  - _Notas: —_
- [ ] Crear financiamiento: comisión y monto neto se calculan automáticamente
  - _Notas: —_
- [ ] El código `FN000001` se genera al guardar
  - _Notas: —_
- [ ] Adjuntar documento PDF y verificar que se puede visualizar desde el portal
  - _Notas: —_
- [ ] Acción "Desembolsar" disponible en financiamientos con estado `solicitado`
  - _Notas: —_
- [ ] Acción "Cobrar" disponible en financiamientos con estado `desembolsado`
  - _Notas: —_
- [ ] Cancelar financiamiento: campo motivo de cancelación es obligatorio
  - _Notas: —_
- [ ] Acción en lote: seleccionar varios de la **misma compañía** → desembolsar en lote
  - _Notas: —_
- [ ] Acción en lote: seleccionar financiamientos de **compañías distintas** → debe bloquearse o alertar
  - _Notas: —_

### Transacciones

- [ ] Ver todas las transacciones
  - _Notas: —_
- [ ] Crear transacción de **Desembolso**: selector de compañía → selector de financiamientos solicitados → monto calculado automáticamente
  - _Notas: —_
- [ ] Crear transacción de **Cobro**: misma lógica con financiamientos desembolsados
  - _Notas: —_
- [ ] Vista detalle: muestra lista de financiamientos vinculados con enlaces clickeables
  - _Notas: —_
- [ ] Los enlaces en la vista detalle abren el financiamiento correcto
  - _Notas: —_

### Cuentas por Cobrar

- [ ] Muestra solo financiamientos en estado `desembolsado`
  - _Notas: —_
- [ ] Los widgets de stats muestran totales correctos
  - _Notas: —_
- [ ] Acción en lote "Cobrar seleccionados" redirige a crear transacción de cobro con los IDs preseleccionados
  - _Notas: —_

### Cuentas por Pagar

- [ ] Muestra solo financiamientos en estado `solicitado`
  - _Notas: —_
- [ ] Los widgets de stats muestran totales correctos
  - _Notas: —_
- [ ] Acción en lote "Desembolsar seleccionados" redirige correctamente
  - _Notas: —_

### Cierre Mensual

- [ ] Seleccionar un período con financiamientos cobrados → preview muestra todos los cálculos
  - _Notas: —_
- [ ] Verificar que la cascada de distribución es matemáticamente correcta (diff = 0)
  - _Notas: —_
- [ ] Ejecutar cierre → se crea el registro histórico
  - _Notas: —_
- [ ] Intentar cerrar el mismo período dos veces → debe bloquearse con mensaje claro
  - _Notas: —_

### Parámetros

- [ ] Los valores actuales se muestran sin ceros (`5.00`, no `5.0000`)
  - _Notas: —_
- [ ] Modificar un parámetro y guardar → notificación de éxito
  - _Notas: —_
- [ ] Verificar que el cambio quedó en el historial de parámetros
  - _Notas: —_

### Usuarios

- [ ] Crear usuario con cada rol
  - _Notas: —_
- [ ] Asignar compañía a un `company_user`
  - _Notas: —_
- [ ] Editar contraseña de un usuario
  - _Notas: —_

---

## Rol 2 — `operator`

**Menú esperado:** Operaciones completas, sin Usuarios, sin Parámetros, sin Cierre Mensual.

### Escritorio

- [ ] `CapitalSummaryWidget` **sí visible**
  - _Notas: —_
- [ ] Mismos widgets que super_admin
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
- [ ] Puede confirmar transacciones de cobro pendientes creadas por `company_user`
  - _Notas: —_

---

## Rol 3 — `member`

**Menú esperado:** Solo lectura. Financiamientos y Cierres Mensuales.

### Escritorio

- [ ] `CapitalSummaryWidget` **sí visible**
  - _Notas: —_
- [ ] `FinancingPipelineWidget` visible
  - _Notas: —_
- [ ] `ActiveFinancingsWidget` visible
  - _Notas: —_
- [ ] `PendingTransactionsWidget` **no visible**
  - _Notas: —_

### Accesos

- [ ] Puede **ver** financiamientos pero no crear ni editar (sin botón "Nuevo")
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
- [ ] `FinancingPipelineWidget` muestra solo financiamientos de su compañía
  - _Notas: —_
- [ ] `ActiveFinancingsWidget` muestra solo financiamientos activos de su compañía
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
- [ ] **No puede** crear transacciones de desembolso
  - _Notas: —_

### Accesos bloqueados

- [ ] **No aparece** Compañías, Usuarios, Parámetros, Cierre Mensual en el menú
  - _Notas: —_
- [ ] **No puede** desembolsar financiamientos (sin botón de acción)
  - _Notas: —_

---

## Flujos completos

### Flujo A — Ciclo completo de un financiamiento

- [ ] `company_user` crea financiamiento → estado `solicitado`
  - _Notas: —_
- [ ] `operator` lo desembolsa desde Cuentas por Pagar → estado `desembolsado`
  - _Notas: —_
- [ ] `company_user` solicita cobro desde Cuentas por Cobrar → transacción en `pendiente`
  - _Notas: —_
- [ ] `operator` confirma la transacción → estado `cobrado`
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
- [ ] Crear la transacción de desembolso con los 3 vinculados
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

---

## Observaciones generales

> _Escribe aquí cualquier hallazgo no cubierto por los casos anteriores._
