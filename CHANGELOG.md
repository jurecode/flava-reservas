# Changelog · Flava Studio

Todas las versiones relevantes de flava.cl.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y
versionado [SemVer](https://semver.org/lang/es/).

---

## v1.3.9 — 2026-08-25

### Fixed

- **La portada decía «Santiago» aunque la barbería estuviera en otra parte.**
  La ubicación del héroe estaba escrita a mano en la plantilla, así que una
  barbería en Viña del Mar veía la ciudad equivocada en su propia página.
  Ahora sale de los datos de la sucursal: usa la comuna, y si no está, la
  ciudad; si tampoco, deduce la comuna del último tramo de la dirección
  —descartándolo si contiene números, porque entonces es parte de la calle—.
  Sin ningún dato utilizable, muestra sólo «Barbería» en vez de inventarlo.

---

## v1.3.8 — 2026-08-25

### Changed

- **La página de despliegues explica qué falta.** Antes deshabilitaba el botón
  «Crear respaldo y actualizar» mostrando un único aviso genérico, sin decir cuál
  de los cuatro requisitos no se cumplía ni qué hacer al respecto. Ahora los
  lista uno por uno —integración activa, token, Git ejecutable desde PHP y
  carpeta con repositorio— y para cada uno que falta indica cómo resolverlo.
- **Se añadió el procedimiento alternativo**, visible siempre que el despliegue
  automático no sea posible: respaldar, actualizar los archivos desde el panel
  del hosting, ejecutar las migraciones y comprobar la versión. En hosting
  compartido esa es la vía real, y el panel no la mencionaba.
- Aunque el despliegue automático no esté disponible, se puede consultar GitHub
  desde la misma página para saber si hay algo nuevo.

---

## v1.3.7 — 2026-08-25

### Fixed

- **Guardar la configuración de GitHub devolvía error 500.** La regla que valida
  el *owner* se declaraba como texto y su patrón contenía el carácter `|`, que
  es justo el separador de esa sintaxis: el validador partía el patrón en dos
  trozos inválidos y `preg_match()` fallaba. Las reglas con expresiones
  regulares pasan ahora como array, donde el separador no interviene.
- **El validador ya no se cae ante un patrón mal formado.** Antes cualquier
  regex inválida se convertía en excepción fatal. Ahora la validación falla
  —el valor no se da por bueno— y el patrón queda registrado en el log con la
  causa, en vez de tumbar la petición.

---

## v1.3.6 — 2026-08-25

### Changed

- La portada prometía «menos de un minuto» en el héroe y «30 segundos» dos
  secciones más abajo. Ahora dice 30 segundos en ambos sitios, que es lo que
  mide el flujo real.
- El código fuente del sitio público incluye un comentario con la versión
  instalada (`<!-- Flava Studio vX.Y.Z -->`), útil para confirmar de un vistazo
  qué versión está desplegada.

---

## v1.3.5 — 2026-08-25

Repositorio listo para publicar.

### Fixed

- **Los `.htaccess` de seguridad quedaban fuera del control de versiones.** Las
  reglas `/public/uploads/*` y `/storage/framework/*` ignoraban toda la carpeta,
  incluidos los `.htaccess` que impiden ejecutar scripts dentro de las subidas.
  Al desplegar desde el repositorio, la carpeta de imágenes habría llegado al
  servidor sin protección. Ahora se excluye el contenido pero se conservan los
  archivos de seguridad y los `.gitkeep`.
- `config/installed.php` y `.claude/` añadidos al `.gitignore`.

### Verificado

Una copia limpia del repositorio (258 archivos) no lleva `.env`,
`config/database.php`, `config/secrets.php` ni `config/installed.php`; sí lleva
los seis `.htaccess` de seguridad; y arranca directo en `/instalar`.

---

## v1.3.4 — 2026-08-25

Empaquetado limpio para subir al hosting.

### Fixed

- **`config/installed.php` no estaba en `.gitignore`.** Comprimir la carpeta del
  proyecto se llevaba la marca de instalación del equipo de desarrollo: el
  servidor arrancaba creyendo estar instalado, el asistente `/instalar` respondía
  404 y —junto con un `config/database.php` apuntando a una base local— el
  resultado era un error 500 sin explicación posible.
- `diagnostico.php` funciona justo en ese estado: sigue activo mientras la base
  de datos no responda, aunque exista la marca de instalación, y avisa
  explícitamente de esa combinación incoherente. Antes fallaba al leer un
  `config/database.php` basado en `env()`.

### Added

- **`php bin/flava package`**: genera el `.zip` para subir al hosting excluyendo
  lo que nunca debe salir del equipo de desarrollo —credenciales, marca de
  instalación, `.env`, logs, cache, respaldos e imágenes subidas— y creando las
  carpetas vacías que el servidor necesita.
- `diagnostico.php`: informe del servidor independiente del framework (PHP,
  extensiones, permisos reales de escritura, sesiones, base de datos y últimas
  líneas del log). Se desactiva solo cuando el sistema queda sano.

---

## v1.3.3 — 2026-08-25

Diagnóstico de errores durante la instalación.

### Changed

- **Mientras el sistema no está instalado, los errores se muestran completos.**
  Antes aparecía «Algo salió mal» sin más, y en un hosting compartido leer los
  logs para averiguar qué pasó es incómodo. Quien ve esa pantalla es quien está
  instalando, y todavía no hay datos de clientes que proteger. Una vez creado
  `config/installed.php`, el comportamiento vuelve a ser el de producción:
  mensaje genérico y detalle sólo en el log.
- La pantalla técnica traduce los fallos típicos de un despliegue nuevo a
  instrucciones concretas: sin conexión a la base, falta de permisos de
  escritura, archivos que no se subieron.

### Fixed

- **Las sesiones podían tumbar la primera página.** Si el directorio de sesiones
  del sistema no era escribible —habitual en hosting compartido—,
  `session_start()` emitía un aviso que el manejador de errores convertía en
  excepción fatal. Ahora se comprueba antes, se usa
  `storage/framework/sessions` como alternativa, y si aun así falla el mensaje
  dice exactamente qué revisar.

---

## v1.3.2 — 2026-08-25

El despliegue en hosting compartido deja de depender de la reescritura de URLs.

### Fixed

- **El `.htaccess` de la raíz podía dejar de aplicarse.** La regla que
  reescribía *todo* el tráfico hacia `/public` se reaplicaba en algunos
  servidores —`REQUEST_URI` no cambia durante las reescrituras internas—,
  entrando en un bucle que el servidor corta dejando de procesar el archivo.
  El síntoma era un 403 o un sitio sin estilos con el `.htaccess` presente.

### Changed

- **El front controller de la raíz (`/index.php`) es ahora real**, no sólo una
  pantalla de diagnóstico: arranca la aplicación cuando el dominio apunta a la
  carpeta del proyecto. Ya no hace falta cambiar el directorio raíz del dominio
  —opción que varios planes de Hostinger no ofrecen.
- **Los archivos estáticos usan rutas reales** (`/public/assets`,
  `/public/uploads`), así el servidor los entrega directamente. El sitio se ve
  completo incluso sin ninguna reescritura activa; el `.htaccess` sólo aporta
  las URLs limpias.
- `public_prefix()` resuelve el prefijo según el punto de entrada, de modo que
  los enlaces salen correctos con el dominio apuntando a la raíz o a `/public`.
- `/public/...` redirige (301) a su equivalente en la raíz, salvo los archivos
  estáticos: una sola URL canónica por página.

---

## v1.3.1 — 2026-08-25

Correcciones de despliegue en hosting compartido, detectadas al instalar en
Hostinger.

### Fixed

- **`<Directory>` dentro de `public/.htaccess` era inválido.** Esa directiva
  sólo se admite en la configuración del servidor: en un `.htaccess` Apache
  responde *500 Internal Server Error* («Directory not allowed here»). La
  protección de `/public/uploads` pasó a su propio `.htaccess`, que además
  bloquea la ejecución de cualquier script en vez de depender de `php_flag`
  —que necesita mod_php y no funciona con LiteSpeed ni PHP-FPM, que es lo que
  usa Hostinger.
- Las reglas `Require all denied` ahora tienen alternativa para Apache 2.2
  (`Order deny,allow`), por si el hosting no trae `mod_authz_core`.

### Added

- **Pantalla de diagnóstico en la raíz** (`/index.php`). Si el reenvío hacia
  `/public` no está funcionando —normalmente porque el `.htaccess` no se subió—
  el visitante ya no ve un 403 sin explicación, sino una página que identifica
  la causa y ofrece **crear el archivo que falta con un clic**. Cuando el
  reenvío funciona, esta pantalla nunca aparece.
- `htaccess-raiz.txt` y `htaccess-public.txt`: copias sin punto inicial de los
  dos `.htaccess`, para subirlas y renombrarlas cuando el descompresor se salta
  los archivos ocultos.
- `docs/HOSTINGER.md`: sección dedicada al error 403 con las tres formas de
  resolverlo.

---

## v1.3.0 — 2026-08-25

Instalador web. Pensado para hosting compartido tipo Hostinger: la base de datos
se crea desde el panel del hosting y todo lo demás se resuelve en el navegador.

### Added

- **Asistente de instalación en `/instalar`** con seis pasos: requisitos del
  servidor, conexión a la base, creación de tablas, cuenta de administrador,
  datos del negocio y cierre verificado.
- `InstallerService`: comprueba PHP y extensiones, valida la conexión, escribe
  `/config/database.php`, genera `APP_KEY`, importa el esquema, crea el
  SUPER_ADMIN y guarda los datos del negocio.
- `InstallMiddleware`: mientras falte instalar, toda ruta lleva al asistente;
  una vez instalado, el asistente responde 404 y no puede relanzarse.
- **Mensajes de error accionables** en la conexión a la base: distinguen entre
  contraseña incorrecta, base inexistente, host equivocado y —el tropiezo más
  común en Hostinger— usuario no asignado a la base.
- **Salida manual** si `/config` no permite escritura: el asistente muestra el
  contenido exacto del archivo para crearlo desde el Administrador de archivos.
- Router: soporte de middleware global (`globalMiddleware()`).
- [docs/HOSTINGER.md](docs/HOSTINGER.md): guía completa paso a paso.

### Fixed

- **Los respaldos no se podían restaurar.** `mysqldump` en MySQL 8+ añade
  `SET @@GLOBAL.GTID_PURGED` al volcado y la restauración falla con el error
  3546. Se añadió `--set-gtid-purged=OFF`, y `--no-tablespaces` para no depender
  del privilegio PROCESS que los hostings compartidos no conceden. Verificado
  restaurando un respaldo real en una base limpia.
- **APP_URL se deducía mal en una instalación nueva.** El valor por defecto
  apuntaba a `flava.cl`, así que el instalador habría redirigido al usuario
  fuera de su propio dominio. Ahora, si `APP_URL` no está configurada, la URL
  base se deduce del dominio por el que llega la petición.
- El arranque ya no depende de la base de datos: durante la instalación todavía
  no existe.

---

## v1.2.0 — 2026-08-24

Servicios con imagen.

### Added

- **Subida de imágenes** para servicios y barberos desde administración, con
  vista previa inmediata y opción de quitar la actual (`UploadService`).
  El tipo se valida leyendo la imagen, el archivo se reprocesa con GD y se
  reduce a 1200 px, y al reemplazar se borra la anterior.
- **Portadas generadas** (`App\Support\Cover`) para servicios y barberos que
  todavía no tienen foto: degradado oscuro de paleta curada, textura de panal e
  ícono del servicio. Deterministas y en línea, así nada se ve roto ni vacío.
- Componentes `service-tile.php` (tarjeta-imagen), `service-row.php` (fila
  compacta) e `image-field.php` (campo de subida con vista previa).

### Changed

- **Servicios destacados rediseñado**: cuadrícula editorial `.showcase` donde la
  primera tarjeta ocupa el doble y las otras la acompañan. La foto llena la
  tarjeta, el contenido va encima sobre un degradado y el precio es un chip
  sólido. Toda la tarjeta lleva directo a reservar.
- **Página de servicios** reorganizada: destacados arriba como tarjetas-imagen y
  el resto en filas compactas con miniatura, para no obligar a tanto scroll.
- Las tarjetas de barbero usan portada generada en vez de sólo las iniciales.

### Fixed

- **Los avisos de obsolescencia de PHP ya no tumban la página.** El manejador de
  errores los convertía en excepciones fatales, así que cualquier función
  obsoleta —o una actualización de la versión de PHP— podía provocar un 500.
  Ahora se registran como advertencia y la petición continúa.
- `imagedestroy()`, obsoleta desde PHP 8.5, ya no se usa.
- La etiqueta «Destacado» era invisible: `backdrop-filter` pintaba sobre el
  texto en algunos motores. Ahora usa fondo sólido.

---

## v1.1.0 — 2026-08-24

Refresh visual. Sin cambios en la funcionalidad ni en la base de datos.

### Changed

- **Geometría cuadrada** en todo el sistema: radios de 3 a 8 px, botones
  rectangulares en vez de píldoras.
- **Tarjetas sin borde**: se separan del fondo por contraste. El borde quedó
  reservado para lo seleccionado (`.is-selected`), lo que exige atención
  (`.card-accent`) y las zonas destructivas (`.card-danger`).
- **Checkout en dos columnas** en escritorio, con el resumen fijo a la derecha.
  En móvil el resumen va primero, antes del formulario.
- **Filas de selección de altura uniforme** en el booking: la lista de servicios
  y barberos se lee de un vistazo, sin filas que crezcan por la descripción.
- **Tarjeta de cita del barbero** rediseñada: cabecera, nota técnica a lo ancho y
  acciones separadas, sin elementos que se apretujen en móvil.
- Las políticas de reserva y la cancelación de reservas pasaron a **modales**.

### Added

- `App\Support\Icon`: 81 íconos SVG en línea con `currentColor`, más los mapas
  de dominio (`forPaymentMethod`, `forBookingStatus`, `forFlash`).
- Helper global `icon('nombre', tamaño)` disponible en todas las vistas.
- Componente `components/modal.php` con apertura declarativa
  (`data-modal-open`), cierre con Escape, clic fuera o `data-modal-close`.
- [docs/DISENO.md](docs/DISENO.md) con las reglas del sistema visual.

### Removed

- **Todos los emoji de la interfaz**, reemplazados por SVG. Se mantienen sólo en
  los mensajes de WhatsApp al cliente, donde son contenido del mensaje.

---

## v1.0.0 — 2026-08-24

Primera versión. Núcleo de reservas completo y operativo.

### Added

- **Booking público sin registro**: servicio → barbero → fecha y hora → checkout,
  diseñado mobile first para completarse en menos de un minuto.
- **Motor de disponibilidad único** (`AvailabilityService`) compartido por el
  booking web, recepción, administración y la API: horarios semanales con varios
  bloques por día, duración real del servicio, bloqueos, colchón entre citas,
  anticipación mínima y ventana máxima de reserva.
- **Prevención de doble reserva en tres capas**: interfaz, transacción con
  `SELECT ... FOR UPDATE` e índice único sobre columna generada `active_slot`.
- **CRM automático** con deduplicación por RUT, email y teléfono; métricas de
  visitas, gasto, no-show y barbero habitual recalculadas desde datos reales.
- **Validación de RUT chileno** en backend y frontend, con normalización y
  formato de presentación separados.
- **Gestión sin cuenta**: código público `FLV-AAMMDD-XXXX` + token de 64 hex para
  ver, reprogramar, cancelar y descargar el `.ics` de la reserva.
- **Panel de administración**: dashboard con KPIs reales, reservas con filtros y
  paginación, calendario día/semana, clientes, barberos, horarios, servicios,
  bloqueos, pagos, usuarios, configuración, reportes y auditoría.
- **Panel de recepción**: agenda del día en columnas por barbero, creación manual
  de reservas, walk-in, buscador de clientes, cobros y bloqueos.
- **Panel del barbero**: agenda diaria con línea de tiempo, cambios de estado,
  notas técnicas del cliente, sus clientes y sus bloqueos.
- **Panel de Súper Administrador**: estado del sistema, GitHub, despliegues con
  respaldo y rollback, migraciones, respaldos, logs, rutas y modo mantención.
- **Integración con GitHub**: configuración del repositorio, token cifrado con
  libsodium/AES-256-GCM, prueba de conexión, búsqueda de actualizaciones y
  despliegue controlado de 13 pasos.
- **Sistema de migraciones** con respaldo automático previo y registro de lo
  ejecutado; `flava.sql` queda reservado para instalaciones nuevas.
- **Respaldos** con `mysqldump` o volcado PHP equivalente para hostings sin
  acceso a comandos, guardados fuera del webroot.
- **Auditoría** (`activity_logs`) e **historial de estados**
  (`booking_status_history`) de cada reserva.
- **Cola de notificaciones** (`notifications`) que ya registra confirmaciones y
  recordatorios, lista para conectar email y WhatsApp mediante cron.
- **API v1** pública (servicios, barberos, disponibilidad, reservas) e interna
  (slots, buscador de clientes, calendario, estadísticas).
- **Consola** `bin/flava` con verificación de instalación, creación de usuarios,
  migraciones, respaldos, mantención y procesamiento de la cola.
- Páginas de error 403, 404, 405, 419, 500 y 503 con la identidad de la marca.

### Prepared

Arquitectura y base de datos listas, integración pendiente para etapas siguientes:
email, WhatsApp, Webpay, Mercado Pago, tienda de productos, fidelización,
cupones, membresías y multisucursal.

### Security

- Consultas exclusivamente preparadas con PDO; nombres de columna validados
  contra lista blanca.
- CSRF en todos los métodos que modifican estado.
- Autorización verificada en el backend por ruta, con jerarquía de roles.
- Bloqueo de login tras 5 intentos fallidos durante 10 minutos.
- Contraseñas con `password_hash()` y rehash automático; cambio obligatorio en
  el primer ingreso.
- Secretos cifrados con clave fuera del webroot; enmascarados en todos los logs.
- `GitService` ejecuta únicamente operaciones predefinidas con argumentos
  validados y escapados: no existe ningún campo para comandos libres.
- Carpetas internas bloqueadas por `.htaccess` propio y por la redirección de la
  raíz, para hostings donde no se puede mover el DocumentRoot.
