<?php
/**
 * Ruta: /core/Controller.php
 * Controlador base: acceso a request, vistas, JSON, redirecciones y validación.
 */

namespace Core;

use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    // ---- Respuestas ----

    protected function view(string $view, array $data = [], ?string $layout = null): Response
    {
        return Response::make(View::render($view, $data, $layout));
    }

    protected function json(array|object $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function success(string $message = '', array|object $data = [], int $status = 200): Response
    {
        return Response::success($message, $data, $status);
    }

    protected function fail(string $message, array $errors = [], int $status = 422): Response
    {
        return Response::error($message, $errors, $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        return Response::back($fallback);
    }

    /** Redirección con mensaje flash de éxito. */
    protected function redirectWith(string $to, string $message, string $type = 'success'): Response
    {
        Session::flash($type, $message);

        return $this->redirect($to);
    }

    /** Vuelve al formulario conservando input y errores. */
    protected function backWithErrors(array $errors, string $message = 'Revisa los datos ingresados'): Response
    {
        Session::flashInput($this->request->all(), $errors);
        Session::flash('error', $message);

        return $this->back();
    }

    // ---- Validación ----

    /**
     * Valida la entrada. Lanza ValidationException que el handler convierte en
     * JSON (AJAX) o redirección con errores (formulario).
     *
     * @param array<string,string> $rules
     * @return array<string,mixed> datos validados
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $validator = new Validator($this->request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    // ---- Seguridad ----

    protected function authorize(bool $condition, string $message = 'No tienes permisos para esta acción'): void
    {
        if (!$condition) {
            throw HttpException::forbidden($message);
        }
    }

    protected function user(): ?array
    {
        return Auth::user();
    }

    protected function userId(): ?int
    {
        return Auth::id();
    }

    /** Verifica el token CSRF manualmente (además del middleware). */
    protected function verifyCsrf(): void
    {
        if (!Session::verifyCsrf($this->request->input('_token') ?? $this->request->header('X-CSRF-Token'))) {
            throw HttpException::csrf();
        }
    }
}
