# Instalar Flava Studio en Hostinger

Guía paso a paso. Lo único que haces a mano es **crear la base de datos**; el
resto lo resuelve el asistente desde el navegador.

Tiempo estimado: **15 minutos**.

---

## Antes de empezar

Necesitas:

- Un plan de Hostinger con **PHP 8.1 o superior** (cualquiera sirve)
- Tu dominio ya apuntando al hosting
- Los archivos del proyecto (carpeta completa de Flava Studio)

No necesitas SSH, Composer ni Node.js.

---

## 1 · Sube los archivos

En **hPanel → Archivos → Administrador de archivos**.

### Si el dominio es el principal de la cuenta

1. Entra a `public_html`.
2. Sube el proyecto completo (puedes subir un `.zip` y descomprimirlo ahí).
3. Debe quedar así:

```text
public_html/
├── app/
├── config/
├── core/
├── database/
├── public/
├── routes/
├── storage/
├── .htaccess          ← importante: el de la raíz
└── ...
```

El `.htaccess` de la raíz redirige todo hacia `/public` sin que se vea en la
URL, y bloquea el acceso web a `app/`, `config/`, `core/` y `storage/`.

> ⚠ **Lo más importante de este paso:** confirma que el archivo `.htaccess`
> quedó en `public_html`. Al empezar con punto, los descompresores y clientes
> FTP suelen saltárselo, y sin él el sitio responde **403 Forbidden**.
>
> En el Administrador de archivos activa
> *Configuración → Mostrar archivos ocultos* para verlo.
>
> Si no está, sube `htaccess-raiz.txt` y renómbralo a `.htaccess`.
> Haz lo mismo dentro de `public/` con `htaccess-public.txt`.
> (O entra a `tudominio.cl/index.php` y deja que el sistema lo cree por ti.)

> **No necesitas cambiar el directorio raíz.** Algunos planes de Hostinger no
> ofrecen esa opción en el menú *Avanzado*, y el sistema está preparado para
> funcionar igual: el front controller vive en la raíz y los archivos estáticos
> se sirven desde `/public/assets` y `/public/uploads`, que son rutas reales.
>
> Si tu plan **sí** tiene *Cambiar el directorio raíz*, apuntarlo a
> `public_html/public` también funciona y es marginalmente más limpio. Las dos
> formas están soportadas.

---

## 2 · Crea la base de datos

En **hPanel → Bases de datos → Administración de bases de datos MySQL**:

1. **Crear nueva base de datos**
   - Nombre: `flava` (Hostinger le antepone tu prefijo → `u123456789_flava`)
   - Usuario: `admin` (queda `u123456789_admin`)
   - Contraseña: genera una y **guárdala**
2. Pulsa **Crear**

Hostinger asigna el usuario a la base automáticamente al crearlos juntos. Si los
creaste por separado, ve a la sección **Usuarios** y asigna el usuario a la base
con todos los privilegios.

**Anota estos tres datos**, los pide el asistente:

| Dato | Ejemplo |
|---|---|
| Base de datos | `u123456789_flava` |
| Usuario | `u123456789_admin` |
| Contraseña | la que generaste |

El **host** es `localhost`.

> No importes nada todavía. El asistente crea las tablas por ti.

---

## 3 · Revisa la versión de PHP

**hPanel → Avanzado → Configuración PHP**

- Pestaña **Versión de PHP**: elige **8.1 o superior**
- Pestaña **Extensiones PHP**: deben estar marcadas `pdo_mysql`, `mbstring`,
  `curl` y `gd`

Si falta alguna, márcala y guarda. El asistente te dirá exactamente cuál falta.

---

## 4 · Abre el asistente

Entra a tu dominio en el navegador:

```text
https://tudominio.cl
```

Te lleva solo a `/instalar`. Verás seis pasos:

| Paso | Qué hace |
|---|---|
| 1 · Requisitos | Comprueba PHP, extensiones y permisos de carpetas |
| 2 · Base de datos | Conecta con la base que creaste y guarda la configuración |
| 3 · Tablas | Crea las 24 tablas del sistema |
| 4 · Administrador | Crea tu cuenta con acceso completo |
| 5 · Tu barbería | Nombre, dirección, teléfono y dominio |
| 6 · Listo | Verifica todo y cierra el instalador |

Al terminar, el asistente se **cierra solo**: crea `/config/installed.php` y a
partir de ahí `/instalar` responde 404. Nadie puede relanzarlo sobre tus datos.

---

## Si algo se atasca

### «Falta escritura en /config» o «/storage»

En el Administrador de archivos, clic derecho sobre la carpeta →
**Permisos** → `755`. Marca *Aplicar a subcarpetas*.

### «No se pudo entrar con esos datos» (paso 2)

Casi siempre es una de estas tres:

1. **El usuario no está asignado a la base.** Es el error más común.
   hPanel → Bases de datos → sección **Usuarios** → asigna el usuario a la base.
2. **Falta el prefijo.** El nombre real incluye `u123456789_`, no es sólo `flava`.
3. **La contraseña tiene un espacio al copiarla.** Escríbela a mano.

### El `.htaccess` está pero el servidor parece ignorarlo

