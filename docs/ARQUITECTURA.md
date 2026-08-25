# Arquitectura · Flava Studio

Este documento explica **por qué** el sistema está hecho así, para que cualquier
cambio futuro respete las decisiones que lo sostienen.

---

## Principios

1. **Un único punto de entrada.** Todo pasa por `/public/index.php` → `Router`.
   No existen archivos sueltos tipo `booking.php?id=12`.
2. **Las vistas no consultan la base de datos.** Reciben datos ya procesados.
3. **Un único motor de disponibilidad.** Si hubiera dos cálculos, aparecerían
   inconsistencias entre la web y el mostrador.
4. **El backend manda.** El frontend ayuda a la experiencia; toda validación
   crítica se repite en el servidor.
5. **Los estados están centralizados.** Nunca se escribe `'confirmed'` suelto.

---

## Capas

```text
Request → Router → Middleware → Controller → Service → Model → PDO
                                     ↓
                                   View
```

| Capa | Responsabilidad | Qué NO hace |
|---|---|---|
| **Controller** | Recibir, validar, delegar, responder | Lógica de negocio, SQL |
| **Service** | Reglas de negocio y orquestación | HTML, acceso a `$_POST` |
| **Model** | Consultas preparadas de su entidad | Reglas de negocio |
| **View** | Interfaz | Consultar la base de datos |

---

## El motor de disponibilidad

`App\Services\AvailabilityService` es la única fuente de verdad sobre qué
horarios están libres. Lo consumen por igual el booking público, recepción,
administración y la API.

Considera, en este orden:

1. Horarios semanales del barbero (admite varios bloques por día).
2. Duración real del servicio, no intervalos fijos de 30 minutos.
3. Reservas existentes en estados que bloquean la agenda.
4. Bloqueos del barbero y de la sucursal (almuerzo, vacaciones, feriados).
5. Hora actual y anticipación mínima configurable.
6. Colchón entre citas configurable.
7. Ventana máxima de anticipación.

El parámetro `$internal` distingue al cliente web del personal: recepción puede
agendar sin la anticipación mínima, pero **nunca** puede solaparse con otra cita.

---

## Prevención de doble reserva

Tres barreras, de más externa a más interna:

1. **La interfaz** sólo ofrece horarios calculados por el motor.
2. **La transacción**: al confirmar, `BookingService::create()` abre una
   transacción y vuelve a validar con `SELECT ... FOR UPDATE`, que bloquea el
   rango del barbero en esa fecha.
3. **El motor de base de datos**: la tabla `bookings` tiene una columna generada

   ```sql
   active_slot VARCHAR(48) GENERATED ALWAYS AS (
       IF(status IN ('cancelled','no_show'), NULL,
          CONCAT(barber_id, '|', booking_date, '|', start_time))
   ) STORED
   ```

   con índice `UNIQUE`. Como MySQL ignora los `NULL` en índices únicos, una
   reserva cancelada libera el horario automáticamente, y dos reservas activas
   con el mismo barbero/fecha/hora son **físicamente imposibles**.

Si la barrera 3 se activa, el error 23000 se traduce al mensaje del cliente:
*"Este horario acaba de ser reservado. Selecciona otro disponible."*

> Nota técnica: `barber_id` es columna base de `active_slot`, y MySQL prohíbe
> `ON UPDATE CASCADE` sobre columnas base de una generada `STORED`. Por eso esa
> clave foránea usa `RESTRICT` en ambos sentidos. No cambiarlo.

---

## CRM sin registro

El cliente nunca crea una cuenta. Cada reserva genera o actualiza su ficha:

```text
buscar por RUT → por email → por teléfono
   ├── existe  → completa los datos que faltaban, sin pisar los actuales
   └── no existe → crea la ficha
```

El RUT se guarda en dos columnas: `rut` con formato de presentación
(`12.345.678-5`) y `rut_normalized` canónico (`12345678-5`) con índice único.
Eso permite buscar rápido y evitar duplicados.

---

## Seguridad

| Riesgo | Mitigación |
|---|---|
| SQL injection | PDO preparado siempre; nombres de columna validados con lista blanca |
| XSS | `e()` en toda salida; nunca se imprime input sin escapar |
| CSRF | Middleware en POST/PUT/PATCH/DELETE, con token por sesión |
| Escalada de privilegios | `RoleMiddleware` valida en backend; jerarquía en `Role` |
| Fuerza bruta en login | Bloqueo tras 5 intentos por 10 minutos + retardo aleatorio |
| Fuga de datos de reservas | Código público + token de 64 hex; comparación en tiempo constante |
| Secretos expuestos | Cifrado libsodium/AES-256-GCM, clave fuera del webroot, logs enmascarados |
| Inyección de comandos | `GitService` sólo ejecuta operaciones predefinidas con argumentos validados |
| Archivos internos accesibles | `.htaccess` por carpeta + redirección desde la raíz |

---

## Preparado, no implementado

Estas piezas tienen su lugar reservado en la base de datos y en el código, pero
su integración externa llega en etapas posteriores. Están así **a propósito**:
agregar la integración no obliga a rehacer nada.

| Módulo | Punto de extensión |
|---|---|
| Email | `EmailService` implementa `ChannelInterface`; añadir driver `smtp` |
| WhatsApp | `WhatsAppService`; añadir driver `cloud` (WhatsApp Business Cloud API) |
| Webpay | `WebpayGateway implements GatewayInterface` |
| Mercado Pago | `MercadoPagoGateway implements GatewayInterface` |
| Tienda | Tablas `products`, `orders`, `order_items` + modelos base |
| Fidelización | `loyalty_transactions` (movimientos, no saldo suelto) |
| Cupones | Tabla `coupons` y `bookings.coupon_id` |
| Multisucursal | `branch_id` en users, customers, barbers, bookings, blocked_times, products, orders |
| App móvil | API v1 ya expuesta y separada de las vistas |

Ningún controlador depende de un proveedor concreto: `BookingController` no sabe
qué pasarela existe, y `NotificationService` no sabe cómo se envía un WhatsApp.

---

## Cómo agregar un módulo nuevo

El orden que evita romper lo existente:

1. Analiza el requerimiento y revisa qué ya existe.
2. Identifica modelos y relaciones; escribe la **migración**, no toques `flava.sql`.
3. Define las rutas en `/routes/web.php` con su middleware de permisos.
4. Crea el controlador: recibe, valida, delega.
5. Pon la lógica en un **Service**, no en el controlador.
6. Crea las vistas reutilizando los componentes existentes.
7. Registra las acciones importantes con `ActivityLogger`.
8. Prueba el flujo completo y los permisos de cada rol.
9. Actualiza `CHANGELOG.md` y `config/version.php`.
