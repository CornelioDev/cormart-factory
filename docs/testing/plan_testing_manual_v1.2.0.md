# Plan de Testing Manual — Cormart Factory v1.2.0

> Versión: v1.2.0 — Comisión por tramos de 30 días + Mora por financiamientos vencidos

## Resumen de la sesión

| Campo     | Valor                                 |
| --------- | ------------------------------------- |
| Tester    | —                                     |
| Fecha     | —                                     |
| Instancia | `cormart_factory` (desarrollo)        |
| Rol       | `super_admin`, `operator`             |
| Resultado | —                                     |

## Preparación

Verificar que las migraciones están al día (debe incluir `add_late_fee_fields_to_financings_table`):

```bash
php artisan migrate:status
```

Verificar que el nuevo parámetro existe:

```bash
php artisan tinker --execute="
echo 'late_fee_pct: ' . \App\Models\Parameter::where('key', 'late_fee_pct')->value('value');
echo PHP_EOL . 'commission_pct: ' . \App\Models\Parameter::where('key', 'commission_pct')->value('value');
echo PHP_EOL . 'Total parámetros: ' . \App\Models\Parameter::count();
"
```

Verificar que los tests automatizados pasan:

```bash
php artisan test
```

---

## 1. Comisión por tramos de 30 días

> Crear financiamientos con distintos plazos y verificar que la comisión escala correctamente. Usar monto RD$ 100,000 para facilitar el cálculo.

- [ ] Crear financiamiento con plazo **15 días** → comisión = RD$ 5,000.00 (5%)
  - _Notas: —_

- [ ] Crear financiamiento con plazo **30 días** → comisión = RD$ 5,000.00 (5%)
  - _Notas: —_

- [ ] Crear financiamiento con plazo **31 días** → comisión = RD$ 10,000.00 (10%)
  - _Notas: —_

- [ ] Crear financiamiento con plazo **60 días** → comisión = RD$ 10,000.00 (10%)
  - _Notas: —_

- [ ] Crear financiamiento con plazo **90 días** → comisión = RD$ 15,000.00 (15%)
  - _Notas: —_

---

## 2. Recálculo dinámico en formulario

> En el formulario de creación de financiamiento, verificar que cambiar el plazo recalcula la comisión y el monto a transferir.

- [ ] Llenar monto RD$ 100,000 con plazo default (15 días) → comisión = RD$ 5,000.00, transferir = RD$ 95,000.00
  - _Notas: —_

- [ ] Cambiar plazo a **45 días** sin salir del formulario → comisión = RD$ 10,000.00, transferir = RD$ 90,000.00
  - _Notas: —_

- [ ] Cambiar plazo a **90 días** → comisión = RD$ 15,000.00, transferir = RD$ 85,000.00
  - _Notas: —_

- [ ] Verificar que el campo de plazo permite escribir números de múltiples dígitos sin resetear (ej. escribir "45" completo)
  - _Notas: —_

- [ ] Verificar que la fecha de vencimiento se actualiza al cambiar el plazo
  - _Notas: —_

---

## 3. Label de comisión

- [ ] En el formulario de creación, el campo se llama **"Comisión"** (sin porcentaje fijo)
  - _Notas: —_

- [ ] En la página de Cuentas por Pagar, la columna se llama **"Comisión"** (sin porcentaje fijo)
  - _Notas: —_

---

## 4. Parámetro de mora

> Verificar que el parámetro `late_fee_pct` aparece y es configurable en la página de parámetros.

- [ ] Navegar a Parámetros como `super_admin` → ver campo **"Mora por Atraso"** con valor 5.00%
  - _Notas: —_

- [ ] El helper text dice: "% sobre saldo pendiente por cada 30 días de atraso."
  - _Notas: —_

- [ ] Cambiar el valor a 10%, guardar, recargar → el valor persiste en 10%
  - _Notas: —_
  - [ ] Revertir a 5% después de la prueba
    - _Notas: —_

---

## 5. Mora estimada en vista de financiamiento

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada.

