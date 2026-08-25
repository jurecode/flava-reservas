<?php
/**
 * Ruta: /app/Support/Cover.php
 *
 * Portada generada para servicios y barberos que todavía no tienen foto.
 *
 * En vez de mostrar un hueco gris o una imagen rota, se compone un SVG con la
 * identidad de la marca: degradado oscuro, textura de panal y el ícono del
 * servicio en grande. El resultado es determinista —el mismo servicio siempre
 * se ve igual— y no pesa nada porque va en línea.
 *
 * Cuando el servicio sí tiene imagen subida, esta clase no se usa.
 */

namespace App\Support;

final class Cover
{
    /**
     * Paletas oscuras curadas. No se usan tonos al azar: todas conviven con el
     * amarillo de la marca.
     *
     * @var array<int,array{0:string,1:string,2:string}> [desde, hacia, acento]
     */
    private const PALETTES = [
        ['#1A1A1A', '#0D0D0D', '#FFC400'],
        ['#241E12', '#12100B', '#E9A400'],
        ['#15181A', '#0B0D0E', '#FFC400'],
        ['#1E1A24', '#0E0C11', '#E9A400'],
        ['#101C18', '#080F0D', '#FFC400'],
    ];

    /** Ícono por defecto según lo que sugiere el nombre del servicio. */
    private const KEYWORDS = [
        'barba'    => 'scissors',
        'corte'    => 'scissors',
        'fade'     => 'scissors',
        'niño'     => 'user',
        'nino'     => 'user',
        'premium'  => 'star',
        'combo'    => 'star',
        'color'    => 'bottle',
        'tinte'    => 'bottle',
        'lavado'   => 'bottle',
        'ritual'   => 'star',
    ];

    private static int $counter = 0;

    /**
     * SVG de portada listo para insertar.
     *
     * @param string      $seed  texto estable (slug o nombre) que fija la paleta
     * @param string|null $icon  ícono a mostrar; si es null se deduce del nombre
     * @param string|null $label texto corto opcional sobre la textura
     */
    public static function render(string $seed, ?string $icon = null, ?string $label = null): string
    {
        $palette = self::paletteFor($seed);
        $icon  ??= self::iconFor($seed);
        $id      = 'cv' . (++self::$counter) . substr(md5($seed), 0, 5);

        [$from, $to, $accent] = $palette;

        // El ícono se dibuja grande y translúcido: es textura, no información.
        $glyph = self::glyph($icon);

        return <<<SVG
        <svg class="cover-art" viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="g{$id}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="{$from}"/>
                    <stop offset="100%" stop-color="{$to}"/>
                </linearGradient>
                <radialGradient id="r{$id}" cx="78%" cy="18%" r="72%">
                    <stop offset="0%" stop-color="{$accent}" stop-opacity=".22"/>
                    <stop offset="100%" stop-color="{$accent}" stop-opacity="0"/>
                </radialGradient>
                <pattern id="h{$id}" width="46" height="80" patternUnits="userSpaceOnUse" patternTransform="rotate(0)">
                    <path d="M23 0l20 11.5v23L23 46 3 34.5v-23z" fill="none" stroke="{$accent}" stroke-opacity=".085" stroke-width="1.1"/>
                    <path d="M23 40l20 11.5v23L23 86 3 74.5v-23z" fill="none" stroke="{$accent}" stroke-opacity=".085" stroke-width="1.1"/>
                </pattern>
            </defs>

            <rect width="400" height="300" fill="url(#g{$id})"/>
            <rect width="400" height="300" fill="url(#h{$id})"/>
            <rect width="400" height="300" fill="url(#r{$id})"/>

            <g transform="translate(276 66) scale(5.6)" stroke="{$accent}" stroke-opacity=".16" stroke-width="1.4"
               fill="none" stroke-linecap="round" stroke-linejoin="round">{$glyph}</g>
        </svg>
        SVG;
    }

    /** Portada para un barbero: usa sus iniciales en grande. */
    public static function initials(string $seed, string $initials): string
    {
        [$from, $to, $accent] = self::paletteFor($seed);
        $id                   = 'bv' . (++self::$counter) . substr(md5($seed), 0, 5);
        $text                 = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');

        return <<<SVG
        <svg class="cover-art" viewBox="0 0 400 500" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="g{$id}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="{$from}"/>
                    <stop offset="100%" stop-color="{$to}"/>
                </linearGradient>
                <pattern id="h{$id}" width="46" height="80" patternUnits="userSpaceOnUse">
                    <path d="M23 0l20 11.5v23L23 46 3 34.5v-23z" fill="none" stroke="{$accent}" stroke-opacity=".09" stroke-width="1.1"/>
                    <path d="M23 40l20 11.5v23L23 86 3 74.5v-23z" fill="none" stroke="{$accent}" stroke-opacity=".09" stroke-width="1.1"/>
                </pattern>
            </defs>
            <rect width="400" height="500" fill="url(#g{$id})"/>
            <rect width="400" height="500" fill="url(#h{$id})"/>
            <text x="200" y="250" text-anchor="middle" dominant-baseline="central"
                  font-family="Archivo, Inter, sans-serif" font-size="150" font-weight="800"
                  fill="{$accent}" fill-opacity=".26">{$text}</text>
        </svg>
        SVG;
    }

    /** @return array{0:string,1:string,2:string} */
    private static function paletteFor(string $seed): array
    {
        $index = hexdec(substr(md5($seed), 0, 8)) % count(self::PALETTES);

        return self::PALETTES[$index];
    }

    private static function iconFor(string $seed): string
    {
        $normalized = mb_strtolower($seed);

        foreach (self::KEYWORDS as $keyword => $icon) {
            if (str_contains($normalized, $keyword)) {
                return $icon;
            }
        }

        return 'scissors';
    }

    /** Trazos del ícono, tomados de la misma biblioteca que el resto. */
    private static function glyph(string $icon): string
    {
        return match ($icon) {
            'star'   => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14l-5-4.87 6.91-1.01L12 2z"/>',
            'user'   => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'bottle' => '<path d="M10 2h4v3l1.5 2.5A4 4 0 0116 10v9a3 3 0 01-3 3h-2a3 3 0 01-3-3v-9a4 4 0 01.5-2.5L10 5V2z"/><path d="M8 12h8"/>',
            default  => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88M14.47 14.48L20 20M8.12 8.12L12 12"/>',
        };
    }
}
