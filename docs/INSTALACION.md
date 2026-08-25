# Instalación · Flava Studio

Guía completa para dejar el sistema funcionando en `flava.cl`.

---

## 1. Requisitos del servidor

| Componente | Mínimo | Comprobar con |
|---|---|---|
| PHP | 8.1 (probado en 8.5) | `php -v` o el panel de cPanel |
| Extensiones | `pdo_mysql`, `mbstring`, `curl`, `openssl` o `sodium` | `php bin/flava install:check` |
| MySQL / MariaDB | 5.7+ / 10.3+ | phpMyAdmin → pestaña "Bases de datos" |
| Apache | con `mod_rewrite` | `.htaccess` incluido |

No se necesita Composer, Node.js ni SSH.

---

## 2. Crear la base de datos

En phpMyAdmin:

1. **Bases de datos → Crear**: nombre `flava_db`, cotejamiento `utf8mb4_unicode_ci`.
2. Selecciónala y entra en **Importar**.
3. Sube `database/flava.sql` y ejecuta.

Debe crear **24 tablas**. Si tu usuario MySQL puede crear bases, también puedes
descomentar las dos primeras líneas del archivo y ejecutarlo directamente.

> ⚠️ `flava.sql` es **sólo para instalaciones nuevas**. Nunca lo vuelvas a importar
> sobre una base con datos reales: para eso están las migraciones (§5).

---

## 3. Subir los archivos y configurar

Sube todo el proyecto por FTP o el administrador de archivos de cPanel.

### Configuración

Copia `.env.example` a `.env` y completa:

```ini
APP_URL=https://flava.cl
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_DATABASE=flava_db
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña
```

Si tu hosting no permite archivos que empiezan con punto, usa la alternativa:
copia `config/secrets.example.php` a `config/secrets.php` y pon ahí las
credenciales. El sistema lee ambos.

### Clave de cifrado

Genera la clave que protege los secretos (como el token de GitHub):

```bash
php bin/flava key:generate
```

Sin consola, genera una desde phpMyAdmin no es posible: pídela a tu desarrollador
o usa cualquier generador de 32 bytes en base64 y guárdala como
`APP_KEY=base64:...`. **Debe vivir fuera de `/public`.**

---

## 4. DocumentRoot

### Opción A — recomendada

Apunta el dominio a la carpeta `/public`:

- **cPanel → Dominios → Cambiar la raíz del documento** a `.../flava/public`
- O en la configuración de Apache: `DocumentRoot /var/www/flava/public`

Sólo `/public` queda expuesto. El resto del código es inalcanzable desde la web.

### Opción B — hosting que no permite cambiar el DocumentRoot

Sube el proyecto completo dentro de `public_html`. El archivo `.htaccess` de la
raíz ya redirige todo el tráfico a `/public` manteniendo las URLs limpias, y
bloquea el acceso directo a `/app`, `/core`, `/config`, `/storage`, `/database`,
`/routes` y `/bin`. Cada una de esas carpetas trae además su propio `.htaccess`
con `Require all denied`, como segunda barrera.

Verifica que la protección funciona: `https://flava.cl/config/database.php`
debe devolver **403**, nunca el contenido del archivo.

---

## 5. Permisos de escritura

Estas carpetas deben ser escribibles por el servidor web (`755` o `775` según el
hosting):

```text
/storage/logs
/storage/cache
/storage/backups
/storage/framework
/public/uploads
```

El panel de Súper Administrador (`/super-admin`) muestra el estado de cada una.

---

## 6. Primer ingreso

Entra a `https://flava.cl/login`:

- **Email:** `admin@flava.cl`
- **Contraseña:** `Flava2026!`

El sistema **obliga a cambiar la contraseña** antes de dejarte continuar.
Hazlo de inmediato y borra estas credenciales de cualquier nota.

Si prefieres crear tu propio usuario desde consola:

```bash
php bin/flava user:create
```

---

## 7. Configuración inicial del negocio

En este orden, desde el panel de administración:

1. **Configuración → Negocio**: nombre, dirección, teléfono, WhatsApp, Instagram.
2. **Servicios**: revisa los seis servicios de ejemplo, ajusta precios y duraciones.
3. **Barberos**: crea cada barbero y marca los servicios que realiza.
   Puedes crear su cuenta de acceso desde el mismo formulario.
4. **Horario de cada barbero**: define su jornada semanal (admite varios bloques
   por día, por ejemplo 09:00–13:00 y 14:00–19:00).
5. **Bloqueos**: almuerzos, días libres y vacaciones.
6. **Configuración → Reservas**: intervalo entre horas, anticipación mínima,
   política de cancelación.

Sin al menos un barbero con servicios y horario, el booking no mostrará horas
disponibles: es el comportamiento correcto, no un error.

---

## 8. Verificación final

```bash
php bin/flava install:check
```

O revisa manualmente:

- [ ] `https://flava.cl` carga la portada
- [ ] `https://flava.cl/reservar` muestra los servicios
- [ ] Se puede completar una reserva de prueba de principio a fin
- [ ] `https://flava.cl/config/database.php` devuelve 403
- [ ] `https://flava.cl/storage/logs/` devuelve 403 o 404
- [ ] El login exige cambiar la contraseña por defecto
- [ ] `/super-admin` muestra todas las carpetas escribibles

---

## 9. Cron (opcional, Etapa 2)

Cuando actives email o WhatsApp, agrega en cPanel → Cron Jobs:

```bash
*/5 * * * * /usr/bin/php /home/usuario/flava/bin/flava notifications:process
```

La cola de notificaciones ya se está llenando desde ahora: al conectar el
proveedor, los recordatorios empiezan a salir sin perder historial.

---

## 10. Problemas frecuentes

| Síntoma | Causa habitual |
|---|---|
| "No se pudo conectar a la base de datos" | Credenciales en `.env` o `config/database.php` |
| Error 500 en blanco | Revisa `/storage/logs/`; activa `APP_DEBUG=true` sólo temporalmente |
| Las URLs limpias dan 404 | Falta `mod_rewrite` o `AllowOverride All` en Apache |
| No aparecen horarios al reservar | El barbero no tiene horario, o no tiene el servicio asignado |
| "APP_KEY no está configurada" | Ejecuta `php bin/flava key:generate` |
| Los assets no cargan | `APP_URL` no coincide con el dominio real |
