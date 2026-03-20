# Plan de Testing Manual — Cormart Factory v0.5.0

> Versión: v0.5.0 — Gastos, Impuesto de Desembolso, Proveedores

## Resumen de la sesión

| Campo | Valor |
|---|---|
| Tester | — |
| Fecha | — |
| Instancia | `cormart_factory` / `cormart_staging` |
| Rol | `super_admin` |
| Resultado | ☑ Aprobado con observaciones para versiones futuras |

---

## Preparación

Verificar que las migraciones se aplicaron:

```bash
php artisan migrate:status
```

Verificar que el parámetro `tax_pct` existe:

```bash
php artisan tinker --execute="echo \App\Models\Parameter::where('key','tax_pct')->value('value');"
```

Debe retornar `0.15`.

Verificar que el proveedor DGII existe:

```bash
php artisan tinker --execute="echo \App\Models\Supplier::where('name','DGII')->exists();"
```

Debe retornar `1` (true).

---

## 1. Parámetros

- [x] Ir a **Cierre Mensual → Parámetros**
  - _Notas: —_
- [x] Verificar que aparece el campo **Impuesto sobre Desembolsos** con valor `0.15` y helper text explicativo
  - _Notas: —_
- [x] Cambiar `tax_pct` a `0.10` y guardar → notificación de éxito y valor persiste al recargar
  - _Notas: —_
- [x] Restaurar `tax_pct` a `0.15` y guardar
  - _Notas: —_

---

## 2. Proveedores

- [x] Ir a **Transacciones → Nueva transacción**, seleccionar tipo **Gasto Operativo**
  - _Notas: —_
- [x] Verificar que aparece el campo **Proveedor** con selector searchable
  - _Notas: —_
- [x] Hacer clic en "Crear" dentro del selector → se abre modal con campos Nombre y RNC
  - _Notas: —_
- [x] Crear un proveedor de prueba (ej: `Ferretería Central`, RNC `123456789`) → queda seleccionado automáticamente
  - _Notas: —_
- [x] Verificar que DGII aparece como opción en el selector
  - _Notas: —_
- [x] Verificar que el campo Proveedor **no aparece** al seleccionar tipo Desembolso o Cobro
  - _Notas: —_

---

## 3. Gasto manual

- [x] Seleccionar tipo **Gasto Operativo** en nueva transacción
  - _Notas: —_
- [x] Verificar que desaparecen los campos: Compañía y Financiamientos; y que **sí aparecen**: Proveedor, Monto, Banco, No. Transacción, Fecha, Notas
  - _Notas: —_
- [x] Intentar guardar sin llenar Notas → debe mostrar error de validación
  - _Notas: —_
- [x] Intentar guardar sin Proveedor → debe mostrar error de validación
  - _Notas: —_
- [x] Llenar todos los campos: Proveedor, Monto (`500.00`), Banco, No. Transacción, Fecha, Notas y guardar
  - _Notas: —_
- [x] Verificar registro en la tabla: badge rojo **Gasto**, estado **Confirmada**, columna Proveedor muestra el nombre
  - _Notas: —_
- [x] Abrir vista detalle → muestra Proveedor en vez de Compañía; sección "Financiamientos Asociados" **no aparece**
  - _Notas: —_
- [x] Verificar que Banco y No. Transacción se muestran en la vista detalle
  - _Notas: —_ 
- [x] Aplicar filtro **Tipo → Gasto** en el listado → solo muestra transacciones `expense`
  - _Notas: —_

---

## 4. Auto-impuesto en desembolso

> Requiere un financiamiento en estado `solicited`.

- [x] Crear un desembolso para un financiamiento
  - _Notas: —_
- [x] Ir al listado de transacciones → aparece automáticamente una segunda transacción de tipo **Gasto**
  - _Notas: —_
- [x] Abrir la transacción de gasto automática y verificar:
  - _Notas: —_
  - [x] Monto = `monto_desembolso × 0.15 / 100`
    - _Notas: —_
  - [x] Proveedor = **DGII**
    - _Notas: —_
  - [x] Banco = mismo banco del desembolso padre
    - _Notas: —_
  - [x] ~~No. Transacción = mismo del desembolso padre~~ (corregido)
    - _Notas: Se corrigió. Ahora usa el TX code del padre._
  - [x] No. Transacción = código TX del desembolso padre (ej: `TX000012`), **no** el número de transacción del padre
    - _Notas: —_
  - [x] Notas comienzan con **"Impuesto por transacción"**
    - _Notas: —_
  - [x] Estado = **Confirmada**
    - _Notas: —_