- [ ] Ver detalle de un financiamiento vencido → aparece campo **"Mora Estimada"** en color rojo
  - _Notas: —_

- [ ] La mora estimada se calcula como: saldo pendiente × 5% × ceil(días vencidos / 30)
  - _Notas: —_

- [ ] Ver detalle de un financiamiento **no vencido** → NO aparece el campo "Mora Estimada"
  - _Notas: —_

- [ ] Ver detalle de un financiamiento **cobrado** → NO aparece "Mora Estimada"
  - _Notas: —_

---

## 6. Mora estimada en Cuentas por Cobrar

> La página de Cuentas por Cobrar ahora incluye financiamientos `partially_collected` y muestra columna de mora.

- [ ] La tabla incluye financiamientos en estado `disbursed` y `partially_collected`
  - _Notas: —_

- [ ] Existe columna **"Mora Estimada"** visible para `super_admin` y `operator`
  - _Notas: —_

- [ ] Financiamientos no vencidos muestran RD$ 0.00 en mora estimada
  - _Notas: —_

- [ ] Financiamientos vencidos muestran el monto de mora calculado correctamente
  - _Notas: —_

- [ ] La columna de mora **no es visible** para `company_user`
  - _Notas: —_

---

## 7. Cobro sin mora (financiamiento no vencido)

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento futura.

- [ ] Registrar cobro completo → financiamiento pasa a `collected`
  - _Notas: —_
  - [ ] `collected_amount` = monto total del financiamiento
    - _Notas: —_
  - [ ] `late_fee_amount` = 0.00
    - _Notas: —_
  - [ ] `late_fee_pending` = 0.00
    - _Notas: —_

---

## 8. Cobro con mora (financiamiento vencido) — cobro total

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada. Ejemplo: RD$ 100,000, vencido hace 45 días.

- [ ] Calcular mora esperada: 100,000 × 5% × ceil(45/30) = RD$ 10,000
  - _Notas: —_

- [ ] Registrar cobro por RD$ 110,000 (capital + mora)
  - _Notas: —_
  - [ ] Financiamiento pasa a `collected`
    - _Notas: —_
  - [ ] `collected_amount` = RD$ 100,000.00
    - _Notas: —_
  - [ ] `late_fee_amount` = RD$ 10,000.00
    - _Notas: —_
  - [ ] `late_fee_pending` = RD$ 0.00
    - _Notas: —_

---

## 9. Cobro con mora (financiamiento vencido) — cobro parcial

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada. Ejemplo: RD$ 100,000, vencido hace 45 días.

- [ ] Mora esperada: RD$ 10,000. Total adeudado: RD$ 110,000
  - _Notas: —_

- [ ] Registrar cobro parcial de RD$ 50,000
  - _Notas: —_
  - [ ] Financiamiento pasa a `partially_collected`
    - _Notas: —_
  - [ ] Distribución proporcional: capital ≈ RD$ 45,454.55, mora ≈ RD$ 4,545.45
    - _Notas: —_
  - [ ] `late_fee_pending` > 0 (mora restante)
    - _Notas: —_

- [ ] Verificar en detalle del financiamiento que se muestra **"Mora Cobrada"** con el monto acumulado
  - _Notas: —_

---

## 10. Mora cobrada visible en detalle

> Requiere un financiamiento que haya recibido al menos un cobro con mora.

- [ ] En el detalle del financiamiento aparece campo **"Mora Cobrada"** con el monto acumulado
  - _Notas: —_

- [ ] En financiamientos sin mora cobrada, el campo **no aparece**
  - _Notas: —_

---

## 11. Flujo de desembolso sin afectación

> La mora solo aplica a cobros. Verificar que el flujo de desembolso no cambió.

- [ ] Crear y desembolsar un financiamiento → `late_fee_amount` = 0.00, `late_fee_pending` = 0.00
  - _Notas: —_

- [ ] La comisión se acredita al fondo correctamente
  - _Notas: —_

---

## Observaciones generales

- —
