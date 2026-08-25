# Despliegue y actualizaciones · Flava Studio

El flujo oficial del proyecto es **uno solo**:

```text
DESARROLLO LOCAL
      ↓ git push
   GITHUB  (repositorio central y fuente oficial de versiones)
      ↓ panel SUPER_ADMIN
   PRODUCCIÓN  ·  flava.cl
```

**Producción no es un entorno de desarrollo.** No se editan archivos a mano en el
servidor: eso rompe la trazabilidad y el sistema te avisará si detecta cambios
locales antes de actualizar.

---

## 1. Preparar el repositorio (una sola vez)

En tu equipo, dentro del proyecto:

```bash
git init
git add .
git commit -m "chore: versión inicial de Flava Studio"
git branch -M main
git remote add origin https://github.com/flavastudio/flava-web.git
git push -u origin main
```

El `.gitignore` ya excluye lo que **nunca** debe versionarse:

```text
.env · /config/secrets.php · /config/database.php
/storage/logs · /storage/cache · /storage/backups
/public/uploads
```

---

## 2. Preparar el servidor (una sola vez)

Si el hosting permite Git (SSH o cPanel Git™ Version Control):

```bash
cd /home/usuario/flava
git init
git remote add origin https://github.com/flavastudio/flava-web.git
git fetch origin main
git checkout -b main --track origin/main
```

Después crea en el servidor los archivos que Git no trae: `.env` (o
`config/secrets.php`) con las credenciales de producción.

Si el hosting **no** permite Git, el panel lo detecta y lo dice explícitamente:
podrás consultar el repositorio y ejecutar migraciones desde la interfaz, pero
la actualización de archivos deberá hacerse por FTP o cPanel.

---

## 3. Token de GitHub

Crea un **fine-grained personal access token** con permisos **mínimos**:

| Permiso | Nivel |
|---|---|
| Contents | Read |
| Metadata | Read |

El servidor sólo necesita **leer**. Los permisos de escritura se quedan en tu
entorno local.

Guárdalo de una de estas dos formas:

1. **Variable de entorno** (preferida): `GITHUB_TOKEN=github_pat_...` en `.env`.
2. **Desde el panel**: `/super-admin/github` → Personal Access Token.

Cómo se protege el token:

- Se cifra con **libsodium** (o AES-256-GCM si no está disponible) antes de
  guardarse. Nunca queda en texto plano en MySQL.
- La clave de cifrado (`APP_KEY`) vive **fuera del webroot**.
- El panel sólo muestra una pista: `github_pat_****F82K`.
- Nunca viaja al navegador, ni aparece en JSON, ni en los logs: el logger
  enmascara automáticamente tokens y cabeceras `Authorization`.

---

## 4. Ciclo de trabajo

### En local

```bash
# ... haces cambios y los pruebas ...
git add .
git commit -m "feat: recordatorio 2 horas antes"
git push origin main
```

### En el panel de Súper Administrador

1. `/super-admin/github` → **Buscar actualizaciones**
   Compara el commit instalado con el último de GitHub y muestra la lista de
   cambios. Avisa si la actualización trae migraciones de base de datos.
2. `/super-admin/despliegues` → **Crear respaldo y actualizar**
   Requiere confirmar tu contraseña.

El despliegue ejecuta, en orden:

```text
 1. verificar permisos          8. aplicar archivos (merge --ff-only)
 2. comprobar repositorio       9. ejecutar migraciones pendientes
 3. verificar rama             10. limpiar cache
 4. comprobar cambios locales  11. verificar base de datos y archivos
 5. crear respaldo             12. desactivar mantención
 6. activar mantención         13. registrar el resultado
 7. descargar actualización
```

Cada paso queda visible en pantalla y registrado en `/storage/logs/deploy.log`
y en `activity_logs`.

### Lo que el despliegue NUNCA toca

```text
.env                      config/secrets.php        config/database.php
/storage/**               /public/uploads/**        la base de datos
```

La base de datos sólo cambia mediante migraciones. **Nunca** se reimporta
`flava.sql` sobre datos reales.

---

## 5. Migraciones

Cada cambio de estructura vive en un archivo propio que se ejecuta **una sola vez**:

```bash
php bin/flava make:migration "agregar columna de calificacion"
# → /database/migrations/20260824_002_agregar-columna-de-calificacion.sql
```

Escríbelas siempre pensando en producción:

- Idempotentes cuando el motor lo permita (`IF NOT EXISTS`, `IF EXISTS`).
- Nunca eliminan datos sin que sea una decisión explícita y respaldada.
- Una migración = un cambio coherente.

Se ejecutan desde `/super-admin/migraciones` o con `php bin/flava migrate`.
Antes de correr cualquier migración se crea un respaldo automático.

> **Por qué no van en transacción:** MySQL y MariaDB hacen *commit implícito* en
> cada sentencia DDL (`ALTER`, `CREATE`, `DROP`). Envolverlas en `BEGIN/COMMIT`
> no las vuelve atómicas, sólo crea una falsa sensación de seguridad. La red real
> es el respaldo previo. Si una sentencia falla, el proceso se detiene, la
> migración **no** se marca como ejecutada y el panel dice exactamente en qué
> sentencia quedó y si hubo aplicación parcial.

---

## 6. Respaldos

- Automáticos antes de cada despliegue y de cada migración.
- Manuales desde `/super-admin/respaldos` o `php bin/flava backup`.
- Se guardan en `/storage/backups`, **fuera del directorio público**, con su
  propio `.htaccess` de denegación.
- Usan `mysqldump` si está disponible; si no, un volcado equivalente hecho desde
  PHP que funciona en cualquier hosting compartido.

Para restaurar: descarga la carpeta, importa `database.sql` en una base **vacía**
y apunta la configuración allí. Es un proceso manual a propósito: reemplazar
datos de producción debe ser una decisión consciente.

---

## 7. Rollback

`/super-admin/despliegues` → **Restaurar versión**. Pide contraseña y escribir
`RESTAURAR` como confirmación adicional.

⚠️ El rollback devuelve **los archivos** al commit anterior. **Los cambios de base
de datos no se revierten automáticamente**: si el despliegue incluyó migraciones,
revísalas antes. Los datos creados después del despliegue se conservan.

---

## 8. Versionado

Versionado semántico `MAJOR.MINOR.PATCH` en `/config/version.php`.

Al hacer un cambio importante, indica siempre:

```text
Archivos modificados
Migraciones necesarias
Nueva versión sugerida
Mensaje de commit sugerido
```

Ejemplo:

```text
Versión: 1.3.1
Commit:  fix: prevent overlapping barber bookings
```

Y registra el cambio en `CHANGELOG.md`: el panel lo lee y lo muestra.

---

## 9. Ramas

La versión 1.0 usa una sola rama: `main` = producción.

Cuando el equipo crezca, la arquitectura ya soporta:

```text
desarrollo local → develop → staging → main → producción
```

La rama de producción se configura desde el panel, sin tocar código.

---

## 10. Etapas futuras

GitHub Actions, webhooks y CI/CD **no** están implementados a propósito: el
despliegue manual y controlado es preferible a una automatización insegura.
La arquitectura está lista para incorporarlos cuando se soliciten.