- [x] Cambiar `tax_pct` a `0` en Parámetros y crear otro desembolso → **no** se genera gasto automático
  - _Notas: —_
- [x] Restaurar `tax_pct` a `0.15`
  - _Notas: —_

---

## 5. Dashboard — Widgets

- [x] Ir al **Dashboard** → widget **Gastos del Mes** visible con monto correcto
  - _Notas: —_
- [x] Verificar que el monto coincide con la suma de gastos confirmados del mes en curso
  - _Notas: —_
- [x] ~~Verificar que los widgets se distribuyen en filas de 3 columnas~~ (corregido)
  - _Notas: Se corrigió. Ahora CapitalSummary y FinancingPipeline usan 4 columnas._
- [ ] Verificar que los widgets se distribuyen en filas de **4, 3, 4** columnas (Capital 4+3, Pipeline 4)
  - _Notas: —_ No, ahora todos ocupan una sola columna. Deja la distribución por defecto y pasa la línea de las comisiones y cobros al final.

---

## 6. Cierre mensual — Cascada de distribución

> Usar un período con al menos un financiamiento cobrado y gastos registrados.

- [x] Seleccionar el período de prueba y calcular
  - _Notas: —_
- [x] Verificar que la cascada muestra: Comisiones → Gastos del período → Base real de ganancias → Rendimiento fijo → Ganancia neta → Reserva → Post-reserva → Naturaleza → Disponible para capital → Verificación
  - _Notas: —_
- [x] Verificar que **Base real de ganancias** = Comisiones − Gastos
  - _Notas: —_
- [x] Verificar que los porcentajes se muestran con **2 decimales** (ej: `3.00%`, `20.00%`, `50.00%`), no `3.0000%`
  - _Notas: —_
- [x] Calcular un período **sin gastos** → "Gastos del período" = `0.00`; "Base real" = "Comisiones"
  - _Notas: —_
- [x] Ejecutar el cierre en un período con gastos
  - _Notas: —_
- [x] Verificar en BD que `monthly_closings.total_expenses` tiene el valor correcto
  - _Notas: —_

---

## 7. Tabla de transacciones — columnas y datos

- [x] Verificar que la columna **Proveedor** aparece en la tabla de transacciones
  - _Notas: —_
- [x] Para transacciones de tipo desembolso/cobro, la columna Proveedor muestra **—**
  - _Notas: —_
- [x] Para transacciones de tipo gasto, la columna Proveedor muestra el nombre del proveedor
  - _Notas: —_
- [x] La columna Deudor muestra **—** para transacciones de tipo gasto
  - _Notas: —_

---

## 8. Unicidad de No. Transacción

- [x] Intentar crear una transacción con un **No. Transacción ya existente** → debe mostrar error de validación en el formulario
  - _Notas: —_
- [x] Verificar en BD que el constraint UNIQUE existe: `SHOW INDEX FROM transactions WHERE Column_name = 'transaction_number'`
  - _Notas: —_
- [x] Crear un desembolso → verificar que la transacción de impuesto automática tiene No. Transacción = TX code del padre (ej: `TX000015`) y **no** el número de transacción del desembolso
  - _Notas: —_

---

## 9. Filtro de Proveedor

- [x] En el listado de transacciones, abrir filtros → verificar que aparece **Proveedor** como opción
  - _Notas: —_
- [x] Filtrar por un proveedor específico → solo muestra transacciones de ese proveedor
  - _Notas: —_
- [x] Limpiar filtro → vuelven a aparecer todas las transacciones
  - _Notas: —_

---

## Observaciones generales

> _Escribe aquí cualquier hallazgo no cubierto por los casos anteriores._ En la acción Toggle Columns de la lista de transacciones, me gustaría poder tener todos los campos, lo mismo en cada otra lista dentro del sistema. (Pendiente para versión futura).

El idioma de esta instancia sale en inglés y en español, necesito que todo esté en español (Pendiente para una versión futura).

En el listado tenemos una columna para deudor y proveedor, pero en futuras versiones vamos a tener las transacciones de desembolso de ganancias a los miembros, lo que requeriría una tercera columna. Así que lo mejor es unificar en una misma columna el destinatario de las transacciones. (Pendiente para una versión futura).
