# Contexto del Proyecto — Fondo Familiar de Factoring
> Usa este documento como punto de partida en cualquier chat nuevo sobre el negocio.
> Contiene reglas de negocio, decisiones tomadas y parámetros del fondo. NO incluye estado técnico del código (ver estado_proyecto.md).

---

## 1. ¿Qué es el negocio?

Un **fondo familiar cerrado de factoring** (compra de facturas). El fondo financia facturas de empresas de hardware/tecnología a cambio de una comisión. El socio operativo gestiona la relación con los clientes y el cobro.

**Modelo básico:**
- Se compran facturas a plazo de **15 días**
- La comisión es del **5%** sobre el valor de la factura
- El socio operativo (José Cornelio Senior) aporta los clientes y gestiona el cobro — es un **factoring con recurso** (el riesgo de cobro recae en él)
- El plazo real de cobro promedio es de ~7 días
- Moneda: **Peso Dominicano (RD$)**

---

## 2. Miembros del Fondo

### Aportantes de Capital (máximo 5)

| Miembro | Aportación | % del Fondo |
|---|---|---|
| Familia Cornelio Pérez | RD$300,000 | 100% |
| (slots 2–5 disponibles) | — | — |

**Reglas para aportantes de capital:**
- Reciben un **3% fijo mensual** sobre su aportación (independiente del desempeño del mes)
- Reciben adicionalmente una **parte proporcional** de la ganancia neta post-reserva disponible para capital, según su % del fondo

### Aportante en Naturaleza (1 activo)

| Miembro | Tipo de Aporte |
|---|---|
| José Cornelio (Senior) | Contactos / cartera de clientes, espacio físico, conocimiento operativo |

**Reglas para el aportante en naturaleza:**
- **NO recibe 3% fijo**
- Recibe el **50%** de la ganancia neta post-reserva (antes de distribuir el resto a los aportantes de capital)

---

## 3. Orden de Distribución (flujo mensual)

```
Comisiones generadas por facturas del mes
  − 3% fijo a todos los aportantes de capital (sobre sus aportaciones)
  = Ganancia neta

Ganancia neta
  − Reserva del fondo (20% de ganancia neta)
  = Ganancia neta post-reserva

Ganancia neta post-reserva
  − Pago aportante en naturaleza (50%)
  = Disponible para aportantes de capital

Disponible para capital
  → Se reparte proporcionalmente según % de cada miembro en el fondo
```

### Ejemplo con datos actuales (enero 2025)

| Concepto | Monto |
|---|---|
| Comisiones generadas (5% × RD$680,000) | RD$34,000 |
| − 3% fijo Familia Cornelio Pérez (RD$300K) | RD$9,000 |
| = Ganancia neta | RD$25,000 |
| − Reserva del fondo (20%) | RD$5,000 |
| = Post-reserva | RD$20,000 |
| − José Cornelio Senior (50%) | RD$10,000 |
| = Para capital (100% Familia Cornelio Pérez) | RD$10,000 |
| **Total Familia Cornelio Pérez** | **RD$19,000** |
| **Total José Cornelio Senior** | **RD$10,000** |
| **Reserva acumulada** | **RD$5,000** |
| **Verificación total = comisiones** | **RD$34,000 ✔** |

---

## 4. Parámetros Configurables

| Parámetro | Valor actual |
|---|---|
| Comisión por factura | 5% |
| Rendimiento fijo mensual | 3% |
| Reserva sobre ganancia neta | 20% |
| % para aportante en naturaleza | 50% |
| Plazo estándar de facturas | 15 días |

---

## 5. Reglas de Negocio Adicionales

- **Capital inactivo:** Si el fondo crece sin que el volumen de facturas crezca en proporción, el 3% fijo puede consumir gran parte de la ganancia neta.
- **Factoring con recurso:** El riesgo de no cobro recae sobre el socio operativo, no sobre el fondo.
- **Fondo cerrado:** Máximo 5 aportantes de capital más el aportante en naturaleza.
- **Reserva obligatoria:** El 20% de la ganancia neta se retiene cada mes. No se distribuye.
- **Verificación obligatoria:** comisiones = total_fijo + reserva + pago_naturaleza + disponible_capital. Debe ser 0.
- **Inmutabilidad de cierres:** Un cierre mensual ejecutado no puede modificarse.
- **Parámetros:** Los cambios aplican solo al siguiente periodo.

---

## 6. Glosario

| Término | Definición |
|---|---|
| **Factoring** | Compra de cuentas por cobrar a cambio de un descuento |
| **Factoring con recurso** | Si el deudor no paga, el cedente asume la pérdida |
| **Aportante de capital** | Miembro que aporta dinero. Recibe 3% fijo + participación proporcional |
| **Aportante en naturaleza** | Miembro sin capital monetario. Recibe % del sobrante post-reserva |
| **Capital en calle** | Suma del valor de facturas pendientes de cobro |
| **Periodo** | Mes calendario (ej: 2025-01) |
| **Cierre mensual** | Proceso de calcular y guardar la distribución del fondo para un periodo |
