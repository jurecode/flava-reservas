<?php
/**
 * Ruta: /core/Exceptions/HttpException.php
 */

namespace Core\Exceptions;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status = 500,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message ?: self::defaultMessage($status), $status, $previous);
    }

    public static function notFound(string $message = 'Página no encontrada'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'No tienes permisos para acceder aquí'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Debes iniciar sesión'): self
    {
        return new self(401, $message);
    }

    public static function csrf(string $message = 'La sesión expiró. Vuelve a intentarlo.'): self
    {
        return new self(419, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'Solicitud inválida',
            401     => 'Debes iniciar sesión',
            403     => 'Acceso denegado',
            404     => 'Página no encontrada',
            419     => 'La sesión expiró',
            422     => 'Datos inválidos',
            429     => 'Demasiadas solicitudes',
            503     => 'Sistema en mantención',
            default => 'Error interno del servidor',
        };
    }
}
