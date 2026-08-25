<?php
/**
 * Ruta: /app/Services/UploadService.php
 *
 * Subida de imágenes para servicios, barberos y marca.
 *
 * Reglas de seguridad:
 *   · El tipo se decide leyendo la imagen (getimagesize), NUNCA por la
 *     extensión ni por el mime que envía el navegador: ambos son falsificables.
 *   · El nombre final lo genera el servidor; el nombre original se descarta.
 *   · La imagen se REPROCESA con GD cuando está disponible, así el archivo
 *     guardado se reconstruye desde cero y no puede llevar código incrustado.
 *   · /public/uploads tiene el motor PHP apagado por .htaccess.
 */

namespace App\Services;

final class UploadService
{
    /** Formatos aceptados: constante de imagen => extensión. */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(
        private readonly int $maxBytes = 4 * 1024 * 1024,
        private readonly int $maxWidth = 1200,
        private readonly int $maxHeight = 1200,
    ) {
    }

    /**
     * Procesa un archivo de $_FILES y devuelve la ruta relativa a /public/uploads.
     *
     * @param array  $file    entrada de $_FILES
     * @param string $folder  subcarpeta: 'services' | 'barbers' | 'branding'
     * @param string $prefix  prefijo legible del nombre (se convierte a slug)
     *
     * @throws \RuntimeException con un mensaje mostrable al usuario
     */
    public function image(array $file, string $folder, string $prefix = 'img'): string
    {
        $this->guardFolder($folder);
        $this->guardUploadErrors($file);

        $tmp = $file['tmp_name'] ?? '';

        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('El archivo no llegó correctamente. Inténtalo de nuevo.');
        }

        if (($file['size'] ?? 0) > $this->maxBytes) {
            throw new \RuntimeException(sprintf(
                'La imagen pesa demasiado (máximo %s MB).',
                round($this->maxBytes / 1024 / 1024, 1)
            ));
        }

        // El tipo real lo dicta el contenido del archivo.
        $info = @getimagesize($tmp);

        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new \RuntimeException('El archivo no es una imagen válida. Usa JPG, PNG o WebP.');
        }

        $extension = self::ALLOWED[$info[2]];
        $directory = PUBLIC_PATH . '/uploads/' . $folder;

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de imágenes. Revisa los permisos de /public/uploads.');
        }

        $name        = slugify($prefix) . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
        $destination = $directory . '/' . $name;

        if (!$this->reprocess($tmp, $destination, $info[2])) {
            // Sin GD: se mueve el archivo tal cual, ya validado como imagen real.
            if (!@move_uploaded_file($tmp, $destination)) {
                throw new \RuntimeException('No se pudo guardar la imagen.');
            }
        }

        @chmod($destination, 0644);

        ActivityLogger::log('upload.image', $folder, null, 'Imagen subida: ' . $name);

        return $folder . '/' . $name;
    }

    /**
     * Reconstruye la imagen con GD reduciéndola al tamaño máximo.
     * Devuelve false si GD no está disponible (el hosting decide).
     */
    private function reprocess(string $source, string $destination, int $type): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default        => false,
        };

        if ($image === false) {
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $ratio  = min($this->maxWidth / $width, $this->maxHeight / $height, 1);

        $newWidth  = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Conserva la transparencia de PNG y WebP.
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($canvas, $destination, 84),
            IMAGETYPE_PNG  => imagepng($canvas, $destination, 6),
            IMAGETYPE_WEBP => imagewebp($canvas, $destination, 84),
            default        => false,
        };

        // Desde PHP 8.0 los recursos GD son objetos: el recolector los libera
        // solo, e imagedestroy() quedó obsoleta en 8.5.
        unset($image, $canvas);

        return $saved;
    }

    /**
     * Elimina una imagen subida anteriormente.
     * Sólo borra dentro de /public/uploads: ninguna ruta puede escapar de ahí.
     */
    public function delete(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '' || str_starts_with($relativePath, 'http')) {
            return false;
        }

        $base = realpath(PUBLIC_PATH . '/uploads');
        $file = realpath(PUBLIC_PATH . '/uploads/' . ltrim($relativePath, '/'));

        if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return is_file($file) && @unlink($file);
    }

    /**
     * Reemplaza una imagen: sube la nueva y borra la anterior sólo si todo salió
     * bien. Si no viene archivo nuevo, conserva la actual.
     */
    public function replace(?array $file, ?string $current, string $folder, string $prefix): ?string
    {
        if ($file === null) {
            return $current;
        }

        $new = $this->image($file, $folder, $prefix);
        $this->delete($current);

        return $new;
    }

    private function guardFolder(string $folder): void
    {
        if (!in_array($folder, ['services', 'barbers', 'products', 'branding'], true)) {
            throw new \InvalidArgumentException('Carpeta de subida no permitida.');
        }
    }

    private function guardUploadErrors(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new \RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido por el servidor.',
            UPLOAD_ERR_PARTIAL                        => 'La subida se interrumpió. Inténtalo de nuevo.',
            UPLOAD_ERR_NO_FILE                        => 'No se seleccionó ninguna imagen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo temporal.',
            default                                   => 'No se pudo subir la imagen.',
        });
    }

    /** ¿El servidor puede redimensionar? Se informa en el panel del súper admin. */
    public static function canResize(): bool
    {
        return extension_loaded('gd');
    }
}
