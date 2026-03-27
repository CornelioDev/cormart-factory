# CLAUDE.md — Cormart Factory

Archivo de contexto para Claude Code. Léelo completo antes de tocar cualquier archivo.

---

## Proyecto

Sistema de gestión del Fondo Familiar de Factoring — Cormart Factory.
Stack: **Laravel 12 + Filament 3 + MySQL + Filament Shield (Spatie Permission)**.
Idioma de UI: **Español**. Código fuente: **Inglés**. Moneda: **RD$ (Peso Dominicano)**.

Repositorio: https://github.com/CornelioDev/cormart-factory

---

## Estado actual: v0.9.0

El sistema está completamente operativo. Se mantienen dos instancias locales:

| Instancia | BD | Propósito |
|---|---|---|
| Desarrollo | `cormart_factory` | Nuevas funcionalidades, datos de prueba |
| Pre-producción | `cormart_staging` | Datos reales, QA |

---

## Roles

| Rol | Descripción |
|---|---|
| `super_admin` | Acceso total. Cierre mensual, parámetros, usuarios. |
| `operator` | Operaciones: financiamientos, transacciones, cuentas por cobrar/pagar. |
| `member` | Solo lectura: financiamientos, cierres, widgets del dashboard. |
| `company_user` | Externo. Ve **solo** datos de su compañía. No accede a datos del fondo. |

---

## Arquitectura de modelos

```
Fondo
  └── FundMember (tipo: capital | in_kind)
  └── Company (cliente del fondo)
        └── Client (deudor de la compañía)
              └── Financing (código: FN000001)
                    ├── FinancingDocument (OC o factura, adjunto)
                    └── Transaction (via pivot TransactionFinancing)
  └── Transaction (tipo: disbursement | collection)
  └── MonthlyClosing
        ├── ClosingDistribution (por miembro)
        └── ClosingParametersSnapshot (auditoría de parámetros)
  └── Parameter (configuración dinámica)
        └── ParameterHistory (auditoría de cambios)
```

### Estados de Financing
```
solicited → disbursed → partially_collected → collected
    ↓            ↓              ↓
 cancelled    cancelled     cancelled  (cancellation_reason obligatorio)
```

- `partially_collected`: El financiamiento ha recibido abonos parciales pero no se ha completado el cobro total.
- `collected_amount`: Campo decimal que acumula el monto cobrado. Al alcanzar `amount`, el status pasa a `collected`.
- `collection_period` y `collected_at` se asignan solo al completar el cobro total (último abono).

### Tipos de Transaction
- `disbursement`: Fondo → Compañía. Se confirma automáticamente.
- `collection`: Deudor → Fondo. Puede ser cobro completo o abono parcial. Requiere confirmación de operator si lo crea un company_user.

---

## Convenciones críticas

### Relaciones en modelos (no cambiar nombres)
```php
$closing->executedBy        // NO: executor
$closing->distributions
$closing->parametersSnapshot
$distribution->fundMember   // NO: member
```

### Tabla con nombre singular (declarar explícitamente)
```php
// ClosingParametersSnapshot
protected $table = 'closing_parameters_snapshot';
```

### Código de Financing (auto-generado)
```php
// booted() en Financing::class
static::created(function (Financing $financing) {
    $financing->updateQuietly([
        'code' => 'FN' . str_pad($financing->id, 6, '0', STR_PAD_LEFT),
    ]);
});
```

### Permisos Shield
Shield usa `::` como separador en nombres compuestos:
```
view_any_monthly::closing
view_monthly::closing
```

### Tailwind
El tema Filament **no está compilado**. Las clases Tailwind custom no funcionan.
Usar **inline styles** para layouts personalizados:
```blade
style="display:grid;grid-template-columns:1fr 1fr;gap:24px"
```

### Lógica de negocio
**Nunca en Resources ni Widgets.** Siempre en la capa de Services:
`DistributionService`, `TransactionService`, `FinancingService`, `ClientService`, `ParameterService`, `FundMemberService`.

### Recursos del stack antes de construir desde cero
Antes de implementar cualquier funcionalidad, verificar si el stack ya lo provee:
- **Filament 3**: Actions, Infolists, Notifications, Widgets, Table columns/filters/bulk actions, Form components (FileUpload, Repeater, etc.)
- **Laravel 12**: Events, Observers, Policies, Form Requests, Collections, Carbon
- **Spatie Permission / Shield**: Permisos granulares, roles, `canAccess()`, `before` gate

Solo construir solución custom si el stack no cubre el caso.

### No hay queue workers
Namecheap shared hosting (producción futura) — **todas las operaciones son síncronas**.

---

## Parámetros del sistema

Configurables desde el módulo Parámetros (super_admin). Cada cambio queda en historial.

