# Flava Studio · flava.cl

Sistema de reservas y gestión para **Flava Studio**. PHP 8+, MySQL/MariaDB,
arquitectura MVC modular, sin dependencias externas ni Composer: se instala en
un hosting tradicional con cPanel, Apache, PHP y phpMyAdmin.

> **Prioridad del proyecto**
> 1. Que reservar sea simple.
> 2. Que recepción administre rápido.
> 3. Que cada barbero vea sólo lo que necesita.
> 4. Que la arquitectura pueda crecer.

---

## Qué incluye la versión 1.0.0 (MVP)

| Área | Estado |
|---|---|
| Booking público sin registro (servicio → barbero → fecha/hora → checkout) | ✅ |
| Motor de disponibilidad único (web, recepción, admin y API) | ✅ |
| Prevención de doble reserva (transacción + índice único en el motor) | ✅ |
| CRM automático con deduplicación por RUT / email / teléfono | ✅ |
| Validación de RUT chileno en backend y frontend | ✅ |
| Gestión sin cuenta por código + token (ver, reprogramar, cancelar, .ics) | ✅ |
| Panel de administración: reservas, clientes, barberos, servicios, pagos | ✅ |
| Panel de recepción: agenda del día, walk-in, cobros, bloqueos | ✅ |
| Panel del barbero: agenda, estados, notas técnicas, sus clientes | ✅ |
| Panel de Súper Administrador: GitHub, despliegues, migraciones, respaldos, logs | ✅ |
| Horarios semanales con varios bloques por día | ✅ |
| Bloqueos (almuerzo, vacaciones, permisos, cierre de local) | ✅ |
| Pagos manuales, reembolsos y estados de pago | ✅ |
| Auditoría, historial de estados y cola de notificaciones | ✅ |
| Calendario administrativo (día / semana) | ✅ |
| API v1 pública e interna | ✅ |
| Multisucursal, fidelización, cupones, tienda | 🏗️ preparado en la base de datos |
| Email, WhatsApp, Webpay, Mercado Pago | 🏗️ arquitectura lista, integración en Etapa 2 |

---

## Requisitos

- PHP **8.1 o superior** (probado en 8.5) con `pdo_mysql`, `mbstring`, `curl` y `openssl` o `sodium`
- MySQL **5.7+** / MariaDB **10.3+** (InnoDB, utf8mb4)
- Apache con `mod_rewrite`
- Sin Composer, sin Node.js, sin SSH obligatorio

---

## Instalación rápida

```bash
# 1. Base de datos: crear flava_db (utf8mb4_unicode_ci) e importar el esquema
mysql -u usuario -p flava_db < database/flava.sql

# 2. Configuración
cp .env.example .env      # y completar credenciales

# 3. Clave de cifrado de secretos
php bin/flava key:generate

# 4. Verificar la instalación
php bin/flava install:check
```

Luego entra a `/login` con **admin@flava.cl** / **Flava2026!** — el sistema exige
cambiar esa contraseña en el primer ingreso.

La guía detallada, incluida la alternativa para hostings donde no se puede mover
el DocumentRoot, está en **[docs/INSTALACION.md](docs/INSTALACION.md)**.

---

## Estructura

```text
/app
    /Controllers      Reciben la solicitud, validan, delegan y devuelven la vista o JSON
        /Admin  /Reception  /Barber  /SuperAdmin  /Api
    /Models           Acceso a datos con PDO preparado
    /Services         Lógica de negocio (AvailabilityService, BookingService...)
        /Payments     Adaptadores de pasarela (Webpay, Mercado Pago)
        /Notifications  Canales de notificación
        /System       GitHub, Git, despliegues, migraciones, respaldos, mantención
    /Middleware       Auth, Role, Csrf, Maintenance, Guest
    /Support          Estados, RUT, dinero, fechas, cifrado
    /Views            Sólo interfaz: nunca consultan la base de datos
/core                 Router, Database, Model, Controller, View, Auth, Validator...
/config               app.php · database.php · github.php · version.php
/public               Front controller único + assets (única carpeta expuesta)
/routes               web.php · api.php
/database             flava.sql · /migrations · /seeds
/storage              /logs · /backups · /cache · /framework
/bin                  flava (consola) · dev-router.php
/docs                 Instalación, despliegue y arquitectura
```

Reglas que sostienen la arquitectura:

- **Un único punto de entrada**: todo pasa por `/public/index.php` y el router.
- **Las vistas no consultan la base de datos**, sólo reciben datos del controlador.
- **Un único motor de disponibilidad**: `AvailabilityService` sirve al booking
  web, a recepción, a administración y a la API. No hay dos cálculos distintos.
- **Estados centralizados**: `BookingStatus`, `PaymentStatus`, `PaymentMethod`,
  `BookingSource` y `Role`. Nunca se escriben esos strings sueltos.
- **Toda consulta va preparada** con PDO. Nunca se concatena entrada de usuario.
- **Todo formulario que modifica estado lleva CSRF** y valida en el backend.

---

## Consola

```bash
php bin/flava install:check            # verifica requisitos y base de datos
php bin/flava key:generate             # genera APP_KEY para cifrar secretos
php bin/flava user:create              # crea un usuario interno
php bin/flava user:password <email>    # contraseña temporal
php bin/flava migrate                  # respalda y ejecuta migraciones pendientes
php bin/flava migrate:status           # estado de las migraciones
php bin/flava make:migration "texto"   # crea un archivo de migración
php bin/flava backup [etiqueta]        # respalda la base de datos
php bin/flava notifications:process    # procesa la cola (para el cron)
php bin/flava maintenance:on|off       # modo mantención
php bin/flava routes                   # lista las rutas registradas
```

En hostings sin acceso a consola, todo lo esencial (migraciones, respaldos,
mantención, actualizaciones) también está en el panel de Súper Administrador.

---

## Desarrollo local

```bash
php -S localhost:8080 -t public bin/dev-router.php
```

Datos de demostración para probar el sistema completo:

```bash
mysql -u usuario -p flava_db < database/seeds/demo_data.sql
```

Crea dos barberos con horarios distintos, tres clientes, un bloqueo de almuerzo
y usuarios de cada rol (contraseña `Flava2026!`):

| Rol | Email |
|---|---|
| Súper Administrador | admin@flava.cl |
| Recepción | recepcion@flava.cl |
| Barbero | sebastian@flava.cl |
| Barbero | matias@flava.cl |

⚠️ No importar `demo_data.sql` en producción.

---

## Flujo oficial de trabajo

```text
DESARROLLO LOCAL  →  git push  →  GITHUB  →  Panel SUPER_ADMIN  →  flava.cl
```

Producción nunca se edita a mano. El detalle está en
**[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)**.

---

## Documentación

- [docs/HOSTINGER.md](docs/HOSTINGER.md) — instalación paso a paso en Hostinger (la vía más simple)
- [docs/INSTALACION.md](docs/INSTALACION.md) — instalación paso a paso y hosting compartido
- [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) — GitHub, actualizaciones, migraciones y rollback
- [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) — decisiones técnicas y cómo extender el sistema
- [docs/DISENO.md](docs/DISENO.md) — sistema visual: geometría, bordes, íconos SVG y componentes
- [CHANGELOG.md](CHANGELOG.md) — historial de versiones

---

© Flava Studio · [flava.cl](https://flava.cl)
