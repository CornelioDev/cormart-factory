# Cormart Factory

Sistema de gestión del Fondo Familiar de Factoring — v0.2.1

Cormart Factory es una plataforma web para la administración de un fondo de factoring familiar. Permite gestionar el ciclo completo de financiamientos: desde la solicitud hasta el cobro, el cierre mensual y la distribución de rendimientos entre los miembros del fondo.

---

## Tecnologías

| Componente | Tecnología |
|---|---|
| Framework | Laravel 12 |
| Panel de administración | Filament 3.3 |
| Autorización y roles | Spatie Laravel Permission + Filament Shield |
| Base de datos | MySQL (compatible con SQLite) |
| Sesiones y caché | Database driver |
| Estilos | Tailwind CSS (vía Filament) |

---

## Requisitos

- PHP 8.2 o superior
- Composer
- MySQL 8.x o MariaDB
- Node.js (solo para compilar assets en desarrollo)

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/CornelioDev/cormart-factory.git
cd cormart-factory

# 2. Instalar dependencias
composer install

# 3. Configurar el entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar la base de datos en .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=cormart_factory
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Ejecutar migraciones y seeders base
php artisan migrate
php artisan db:seed --class=RolePermissionsSeeder
php artisan db:seed --class=ShieldSeeder
php artisan db:seed --class=ParameterSeeder

# 6. Crear el enlace de almacenamiento
php artisan storage:link

# 7. Crear usuario administrador
php artisan make:filament-user
# Luego asignar el rol super_admin desde tinker:
# php artisan tinker
# \App\Models\User::where('email','tu@email.com')->first()->assignRole('super_admin');
```

---

## Estructura del sistema

### Roles

| Rol | Descripción |
|---|---|
| `super_admin` | Acceso total. Gestión de parámetros, cierre mensual, usuarios y toda la operación. |
| `operator` | Gestión operativa: compañías, financiamientos, transacciones, cuentas por cobrar/pagar. |
| `member` | Acceso de lectura. Ve financiamientos, cierres y widgets del escritorio. |
| `company_user` | Representante de una compañía. Registra financiamientos y solicitudes de cobro, ve únicamente los datos de su compañía. |

---

### Módulos

#### Operaciones

**Financiamientos**
Gestión del ciclo de vida completo de cada financiamiento:
- Estados: `Solicitado → Desembolsado → Cobrado / Cancelado`
- Código auto-generado con formato `FN000001`
- Comisión y monto a transferir calculados automáticamente en base a los parámetros del sistema
- Carga de documentos de soporte (órdenes de compra, facturas)
- Acciones en lote: desembolsar y cobrar múltiples registros

**Transacciones**
Registro bancario de desembolsos y cobros:
- Tipo `Desembolso`: Fondo → Compañía (confirma automáticamente)
- Tipo `Cobro`: Deudor → Fondo (requiere confirmación de operador cuando lo crea un `company_user`)
- Vincula uno o más financiamientos de la misma compañía
- Registra banco, número de transacción y fecha
- Vista detalle con lista enlazada de financiamientos asociados

**Cuentas por Cobrar**
Vista de financiamientos desembolsados pendientes de cobro. Incluye antigüedad en días, alerta de vencidos y acción en lote para generar transacciones de cobro.

**Cuentas por Pagar**
Vista de financiamientos solicitados pendientes de desembolso. Incluye días en espera y acción en lote para generar transacciones de desembolso.

#### Cierre Mensual

Cálculo y ejecución del cierre de distribución mensual:

1. Se selecciona el período (mes/año)
2. El sistema calcula la distribución basándose en las comisiones cobradas en ese período:

```
Comisiones del período
  − Rendimiento fijo  (capital × fixed_return_pct)
  = Ganancia neta
  − Reserva           (ganancia × reserve_pct)
  = Monto post-reserva
  − Aporte en especie (post-reserva × in_kind_pct)
  = Disponible para capital

Por cada miembro de capital:
  Pago fijo         = contribución × fixed_return_pct
  Pago proporcional = disponible × fund_percentage
  Total             = fijo + proporcional
```

3. Se muestra la vista previa antes de confirmar
4. Al ejecutar se persisten: el cierre, las distribuciones individuales y una instantánea de los parámetros usados (trazabilidad)
5. Cada período solo puede cerrarse una vez

#### Administración

**Parámetros**
Ajuste de los parámetros de distribución sin necesidad de modificar código. Todos los cambios quedan registrados en historial con fecha, período y usuario responsable.

| Parámetro | Descripción | Default |
|---|---|---|
| `commission_pct` | % de comisión sobre el monto del financiamiento | 5.00% |
| `fixed_return_pct` | % de rendimiento fijo mensual sobre el capital aportado | 3.00% |
| `reserve_pct` | % de reserva sobre la ganancia neta | 20.00% |
| `in_kind_pct` | % del monto post-reserva para el aporte en especie | 50.00% |
| `default_term_days` | Plazo predeterminado en días para nuevos financiamientos | 15 días |

**Compañías**
Administración de las empresas clientes del fondo (RNC, contacto, estado activo).

**Clientes (Deudores)**
Administración de los deudores vinculados a cada compañía.

**Miembros del Fondo**
Gestión de los miembros del fondo, su tipo (capital o en especie), monto de contribución y porcentaje de participación.

**Usuarios**
Gestión de cuentas de usuario y asignación de roles.

---

### Modelos principales

| Modelo | Descripción |
|---|---|
| `Company` | Compañía cliente del fondo |
| `Client` | Deudor vinculado a una compañía |
| `Financing` | Financiamiento (factura u orden de compra) |
| `FinancingDocument` | Documento de soporte adjunto al financiamiento |
| `Transaction` | Movimiento bancario (desembolso o cobro) |
| `FundMember` | Miembro del fondo (capital o en especie) |
| `Parameter` | Parámetro de configuración del sistema |
| `ParameterHistory` | Historial de cambios de parámetros |
| `MonthlyClosing` | Cierre mensual ejecutado |
| `ClosingDistribution` | Distribución individual por miembro en un cierre |
| `ClosingParametersSnapshot` | Instantánea de parámetros usados en cada cierre |

---

## Entornos

Se recomienda mantener dos instancias locales:

| Instancia | Propósito |
|---|---|
| `cormart-factory` | Desarrollo — datos de prueba, nuevas funcionalidades |
| `cormart-staging` | Pre-producción — datos reales, validación antes de producción |

Para sincronizar staging con los últimos cambios:
```bash
cd cormart-staging
git pull origin master
php artisan migrate
```

---

## Versionamiento

El proyecto sigue [Semantic Versioning](https://semver.org/lang/es/):

- **Mayor** (`x.0.0`): cambios que rompen compatibilidad o rediseño de arquitectura
- **Menor** (`0.x.0`): nuevas funcionalidades
- **Parche** (`0.0.x`): correcciones y mejoras menores

Ver el historial de versiones con:
```bash
git tag -l
```

---

## Licencia

Uso privado — Cormart / Familia Cornelio Pérez.
