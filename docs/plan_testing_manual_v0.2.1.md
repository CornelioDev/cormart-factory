# Plan de Testing Manual — Cormart Factory v0.2.1

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
- [ ] `FinancingPipelineWidget` muestra todos los financiamientos por estado
- [ ] `ActiveFinancingsWidget` muestra la tabla de financiamientos activos
- [ ] `PendingTransactionsWidget` muestra transacciones pendientes de confirmar

### Compañías
- [ ] Ver listado de compañías
- [ ] Crear nueva compañía
- [ ] Editar compañía existente
- [ ] Activar/desactivar compañía

### Clientes
- [ ] Ver clientes de todas las compañías
- [ ] Crear cliente asignado a una compañía
- [ ] El campo compañía filtra correctamente

### Financiamientos
- [ ] Ver todos los financiamientos de todas las compañías
- [ ] Crear financiamiento: comisión y monto neto se calculan automáticamente
- [ ] El código `FN000001` se genera al guardar
- [ ] Adjuntar documento PDF y verificar que se puede visualizar desde el portal
- [ ] Acción "Desembolsar" disponible en financiamientos con estado `solicitado`
- [ ] Acción "Cobrar" disponible en financiamientos con estado `desembolsado`
- [ ] Cancelar financiamiento: campo motivo de cancelación es obligatorio
- [ ] Acción en lote: seleccionar varios de la **misma compañía** → desembolsar en lote
- [ ] Acción en lote: seleccionar financiamientos de **compañías distintas** → debe bloquearse o alertar

### Transacciones
- [ ] Ver todas las transacciones
- [ ] Crear transacción de **Desembolso**: selector de compañía → selector de financiamientos solicitados → monto calculado automáticamente
- [ ] Crear transacción de **Cobro**: misma lógica con financiamientos desembolsados
- [ ] Vista detalle: muestra lista de financiamientos vinculados con enlaces clickeables
- [ ] Los enlaces en la vista detalle abren el financiamiento correcto

### Cuentas por Cobrar
- [ ] Muestra solo financiamientos en estado `desembolsado`
- [ ] Los widgets de stats muestran totales correctos
- [ ] Acción en lote "Cobrar seleccionados" redirige a crear transacción de cobro con los IDs preseleccionados

### Cuentas por Pagar
- [ ] Muestra solo financiamientos en estado `solicitado`
- [ ] Los widgets de stats muestran totales correctos
- [ ] Acción en lote "Desembolsar seleccionados" redirige correctamente

### Cierre Mensual
- [ ] Seleccionar un período con financiamientos cobrados → preview muestra todos los cálculos
- [ ] Verificar que la cascada de distribución es matemáticamente correcta (diff = 0)
- [ ] Ejecutar cierre → se crea el registro histórico
- [ ] Intentar cerrar el mismo período dos veces → debe bloquearse con mensaje claro

### Parámetros
- [ ] Los valores actuales se muestran sin ceros (`5.00`, no `5.0000`)
- [ ] Modificar un parámetro y guardar → notificación de éxito
- [ ] Verificar que el cambio quedó en el historial de parámetros

### Usuarios
- [ ] Crear usuario con cada rol
- [ ] Asignar compañía a un `company_user`
- [ ] Editar contraseña de un usuario

---

## Rol 2 — `operator`

**Menú esperado:** Operaciones completas, sin Usuarios, sin Parámetros, sin Cierre Mensual.

### Escritorio
- [ ] `CapitalSummaryWidget` **sí visible**
- [ ] Mismos widgets que super_admin

### Accesos restringidos
- [ ] **No aparece** Cierre Mensual en el menú
- [ ] **No aparece** Parámetros en el menú
- [ ] **No aparece** Usuarios en el menú
- [ ] Intentar acceder por URL directa → debe redirigir o mostrar error de permisos

