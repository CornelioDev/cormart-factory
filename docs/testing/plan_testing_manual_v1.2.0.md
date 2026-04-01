# Plan de Testing Manual — Cormart Factory v1.2.0

> Versión: v1.2.0 — Comisión por tramos de 30 días + Mora por financiamientos vencidos

## Resumen de la sesión

| Campo     | Valor                          |
| --------- | ------------------------------ |
| Tester    | —                              |
| Fecha     | —                              |
| Instancia | `cormart_factory` (desarrollo) |
| Rol       | `super_admin`, `operator`      |
| Resultado | —                              |

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

## 1. Comisión por tramos de 30 días

> Crear financiamientos con distintos plazos y verificar que la comisión escala correctamente. Usar monto RD$ 100,000 para facilitar el cálculo.

* [x] Crear financiamiento con plazo **15 días** → comisión = RD$ 5,000.00 (5%)

  * *Notas: —*

* [x] Crear financiamiento con plazo **30 días** → comisión = RD$ 5,000.00 (5%)

  * *Notas: —*

* [x] Crear financiamiento con plazo **31 días** → comisión = RD$ 10,000.00 (10%)

  * *Notas: —*

* [x] Crear financiamiento con plazo **60 días** → comisión = RD$ 10,000.00 (10%)

  * *Notas: —*

* [x] Crear financiamiento con plazo **90 días** → comisión = RD$ 15,000.00 (15%)

  * *Notas: —*

## 2. Recálculo dinámico en formulario

> En el formulario de creación de financiamiento, verificar que cambiar el plazo recalcula la comisión y el monto a transferir.

* [x] Llenar monto RD$ 100,000 con plazo default (15 días) → comisión = RD$ 5,000.00, transferir = RD$ 95,000.00

  * *Notas: —*

* [x] Cambiar plazo a **45 días** sin salir del formulario → comisión = RD$ 10,000.00, transferir = RD$ 90,000.00

  * *Notas: —*

* [x] Cambiar plazo a **90 días** → comisión = RD$ 15,000.00, transferir = RD$ 85,000.00

  * *Notas: —*

* [x] Verificar que el campo de plazo permite escribir números de múltiples dígitos sin resetear (ej. escribir "45" completo)

  * *Notas: —*

* [x] Verificar que la fecha de vencimiento se actualiza al cambiar el plazo

  * *Notas: —*

## 3. Label de comisión

* [x] En el formulario de creación, el campo se llama **"Comisión"** (sin porcentaje fijo)

  * *Notas: Se debería poder visualizar el % de comisión aplicada*

* [x] En la página de Cuentas por Pagar, la columna se llama **"Comisión"** (sin porcentaje fijo)

  * *Notas: —*

## 4. Parámetro de mora

> Verificar que el parámetro `late_fee_pct` aparece y es configurable en la página de parámetros.

* [x] Navegar a Parámetros como `super_admin` → ver campo **"Mora por Atraso"** con valor 5.00%

  * *Notas: —*

* [x] El helper text dice: "% sobre saldo pendiente por cada 30 días de atraso."

  * *Notas: Cambia % por Porcentaje*

* [x] Cambiar el valor a 10%, guardar, recargar → el valor persiste en 10%

  * *Notas: —*

  * [x] Revertir a 5% después de la prueba

    * *Notas: —*

## 5. Mora estimada en vista de financiamiento

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada.

* [x] Ver detalle de un financiamiento vencido → aparece campo **"Mora Estimada"** en color rojo

  * *Notas: —*

* [x] La mora estimada se calcula como: saldo pendiente × 5% × ceil(días vencidos / 30)

  * *Notas: —*

* [x] Ver detalle de un financiamiento **no vencido** → NO aparece el campo "Mora Estimada"

  * *Notas: Los financiameintos de la tabla deben enlazar a la vista detalle*

* [x] Ver detalle de un financiamiento **cobrado** → NO aparece "Mora Estimada"

  * *Notas: —*

## 6. Mora estimada en Cuentas por Cobrar

> La página de Cuentas por Cobrar ahora incluye financiamientos `partially_collected` y muestra columna de mora.

* [x] La tabla incluye financiamientos en estado `disbursed` y `partially_collected`

  * *Notas: —*

* [x] Existe columna **"Mora Estimada"** visible para `super_admin` y `operator`

  * *Notas: No verifiqué manualmente, verificar con playwright*

* [x] Financiamientos no vencidos muestran RD$ 0.00 en mora estimada

  * *Notas: —*

* [x] Financiamientos vencidos muestran el monto de mora calculado correctamente

  * *Notas: —*

* [x] La columna de mora **no es visible** para `company_user`

  * *Notas: No verifiqué manualmente, verificar con playwright*

## 7. Cobro sin mora (financiamiento no vencido)

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento futura.

* [x] Registrar cobro completo → financiamiento pasa a `collected`

  * *Notas: —*

  * [x] `collected_amount` = monto total del financiamiento

    * *Notas: —*

  * [x] `late_fee_amount` = 0.00

    * *Notas: —*

  * [x] `late_fee_pending` = 0.00

    * *Notas: —*

## 8. Cobro con mora (financiamiento vencido) — cobro total

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada. Ejemplo: RD$ 100,000, vencido hace 45 días.

* [x] Calcular mora esperada: 100,000 × 5% × ceil(45/30) = RD$ 10,000

  * *Notas: —*

* [x] Registrar cobro por RD$ 110,000 (capital + mora)

  * *Notas: Actualmente el campo no muestra el monto pendiente + la mora, sino solamente el monto pendiente, y al hacer el pago, sale como un Abono*

  * [ ] Financiamiento pasa a `collected`

    * *Notas: —*

  * [ ] `collected_amount` = RD$ 100,000.00

    * *Notas: —*

  * [ ] `late_fee_amount` = RD$ 10,000.00

    * *Notas: —*

  * [ ] `late_fee_pending` = RD$ 0.00

    * *Notas: —*

## 9. Cobro con mora (financiamiento vencido) — cobro parcial

> Requiere un financiamiento en estado `disbursed` con fecha de vencimiento pasada. Ejemplo: RD$ 100,000, vencido hace 45 días.

* [x] Mora esperada: RD$ 10,000. Total adeudado: RD$ 110,000

  * *Notas: —*

* [x] Registrar cobro parcial de RD$ 50,000

  * *Notas: —*

  * [x] Financiamiento pasa a `partially_collected`

    * *Notas: —*

  * [x] Distribución proporcional: capital ≈ RD$ 45,454.55, mora ≈ RD$ 4,545.45

    * *Notas: —*

  * [x] `late_fee_pending` > 0 (mora restante)

    * *Notas: —*

* [x] Verificar en detalle del financiamiento que se muestra **"Mora Cobrada"** con el monto acumulado

  * *Notas: —*

## 10. Mora cobrada visible en detalle

> Requiere un financiamiento que haya recibido al menos un cobro con mora.

* [x] En el detalle del financiamiento aparece campo **"Mora Cobrada"** con el monto acumulado

  * *Notas: —*

* [x] En financiamientos sin mora cobrada, el campo **no aparece**

  * *Notas: —*

## 11. Flujo de desembolso sin afectación

> La mora solo aplica a cobros. Verificar que el flujo de desembolso no cambió.

* [x] Crear y desembolsar un financiamiento → `late_fee_amount` = 0.00, `late_fee_pending` = 0.00

  * *Notas: —*

* [x] La comisión se acredita al fondo correctamente

  * *Notas: —*

## Observaciones generales

*
