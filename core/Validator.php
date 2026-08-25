<?php
/**
 * Ruta: /core/Validator.php
 * Validación en backend (regla del proyecto: el frontend ayuda, el backend manda).
 *
 * Uso:
 *   $data = (new Validator($input, [
 *       'email' => 'required|email|max:150',
 *       'rut'   => 'required|rut',
 *   ]))->validated();
 */

namespace Core;

use App\Support\Rut;

final class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = []
    ) {
        $this->run();
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        $first = reset($this->errors);

        return is_array($first) ? ($first[0] ?? null) : null;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules    = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value    = $this->value($field);
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if ($nullable && $this->isEmpty($value) && !$required) {
                $this->validated[$field] = ($value === '' ? null : $value);
                continue;
            }

            $failed = false;

            foreach ($rules as $rule) {
                if ($rule === '' || $rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->applies($name, $value, $rules)) {
                    continue;
                }

                if (!$this->check($name, $field, $value, $parameter)) {
                    $this->addError($field, $name, $parameter);
                    $failed = true;
                    break;
                }
            }

            if (!$failed) {
                $this->validated[$field] = $this->normalize($value, $rules);
            }
        }
    }

    /** Reglas que se saltan cuando el valor está vacío y no es obligatorio. */
    private function applies(string $rule, mixed $value, array $rules): bool
    {
        if (in_array($rule, ['required', 'required_with', 'required_if', 'confirmed'], true)) {
            return true;
        }

        return !$this->isEmpty($value);
    }

    private function check(string $rule, string $field, mixed $value, ?string $parameter): bool
    {
        return match ($rule) {
            'required'      => !$this->isEmpty($value),
            'required_with' => $this->isEmpty($this->value((string) $parameter)) || !$this->isEmpty($value),
            'required_if'   => $this->requiredIf($value, (string) $parameter),
            'string'        => is_string($value),
            'array'         => is_array($value),
            'boolean'       => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'true', 'false'], true),
            'numeric'       => is_numeric($value),
            'integer'       => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'min'           => $this->size($value) >= (float) $parameter,
            'max'           => $this->size($value) <= (float) $parameter,
            'between'       => $this->between($value, (string) $parameter),
            'email'         => (bool) filter_var((string) $value, FILTER_VALIDATE_EMAIL),
            'url'           => (bool) filter_var((string) $value, FILTER_VALIDATE_URL),
            'date'          => $this->isDate((string) $value),
            'date_format'   => $this->matchesFormat((string) $value, (string) $parameter),
            'time'          => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value),
            'after_or_equal' => $this->afterOrEqual((string) $value, (string) $parameter),
            'in'            => in_array((string) $value, explode(',', (string) $parameter), true),
            'not_in'        => !in_array((string) $value, explode(',', (string) $parameter), true),
            'regex'         => $this->matchesPattern((string) $parameter, (string) $value),
            'alpha_dash'    => (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', (string) $value),
            'slug'          => (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value),
            'rut'           => Rut::isValid((string) $value),
            'phone'         => (bool) preg_match('/^\+?[0-9\s\-()]{8,20}$/', (string) $value),
            'confirmed'     => (string) $value === (string) $this->value($field . '_confirmation'),
            'same'          => (string) $value === (string) $this->value((string) $parameter),
            'unique'        => $this->isUnique((string) $parameter, $value),
            'exists'        => $this->recordExists((string) $parameter, $value),
            default         => true,
        };
    }

    /**
     * Aplica una regla `regex` sin que un patrón mal formado tumbe la petición.
     *
     * Un patrón puede llegar partido si la regla se escribió como texto y el
     * propio patrón contenía el separador «|». En ese caso la validación falla
     * —el valor no se da por bueno— y queda registrado para corregir la regla,
     * en vez de provocar un error 500.
     */
    private function matchesPattern(string $pattern, string $value): bool
    {
        $resultado = @preg_match($pattern, $value);

        if ($resultado === false) {
            logger()->error('Patrón de validación inválido', [
                'pattern' => $pattern,
                'error'   => preg_last_error_msg(),
                'ayuda'   => 'Si el patrón contiene «|», declara las reglas como array en vez de texto.',
            ]);

            return false;
        }

        return $resultado === 1;
    }

    private function requiredIf(mixed $value, string $parameter): bool
    {
        [$other, $expected] = array_pad(explode(',', $parameter, 2), 2, null);

        if ((string) $this->value((string) $other) !== (string) $expected) {
            return true;
        }

        return !$this->isEmpty($value);
    }

    private function between(mixed $value, string $parameter): bool
    {
        [$min, $max] = array_pad(explode(',', $parameter, 2), 2, null);
        $size        = $this->size($value);

        return $size >= (float) $min && $size <= (float) $max;
    }

    private function isDate(string $value): bool
    {
        return $this->matchesFormat($value, 'Y-m-d') || strtotime($value) !== false;
    }

    private function matchesFormat(string $value, string $format): bool
    {
        $date = \DateTime::createFromFormat($format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function afterOrEqual(string $value, string $parameter): bool
    {
        $other = $this->value($parameter);
        $limit = $other !== null ? strtotime((string) $other) : strtotime($parameter);

        return $limit !== false && strtotime($value) >= $limit;
    }

    /** unique:tabla,columna[,idAExcluir[,columnaPk]] */
    private function isUnique(string $parameter, mixed $value): bool
    {
        [$table, $column, $ignore, $pk] = array_pad(explode(',', $parameter), 4, null);
        $pk ??= 'id';

        $this->guardIdentifier((string) $table);
        $this->guardIdentifier((string) $column);
        $this->guardIdentifier($pk);

        $sql      = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
        $bindings = ['value' => $value];

        if ($ignore !== null && $ignore !== '' && $ignore !== 'null') {
            $sql              .= " AND `{$pk}` != :ignore";
            $bindings['ignore'] = $ignore;
        }

        return (int) Database::instance()->scalar($sql, $bindings) === 0;
    }

    /** exists:tabla,columna */
    private function recordExists(string $parameter, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $parameter), 2, 'id');

        $this->guardIdentifier((string) $table);
        $this->guardIdentifier((string) $column);

        return (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value",
            ['value' => $value]
        ) > 0;
    }

    private function guardIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Identificador inválido en regla de validación');
        }
    }

    private function size(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return (float) count($value);
        }

        return (float) mb_strlen((string) $value);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function value(string $field): mixed
    {
        $value = array_get($this->data, $field);

        return is_string($value) ? trim($value) : $value;
    }

    private function normalize(mixed $value, array $rules): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (in_array('integer', $rules, true) && $value !== null && $value !== '') {
            return (int) $value;
        }

        if (in_array('boolean', $rules, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return $value;
    }

    private function addError(string $field, string $rule, ?string $parameter): void
    {
        $key   = $field . '.' . $rule;
        $label = $this->label($field);

        $message = $this->messages[$key]
            ?? $this->messages[$field]
            ?? match ($rule) {
                'required', 'required_with', 'required_if' => "El campo {$label} es obligatorio.",
                'email'          => "Ingresa un email válido.",
                'rut'            => "El RUT ingresado no es válido.",
                'phone'          => "Ingresa un teléfono válido.",
                'min'            => "El campo {$label} debe tener al menos {$parameter} caracteres.",
                'max'            => "El campo {$label} no puede superar {$parameter} caracteres.",
                'numeric', 'integer' => "El campo {$label} debe ser numérico.",
                'date', 'date_format' => "La fecha ingresada no es válida.",
                'time'           => "La hora ingresada no es válida.",
                'in'             => "El valor seleccionado en {$label} no es válido.",
                'unique'         => "Ya existe un registro con ese {$label}.",
                'exists'         => "El {$label} seleccionado no existe.",
                'confirmed'      => "La confirmación no coincide.",
                'after_or_equal' => "La fecha de {$label} no puede ser anterior a la permitida.",
                default          => "El campo {$label} no es válido.",
            };

        $this->errors[$field][] = $message;
    }

    private function label(string $field): string
    {
        $labels = [
            'first_name' => 'nombre', 'last_name' => 'apellido', 'rut' => 'RUT',
            'email' => 'email', 'phone' => 'teléfono', 'password' => 'contraseña',
            'service_id' => 'servicio', 'barber_id' => 'barbero', 'booking_date' => 'fecha',
            'start_time' => 'hora', 'payment_method' => 'método de pago', 'name' => 'nombre',
            'price' => 'precio', 'duration_minutes' => 'duración',
        ];

        return $labels[$field] ?? str_replace('_', ' ', $field);
    }
}