| Key | Default | Descripción |
|---|---|---|
| `commission_pct` | 5.0 | % de comisión sobre el monto del financiamiento |
| `fixed_return_pct` | 3.0 | % de rendimiento fijo mensual sobre capital aportado |
| `reserve_pct` | 20.0 | % de reserva sobre la ganancia neta |
| `in_kind_pct` | 50.0 | % del post-reserva para el aportante en especie |
| `default_term_days` | 15 | Plazo predeterminado en días |

---

## Regla de distribución mensual (orden estricto — no modificar)

```
Comisiones de financiamientos desembolsados en el período (issue_period = período seleccionado)
  − Gastos del período (type=expense, transaction_date en el período)
  = Base real de ganancias
    − Rendimiento fijo (capital × fixed_return_pct) por cada miembro activo de capital
    = Ganancia neta
        − Reserva (ganancia × reserve_pct)
        = Post-reserva
            − Aporte en especie (post-reserva × in_kind_pct)
            = Disponible para capital → reparto proporcional por fund_percentage

Verificación: comisiones = gastos + total_fijo + reserva + naturaleza + capital → diff = 0
```

> **Nota:** La comisión (5%) se retiene al momento del desembolso — nunca sale del fondo.
> La distribución se basa en desembolsos (`issue_period`), no en cobros (`collection_period`).
> `collection_period` y `collected_at` siguen existiendo como métricas operativas de cobro.

Cada cierre persiste: `MonthlyClosing` + `ClosingDistribution` por miembro + `ClosingParametersSnapshot`.
Un período solo puede cerrarse una vez.

---

## Widgets del Dashboard

| Widget | Visible para | Scope |
|---|---|---|
| `FinancingPipelineWidget` | Todos | Filtrado por company_id para company_user |
| `PendingTransactionsWidget` | super_admin, operator | Global |
| `CuentasPorCobrarStatsWidget` | super_admin, operator, company_user | Filtrado por company_id para company_user (isDiscovered = false) |
| `CuentasPorPagarStatsWidget` | super_admin, operator | Global (isDiscovered = false) |

---

## Páginas custom (Filament Pages)

| Page | Acceso | Descripción |
|---|---|---|
| `MonthlyClosingPage` | super_admin | Ejecutar y ver cierres mensuales |
| `CuentasPorCobrarPage` | super_admin, operator, company_user | Vista de cuentas por cobrar |
| `CuentasPorPagarPage` | super_admin, operator | Vista de cuentas por pagar |
| `ParametrosPage` | super_admin | Gestión de parámetros del sistema |

---

## Documentación de proyecto

| Documento | Propósito |
|---|---|
| `docs/ROADMAP.md` | Roadmap detallado v0.5.0 → v1.0.0 con cambios de BD y archivos por versión |
| `docs/TODO.md` | Checklist de tareas con estado `[ ]` / `[x]` — fuente de verdad del avance |

Al completar una versión: marcar ítems en `TODO.md`, bump en `composer.json`, commit + tag + push.

---

## Versionamiento

Se sigue [Semantic Versioning](https://semver.org/lang/es/):
- **Mayor** (`x.0.0`): cambio que rompe compatibilidad
- **Menor** (`0.x.0`): nueva funcionalidad
- **Parche** (`0.0.x`): corrección o mejora menor

Flujo por versión:
1. `git commit` del cambio
2. Actualizar `"version"` en `composer.json`
3. `git commit -m "chore: bump version to vX.X.X"`
4. `git tag -a vX.X.X -m "..."`
5. `git push origin master --tags`

---

## Checklist de Deployment (Producción — Namecheap Shared Hosting)

### Pre-deployment
1. Verificar que todos los tests pasan: `php artisan test`
2. Configurar `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, credenciales de BD
3. Instalar dependencias sin dev: `composer install --no-dev --optimize-autoloader`

### Primer deployment
1. `php artisan migrate --force`
2. `php artisan db:seed --force` (ejecuta solo seeders de producción gracias al guard de entorno)
3. `php artisan shield:generate --all` (genera permisos para todos los resources)
4. `php artisan storage:link`
5. Crear usuario super_admin: `php artisan make:filament-user`
6. Asignar rol super_admin al usuario vía tinker: `User::find(1)->assignRole('super_admin')`

### Deployments subsecuentes
1. `php artisan migrate --force`
2. `php artisan shield:generate --all` (si se agregaron resources nuevos)
3. Limpiar caches: `php artisan optimize:clear`

### Restricciones del hosting
- **Sin queue workers** — todas las operaciones son síncronas
- **Sin scheduler** a menos que se configure cron en cPanel
- Archivos subidos en `storage/app/public` con symlink vía `storage:link`
- Verificar límites de PHP: `upload_max_filesize`, `post_max_size`, `memory_limit`
