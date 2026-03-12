# CLAUDE.md — Cormart Factory

Archivo de contexto para Claude Code. Léelo completo antes de tocar cualquier archivo.

---

## Proyecto

Sistema de gestión del Fondo Familiar de Factoring — Cormart Factory.
Stack: **Laravel 12 + Filament 3 + MySQL + Filament Shield**.
Idioma de UI: **Español**. Código fuente: **Inglés**. Moneda: **RD$ (Peso Dominicano)**.

---

## Estado actual: v0.1.0 (MVP completo)

El MVP está implementado y funcionando. Las fases completadas incluyen:
autenticación, CRUD de clientes, facturas, miembros, parámetros, 5 widgets de dashboard,
cierre mensual con cascada de distribución, e historial de cierres.

**Lo que existe NO debe romperse al construir v0.2.0.**

---

## Convenciones críticas del código existente

### Relaciones en MonthlyClosing
```php
// CORRECTO
$closing->executedBy   // NO: executor
$closing->distributions
$closing->parametersSnapshot
```

### Relaciones en ClosingDistribution
```php
// CORRECTO
$distribution->fundMember   // NO: member
```

### Tabla de ClosingParametersSnapshot
```php
// Requiere declaración explícita — el nombre de la tabla es singular
protected $table = 'closing_parameters_snapshot';
```

### Permisos Shield
Shield usa `::` como separador en nombres compuestos:
```
view_any_monthly::closing
view_monthly::closing
```

### Tailwind
El tema Filament **no está compilado**. Las clases Tailwind custom no funcionan.
Usar **inline styles** para layouts que no sean clases base de Tailwind.
```php
// Ejemplo en blade
style="display:grid;grid-template-columns:1fr 1fr;gap:24px"
```

### Lógica de negocio
**Nunca en Resources ni Widgets.** Siempre en la capa de Services:
`DistributionService`, `InvoiceService`, `ClientService`, `ParameterService`.

### refreshOverdueStatus()
Se llama en cada carga del Dashboard vía InvoiceService. No hay queue workers
(Namecheap shared hosting — todas las operaciones son síncronas).

---

## Regla de distribución mensual (orden estricto — no modificar)

```
Comisiones del periodo (facturas con collection_period = periodo)
  − 3% fijo × aportación de cada miembro de capital
  = Ganancia neta
      − 20% reserva del fondo
      = Post-reserva
          − 50% para aportante en naturaleza
          = Disponible para capital → reparto proporcional por fund_percentage

Verificación: comisiones = total_fijo + reserva + naturaleza + capital → debe ser 0
```

---

## Próxima versión: v0.2.0

### Cambio conceptual principal
La entidad central pasa de **Invoice** a **Financing**.
Los documentos (OC y factura) son atributos del financiamiento, no entidades separadas.

### Nueva jerarquía
```
Fondo (nosotros)
  └── Companies (clientes del fondo, ej: Succar Tech)
        └── Clients (deudores de la compañía, ej: Techno Store RD)
              └── Financings
                    ├── PurchaseOrder (opcional, con adjunto)
                    └── Invoice (opcional, con adjunto)
```
Regla: al menos uno de los dos documentos debe estar presente.

### Estados de Financing
```
solicited → disbursed → collected
    ↓            ↓
 cancelled    cancelled  (cancellation_reason obligatorio)
```

### Nuevas tablas a crear
| Tabla | Descripción |
|---|---|
| `companies` | Clientes del fondo |
| `financings` | Entidad central. Reemplaza `invoices` en flujo futuro |
| `financing_documents` | OC o factura adjunta a un financing |
| `transactions` | Desembolsos y cobros. Tipos: disbursement, collection |
| `transaction_financings` | Pivote: una transacción agrupa múltiples financings |

### Cambios a tablas existentes
| Tabla | Campo a agregar |
|---|---|
| `clients` | `company_id` FK → companies |
| `users` | `company_id` FK → companies (nullable para usuarios internos) |

### Nuevo rol
`company_user` — usuario externo, ve **solo** los datos de su compañía.
Los usuarios internos (`super_admin`, `operator`) ven todas las compañías.

### Transacciones
- Tipo `disbursement`: fondo → compañía. Estados: pending → confirmed.
- Tipo `collection`: deudor → fondo. Estados: pending → confirmed.
- Requieren banco + número de transacción (entrada manual, obligatorio).
- **Ninguna se confirma automáticamente** — requiere acción manual del operator.
- Un disbursement puede agrupar múltiples financings.
- Un collection puede agrupar múltiples financings.

### Orden de desarrollo sugerido para v0.2.0
1. Migrations: companies, financings, financing_documents, transactions, transaction_financings
2. Models + relaciones + actualizar clients y users
3. CompanyResource (CRUD)
4. FinancingResource (CRUD + estados + documentos adjuntos)
5. TransactionService + TransactionResource
6. Query scopes para company_user (solo ve su compañía)
7. Vistas "Cuentas por Cobrar" y "Cuentas por Pagar"
8. Actualizar Dashboard widgets para filtrar por mes actual
9. QA + deploy

---

## Documentos de referencia

Los siguientes archivos están en la carpeta `/docs`:
- `docs/contexto_fondo_familiar.md` — reglas de negocio del fondo (fuente de verdad)
- `docs/PRD_v0.2.0.docx` — requerimientos detallados de v0.2.0
- `docs/Arquitectura_Tecnica_v0.1.0.docx` — arquitectura del MVP implementado
- `docs/Arquitectura_Tecnica_v0.2.0.docx` — arquitectura propuesta para v0.2.0
