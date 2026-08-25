<?php
/**
 * Ruta: /app/Support/Icon.php
 *
 * Biblioteca de íconos SVG en línea. Reemplaza los emoji: se ven igual en todos
 * los sistemas, heredan el color del texto (`currentColor`) y escalan sin perder
 * definición.
 *
 * Uso en vistas:  <?= icon('calendar') ?>   ·   <?= icon('scissors', 20) ?>
 *
 * Todos los trazos usan un grid de 24×24 y stroke-width 1.75 para mantener un
 * peso visual homogéneo.
 */

namespace App\Support;

final class Icon
{
    /** @var array<string,string> nombre => contenido del <svg> */
    private const PATHS = [
        // ---- Navegación ----
        'menu'          => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'         => '<path d="M18 6L6 18M6 6l12 12"/>',
        'chevron-left'  => '<path d="M15 18l-6-6 6-6"/>',
        'chevron-right' => '<path d="M9 18l6-6-6-6"/>',
        'chevron-down'  => '<path d="M6 9l6 6 6-6"/>',
        'chevron-up'    => '<path d="M6 15l6-6 6 6"/>',
        'arrow-left'    => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right'   => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'external'      => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/>',

        // ---- Agenda y reservas ----
        'calendar'      => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'calendar-check' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15l2 2 4-4"/>',
        'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'scissors'      => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88M14.47 14.48L20 20M8.12 8.12L12 12"/>',
        'grid'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list'          => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'walk'          => '<circle cx="13" cy="4" r="2"/><path d="M11 21l1.5-6L9 12l1-5 3 2 3 1M9 21l1-4"/>',

        // ---- Personas ----
        'user'          => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'         => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
        'user-check'    => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M17 11l2 2 4-4"/>',
        'user-plus'     => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/>',

        // ---- Acciones ----
        'plus'          => '<path d="M12 5v14M5 12h14"/>',
        'minus'         => '<path d="M5 12h14"/>',
        'check'         => '<path d="M20 6L9 17l-5-5"/>',
        'check-circle'  => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'x-circle'      => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
        'alert'         => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'edit'          => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'         => '<path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>',
        'search'        => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
        'filter'        => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'refresh'       => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/>',
        'download'      => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'upload'        => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'copy'          => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/>',
        'ban'           => '<circle cx="12" cy="12" r="9"/><path d="M5.64 5.64l12.72 12.72"/>',
        'send'          => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>',
        'play'          => '<path d="M5 3l14 9-14 9V3z"/>',
        'flag'          => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',

        // ---- Contacto ----
        'phone'         => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>',
        'mail'          => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>',
        'message'       => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>',
        'whatsapp'      => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5"/>',
        'map-pin'       => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',
        'instagram'     => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'bell'          => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',

        // ---- Dinero ----
        'credit-card'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'cash'          => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'bank'          => '<path d="M3 21h18M4 10v8M9 10v8M15 10v8M20 10v8M2 10l10-6 10 6"/>',
        'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 010 18 15 15 0 010-18z"/>',
        'receipt'       => '<path d="M4 2v20l2.5-1.5L9 22l2.5-1.5L14 22l2.5-1.5L19 22V2l-2.5 1.5L14 2l-2.5 1.5L9 2 6.5 3.5 4 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',

        // ---- Producto / negocio ----
        'bottle'        => '<path d="M10 2h4v3l1.5 2.5A4 4 0 0116 10v9a3 3 0 01-3 3h-2a3 3 0 01-3-3v-9a4 4 0 01.5-2.5L10 5V2z"/><path d="M8 12h8"/>',
        'store'         => '<path d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18"/><path d="M9 21v-6h6v6"/>',
        'tag'           => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><path d="M7 7h.01"/>',
        'star'          => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z"/>',
        'heart'         => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
        'image'         => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',

        // ---- Sistema ----
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9c.14.36.4.66.73.87.3.19.65.29 1 .28H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
        'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'lock'          => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'key'           => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/>',
        'log-out'       => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        'server'        => '<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><path d="M6 6h.01M6 18h.01"/>',
        'database'      => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'github'        => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 00-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0020 4.77 5.07 5.07 0 0019.91 1S18.73.65 16 2.48a13.38 13.38 0 00-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 005 4.77a5.44 5.44 0 00-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 009 18.13V22"/>',
        'rocket'        => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 00-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 012-3.95A12.88 12.88 0 0122 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 01-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'terminal'      => '<path d="M4 17l6-6-6-6M12 19h8"/>',
        'file-text'     => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'save'          => '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'activity'      => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'trending-up'   => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'bar-chart'     => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
        'pie-chart'     => '<path d="M21.21 15.89A10 10 0 118 2.83"/><path d="M22 12A10 10 0 0012 2v10z"/>',
        'home'          => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/>',
        'zap'           => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
        'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'palm'          => '<path d="M12 22V11"/><path d="M12 11c0-3-3-5-6-4 1-3 4-4 6-3 2-1 5 0 6 3-3-1-6 1-6 4z"/><path d="M12 11c2-2 5-2 7 0M12 11c-2-2-5-2-7 0"/>',
        'pin'           => '<path d="M12 17v5M9 10.76V7a3 3 0 016 0v3.76a2 2 0 00.55 1.38l1.9 2A1 1 0 0116.72 15H7.28a1 1 0 01-.73-1.68l1.9-2A2 2 0 009 10.76z"/>',
        'note'          => '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'sliders'       => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'bee'           => '<ellipse cx="12" cy="14" rx="4.5" ry="6"/><path d="M7.5 12h9M7.5 16h9"/><path d="M12 8V6M9.5 5.5L8 4M14.5 5.5L16 4"/><path d="M7.5 10C5 8 3 9 3 9s1 3 4 3M16.5 10c2.5-2 4.5-1 4.5-1s-1 3-4 3"/>',
    ];