### Operaciones
- [ ] Puede ver y crear financiamientos de cualquier compañía
- [ ] Puede crear transacciones de desembolso y cobro
- [ ] Puede confirmar transacciones de cobro pendientes creadas por `company_user`

---

## Rol 3 — `member`

**Menú esperado:** Solo lectura. Financiamientos y Cierres Mensuales.

### Escritorio
- [ ] `CapitalSummaryWidget` **sí visible**
- [ ] `FinancingPipelineWidget` visible
- [ ] `ActiveFinancingsWidget` visible
- [ ] `PendingTransactionsWidget` **no visible**

### Accesos
- [ ] Puede **ver** financiamientos pero no crear ni editar (sin botón "Nuevo")
- [ ] Puede **ver** historial de cierres mensuales
- [ ] **No aparece** Transacciones en el menú
- [ ] **No aparece** Compañías, Clientes, Usuarios, Parámetros
- [ ] Intentar acceder por URL directa a crear financiamiento → error de permisos

---

## Rol 4 — `company_user`

Probar con `cormart@test.com` **y** con `ysetech@test.com` por separado para verificar aislamiento.

**Menú esperado:** Solo Operaciones acotadas a su compañía.

### Escritorio
- [ ] `CapitalSummaryWidget` **no visible** ← fix v0.2.1
- [ ] `FinancingPipelineWidget` muestra solo financiamientos de su compañía
- [ ] `ActiveFinancingsWidget` muestra solo financiamientos activos de su compañía

### Aislamiento de datos
- [ ] En Financiamientos: solo ve registros de su compañía
- [ ] En Clientes: solo ve clientes de su compañía
- [ ] Al crear un financiamiento, el selector de clientes solo ofrece los de su compañía
- [ ] Iniciar sesión con `cormart@test.com`: no ve datos de Ysetech
- [ ] Iniciar sesión con `ysetech@test.com`: no ve datos de Cormart

### Flujo de cobro
- [ ] Puede crear solicitud de cobro (transacción tipo `collection`)
- [ ] La transacción queda en estado `pendiente` esperando confirmación del operator
- [ ] **No puede** crear transacciones de desembolso

### Accesos bloqueados
- [ ] **No aparece** Compañías, Usuarios, Parámetros, Cierre Mensual en el menú
- [ ] **No puede** desembolsar financiamientos (sin botón de acción)

---

## Flujos completos

### Flujo A — Ciclo completo de un financiamiento

1. `company_user` crea financiamiento → estado `solicitado`
2. `operator` lo desembolsa desde Cuentas por Pagar → estado `desembolsado`
3. `company_user` solicita cobro desde Cuentas por Cobrar → transacción en `pendiente`
4. `operator` confirma la transacción → estado `cobrado`
5. `super_admin` ejecuta cierre mensual → verifica que el financiamiento aparece en el cálculo
6. Verificar distribución por miembro en el historial de cierres

### Flujo B — Desembolso en lote

1. Crear 3 financiamientos de la misma compañía en estado `solicitado`
2. Seleccionarlos en lote desde Cuentas por Pagar → acción Desembolsar
3. Crear la transacción de desembolso con los 3 vinculados
4. Verificar que los 3 pasan a `desembolsado`
5. Abrir la transacción creada → vista detalle muestra los 3 financiamientos con sus enlaces

### Flujo C — Rechazo de lote entre compañías

1. Seleccionar financiamientos de Cormart y Ysetech mezclados
2. Intentar acción en lote → el sistema debe rechazarlo o alertar

---

## Notas para recopilar requerimientos

Durante las pruebas, documentar:

- **Flujos poco intuitivos**: pasos que confunden o requieren demasiados clics
- **Información faltante**: datos que se necesitan ver en una vista pero no están
- **Filtros o columnas**: columnas útiles que faltan en las tablas
- **Permisos**: acciones que un rol debería poder hacer y no puede, o viceversa
- **Validaciones**: casos borde que el sistema no maneja correctamente
- **Rendimiento**: pantallas o acciones que cargan lento