Antes esto pasaba por una regla que reescribía *todo* hacia `/public`: como
`REQUEST_URI` no cambia en las reescrituras internas, en algunos servidores la
regla se reaplicaba y el bucle hacía que LiteSpeed dejara de procesar el archivo.

**Ya no ocurre**: desde la versión 1.3.2 el front controller vive en la raíz y
sólo se reescriben las URLs limpias. Asegúrate de tener la versión nueva de
`.htaccess` e `index.php` (o sube `htaccess-raiz.txt` y renómbralo).

Si aun así falla, prueba `hPanel → Avanzado → Corregir la propiedad de los
archivos`: si la subida dejó los archivos con un propietario distinto al de tu
cuenta, el servidor no puede leer el `.htaccess`.

### El asistente no puede escribir `/config/database.php`

Te muestra el contenido exacto del archivo para que lo crees a mano desde el
Administrador de archivos, y un botón para continuar cuando esté listo.

### Error 403 «Forbidden · Access to this resource on the server is denied»

**Es el problema más común.** El `.htaccess` no se subió: al empezar con punto,
los descompresores y los clientes FTP suelen saltárselo. Sin él, el servidor
encuentra `public_html` sin ningún `index.php` y responde 403.

Tienes tres formas de resolverlo, de más simple a más manual:

**a) Deja que el sistema lo cree** — entra a `https://tudominio.cl/index.php`.
Verás una pantalla de diagnóstico de Flava Studio con un botón
*«Crear el archivo .htaccess»*. Un clic y listo.

**b) Sube la plantilla y renómbrala** — el proyecto incluye dos archivos
pensados justo para esto, porque no empiezan con punto y siempre se suben:

| Sube este archivo | Renómbralo a | En esta carpeta |
|---|---|---|
| `htaccess-raiz.txt` | `.htaccess` | `public_html/` |
| `htaccess-public.txt` | `.htaccess` | `public_html/public/` |

Para renombrar: clic derecho sobre el archivo → **Renombrar**.

**c) Revisa el Administrador de índices** —
`hPanel → Avanzado → Administrador de índices`. Si el índice está desactivado
para `public_html` y falta el `index.php`, el servidor responde 403. Con el
`index.php` de la raíz presente esto deja de importar.

> Si tu plan incluye *Cambiar el directorio raíz* (no todos lo hacen), apuntarlo
> a `public_html/public` es otra vía válida. Pero **no hace falta**: el sistema
> funciona igual con el dominio apuntando a la raíz del proyecto.

> Para ver los archivos que empiezan con punto en el Administrador de archivos:
> **Configuración → Mostrar archivos ocultos**.

### Veo la lista de carpetas en vez del sitio

Falta el `.htaccess` (mismo caso que el 403) o `Options -Indexes` no se está
aplicando. Sigue los pasos de arriba.

### Error 500 sin más información

Abre `storage/logs/flava-AAAA-MM-DD.log` desde el Administrador de archivos: ahí
queda el detalle. Los errores nunca se muestran en pantalla al visitante.

---

## Después de instalar

### Activa el SSL

**hPanel → Seguridad → SSL** → instala el certificado gratuito y activa
*Forzar HTTPS*. El sistema ya redirige a HTTPS por `.htaccess`.

### Configura el correo (opcional)

Las notificaciones se **encolan desde el primer día** en la tabla
`notifications`, aunque el envío esté apagado. Cuando quieras activarlo:

1. **hPanel → Correos electrónicos** → crea `hola@tudominio.cl`
2. En `/config/secrets.php` añade `'MAIL_DRIVER' => 'mail'`
3. En **Administración → Configuración → Notificaciones**, activa el email

### Programa los recordatorios

**hPanel → Avanzado → Trabajos cron**. Añade uno cada 5 minutos:

```bash
/usr/bin/php /home/u123456789/public_html/bin/flava notifications:process
```

Ajusta la ruta a la de tu cuenta (aparece arriba en el Administrador de archivos).

### Respaldos

El sistema crea los suyos antes de cada actualización, en `/storage/backups`
(fuera del webroot). Puedes lanzarlos a mano desde
**Súper Admin → Respaldos**.

Hostinger también hace copias automáticas en **hPanel → Archivos → Copias de
seguridad**. Tener ambas no está de más.

---

## Actualizar más adelante

El flujo oficial es `local → GitHub → producción`.

Tu hPanel incluye **Avanzado → GIT** y **Avanzado → Acceso SSH**: con eso puedes
conectar el repositorio directamente y desplegar sin subir archivos a mano. Es
la vía recomendada. Si prefieres no usarlos, el panel de Súper Admin te muestra
qué hay pendiente en GitHub y ejecuta las migraciones, y los archivos los subes
por el Administrador de archivos.

Para actualizar:

1. **Súper Admin → Respaldos** → crea uno
2. Sube los archivos nuevos por el Administrador de archivos
   (sin tocar `config/`, `storage/` ni `public/uploads/`)
3. **Súper Admin → Migraciones** → ejecuta las pendientes

Nunca reimportes `database/flava.sql`: ese archivo es sólo para instalaciones
nuevas y borraría tus datos.