    /** Íconos que se dibujan rellenos en vez de con trazo. */
    private const FILLED = ['star', 'heart', 'play', 'flag', 'zap'];

    /**
     * Devuelve el SVG en línea.
     *
     * @param string      $name   nombre del ícono
     * @param int         $size   tamaño en píxeles
     * @param string|null $class  clase CSS opcional
     */
    public static function render(string $name, int $size = 18, ?string $class = null, bool $filled = false): string
    {
        $body = self::PATHS[$name] ?? self::PATHS['info'];
        $fill = $filled || in_array($name, self::FILLED, true);

        return sprintf(
            '<svg class="ico%s" width="%d" height="%d" viewBox="0 0 24 24" fill="%s" stroke="%s" '
            . 'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $class !== null ? ' ' . htmlspecialchars($class, ENT_QUOTES) : '',
            $size,
            $size,
            $fill ? 'currentColor' : 'none',
            $fill ? 'none' : 'currentColor',
            $body
        );
    }

    public static function exists(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /** @return array<int,string> */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    // -----------------------------------------------------------------
    //  Mapas de dominio: un único lugar donde se decide qué ícono usa qué
    // -----------------------------------------------------------------

    public static function forPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash'                    => 'cash',
            'debit', 'credit'         => 'credit-card',
            'transfer'                => 'bank',
            'webpay', 'mercadopago'   => 'globe',
            default                   => 'receipt',
        };
    }

    public static function forBookingStatus(string $status): string
    {
        return match ($status) {
            'pending'     => 'clock',
            'confirmed'   => 'calendar-check',
            'checked_in'  => 'user-check',
            'in_progress' => 'scissors',
            'completed'   => 'check-circle',
            'cancelled'   => 'x-circle',
            'no_show'     => 'ban',
            default       => 'info',
        };
    }

    public static function forFlash(string $type): string
    {
        return match ($type) {
            'success' => 'check-circle',
            'error'   => 'alert',
            'warning' => 'zap',
            default   => 'info',
        };
    }
}
