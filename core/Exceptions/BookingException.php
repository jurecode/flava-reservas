<?php
/**
 * Ruta: /core/Exceptions/BookingException.php
 * Errores de negocio del motor de reservas (mensajes seguros para el cliente).
 */

namespace Core\Exceptions;

class BookingException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message, 409);
    }

    public static function slotTaken(): self
    {
        return new self('Este horario acaba de ser reservado. Selecciona otro disponible.');
    }

    public static function outsideSchedule(): self
    {
        return new self('El barbero no atiende en ese horario. Selecciona otro disponible.');
    }

    public static function tooSoon(int $minutes): self
    {
        return new self("Las reservas online requieren al menos {$minutes} minutos de anticipación.");
    }
}
