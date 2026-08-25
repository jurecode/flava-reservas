<?php
/**
 * Ruta: /app/Services/EmailService.php
 *
 * Etapa 1: renderiza el mensaje y lo registra en /storage/logs (driver "log").
 * Etapa 2: basta implementar el driver "smtp"/"api" sin tocar el resto.
 */

namespace App\Services;

use App\Services\Notifications\ChannelInterface;
use Core\View;

final class EmailService implements ChannelInterface
{
    public function isEnabled(): bool
    {
        return (bool) setting('email_enabled', false) && $this->driver() !== 'off';
    }

    public function driver(): string
    {
        return (string) env('MAIL_DRIVER', 'log');
    }

    public function send(string $recipient, string $type, array $payload, string $subject = ''): bool
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Destinatario de email inválido');
        }

        $html = $this->render($type, $payload);

        return match ($this->driver()) {
            'log'   => $this->logOnly($recipient, $subject, $html),
            'mail'  => $this->phpMail($recipient, $subject, $html),
            default => throw new \RuntimeException('Driver de email no implementado: ' . $this->driver()),
        };
    }

    /** Plantilla HTML con la identidad de Flava Studio. */
    public function render(string $type, array $payload): string
    {
        $view = 'emails.' . str_replace('.', '_', $type);

        if (!View::exists($view)) {
            $view = 'emails.generic';
        }

        return View::partial($view, ['data' => $payload, 'type' => $type]);
    }

    private function logOnly(string $recipient, string $subject, string $html): bool
    {
        logger()->info('[EMAIL simulado] ' . $subject, [
            'to'     => $recipient,
            'length' => strlen($html),
        ]);

        return true;
    }

    private function phpMail(string $recipient, string $subject, string $html): bool
    {
        $from    = (string) env('MAIL_FROM_ADDRESS', 'hola@flava.cl');
        $name    = (string) env('MAIL_FROM_NAME', config('app.name'));
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . mb_encode_mimeheader($name) . ' <' . $from . '>',
            'Reply-To: ' . $from,
        ]);

        if (!mail($recipient, mb_encode_mimeheader($subject), $html, $headers)) {
            throw new \RuntimeException('El servidor no pudo enviar el email');
        }

        return true;
    }
}
