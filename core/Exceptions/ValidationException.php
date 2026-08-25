<?php
/**
 * Ruta: /core/Exceptions/ValidationException.php
 */

namespace Core\Exceptions;

class ValidationException extends \RuntimeException
{
    public function __construct(
        public readonly array $errors = [],
        string $message = 'Revisa los datos ingresados'
    ) {
        parent::__construct($message, 422);
    }

    public function first(): ?string
    {
        $first = reset($this->errors);

        return is_array($first) ? ($first[0] ?? null) : ($first ?: null);
    }
}
