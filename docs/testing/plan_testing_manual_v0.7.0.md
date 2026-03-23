# Plan de Testing Manual — Cormart Factory v0.7.0

> Versión: v0.7.0 — Perfil de Miembro (Estado de Cuenta)

## Resumen de la sesión

| Campo | Valor |
|---|---|
| Tester | — |
| Fecha | — |
| Instancia | `cormart_factory` / `cormart_staging` |
| Rol | `super_admin`, `member` |
| Resultado | ☑ Aprobado |

---

## Preparación

Verificar que las migraciones están al día:

```bash
php artisan migrate:status
```

Verificar que el seeder de permisos se ejecutó (member tiene `view_fund::member` y `page_MemberAccountPage`):

```bash
php artisan tinker --execute="\$r = Spatie\Permission\Models\Role::findByName('member'); echo implode(', ', \$r->getPermissionNames()->toArray());"
```

Debe incluir `view_fund::member` y `page_MemberAccountPage`.

Verificar que el usuario `member` tiene `fund_member_id` asignado:

```bash
php artisan tinker --execute="\$u = \App\Models\User::role('member')->first(); echo \$u->name . ' → fund_member_id=' . \$u->fund_member_id;"
```

Verificar que existen distribuciones de cierres:

```bash
php artisan tinker --execute="echo \App\Models\ClosingDistribution::count() . ' distribuciones';"
```

---

## 1. Historial de Distribuciones — super_admin

> Iniciar sesión como `super_admin`.

- [x] Ir a **Administración → Miembros del Fondo** → clic en un miembro con distribuciones
  - _Notas: —_
- [x] Verificar que aparece la tabla **"Historial de Distribuciones"** debajo del infolist
  - _Notas: —_
- [x] Verificar columnas: **Periodo, Fijo, Variable, Total**
  - _Notas: —_
- [x] Verificar que Periodo muestra el valor del cierre (ej: `2026-03`)
  - _Notas: —_
- [x] Verificar que todos los montos tienen formato `RD$` con **2 posiciones decimales**
  - _Notas: —_
- [x] Verificar que la columna **Total** aparece en verde y negrita
  - _Notas: —_
- [x] Verificar que el orden por defecto es periodo descendente (más reciente primero)
  - _Notas: —_

---

## 2. Historial de Distribuciones — miembro sin distribuciones

> Seleccionar un miembro que no tenga distribuciones de cierres.

- [x] Verificar que la tabla **"Historial de Distribuciones"** muestra estado vacío
  - _Notas: —_

---

## 3. Tabla de Transacciones de Ganancias — super_admin

> En la misma vista detalle del miembro.

- [x] Verificar que la tabla **"Transacciones de Ganancias"** sigue visible debajo del historial de distribuciones
  - _Notas: —_
- [x] Verificar que muestra transacciones de tipo `earning_distribution` y `member_disbursement`
  - _Notas: —_

---

## 4. Acceso como rol member — Estado de Cuenta

> Iniciar sesión como usuario con rol `member` y `fund_member_id` asignado.

- [x] Verificar que aparece **"Estado de Cuenta"** en el menú lateral bajo el grupo **Miembros**
  - _Notas: —_
- [x] Hacer clic en "Estado de Cuenta" → redirige automáticamente a la vista detalle de su miembro
  - _Notas: —_
- [x] Verificar que la URL es `/admin/fund-members/{su_fund_member_id}`
  - _Notas: —_

---

## 5. Vista detalle como member — datos correctos

> Continuar en la sesión del usuario `member`.

- [x] Verificar sección **"Datos del Miembro"**: nombre, tipo, capital, % del fondo, miembro desde, estado
  - _Notas: —_
- [x] Verificar sección **"Resumen de Ganancias"**: Total Ganado (verde), Total Desembolsado (naranja), Balance Disponible (azul si > 0)
  - _Notas: —_
- [x] Verificar tabla **"Historial de Distribuciones"** con las distribuciones del miembro
  - _Notas: —_
- [x] Verificar tabla **"Transacciones de Ganancias"** con las transacciones del miembro
  - _Notas: —_

---

## 6. Vista detalle como member — acciones restringidas

> Continuar en la sesión del usuario `member`.

- [x] Verificar que el botón **"Editar"** **no aparece** en el header
  - _Notas: —_
- [x] Verificar que el botón **"Desembolsar Ganancias"** **no aparece** en el header
  - _Notas: —_

---

## 7. Member — navegación restringida

> Continuar en la sesión del usuario `member`.

- [x] Verificar que **"Miembros del Fondo"** **no aparece** en el menú lateral
  - _Notas: —_
- [x] Navegar directamente a `/admin/fund-members` → acceso denegado (403 o redirect)
  - _Notas: —_

---

## 8. Member — no puede ver otros miembros

- [x] Navegar directamente a `/admin/fund-members/1` (otro miembro) → acceso denegado (403)
  - _Notas: —_
- [x] Navegar directamente a `/admin/fund-members/3` (otro miembro) → acceso denegado (403)
  - _Notas: —_

---

## 9. Acceso denegado — otros roles

- [x] Iniciar sesión como `operator` → **"Estado de Cuenta"** **no aparece** en el menú
  - _Notas: —_
- [x] Navegar directamente a `/admin/member-account-page` como `operator` → acceso denegado
  - _Notas: —_
- [x] Iniciar sesión como `company_user` → **"Estado de Cuenta"** **no aparece** en el menú
  - _Notas: —_
- [x] Navegar directamente a `/admin/member-account-page` como `company_user` → acceso denegado
  - _Notas: —_

---

## Observaciones generales

- Para los otros roles, el módulo de miembros del fondo, aparte de tener acceso restringido, no debe de estar visible.
