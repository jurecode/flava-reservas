<?php
/**
 * Ruta: /app/Services/System/MigrationService.php
 *
 * Ejecuta las migraciones SQL pendientes de /database/migrations (spec §123).
 * Cada archivo se ejecuta UNA sola vez y queda registrado en `migrations`.
 * NUNCA se reimporta flava.sql sobre una base con datos (spec §122).
 *
 * Convención de nombres:  20260824_001_descripcion.sql
 *
 * IMPORTANTE — por qué NO usamos transacciones:
 *   MySQL/MariaDB hacen COMMIT IMPLÍCITO en cada sentencia DDL (ALTER, CREATE,
 *   DROP...). Envolver una migración en BEGIN/COMMIT no la vuelve atómica: sólo
 *   crea una falsa sensación de seguridad y rompe el flujo al intentar cerrar
 *   una transacción que el motor ya cerró.
 *   La red de seguridad real es el RESPALDO que se crea antes de migrar, y
 *   escribir migraciones idempotentes (IF NOT EXISTS / IF EXISTS).
 *   Si una sentencia falla, el proceso se detiene, la migración NO se marca
 *   como ejecutada y se informa exactamente en qué sentencia quedó.
 */

namespace App\Services\System;

use App\Models\Migration;
use Core\Database;

final class MigrationService
{
    private string $path;

    public function __construct(
        ?string $path = null,
        private readonly Migration $migrations = new Migration(),
    ) {
        $this->path = $path ?? DATABASE_PATH . '/migrations';
    }

    /** @return array<int,string> nombres de archivo disponibles, ordenados */
    public function available(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = array_map(
            'basename',
            glob($this->path . '/*.sql') ?: []
        );

        sort($files, SORT_STRING);

        return $files;
    }

    /** @return array<int,string> migraciones aún no ejecutadas */
    public function pending(): array
    {
        $executed = $this->migrations->executedNames();

        return array_values(array_filter(
            $this->available(),
            static fn (string $file): bool => !in_array(pathinfo($file, PATHINFO_FILENAME), $executed, true)
        ));
    }

    public function hasPending(): bool
    {
        return $this->pending() !== [];
    }

    /**
     * Ejecuta todas las migraciones pendientes, deteniéndose en el primer error.
     *
     * @return array{
     *   executed: array<int,string>, failed: ?string, error: ?string,
     *   batch: int, partial: bool, statement: ?int
     * }
     */
    public function run(): array
    {
        $pending = $this->pending();

        if ($pending === []) {
            return ['executed' => [], 'failed' => null, 'error' => null, 'batch' => 0, 'partial' => false, 'statement' => null];
        }

        $batch    = $this->migrations->nextBatch();
        $executed = [];
        $pdo      = Database::instance()->pdo();

        foreach ($pending as $file) {
            $name       = pathinfo($file, PATHINFO_FILENAME);
            $sql        = (string) @file_get_contents($this->path . '/' . $file);
            $statements = $this->splitStatements($sql);

            if ($statements === []) {
                logger()->deploy("Migración vacía omitida: {$file}");
                continue;
            }

            $index = 0;

            try {
                foreach ($statements as $index => $statement) {
                    $pdo->exec($statement);
                }

                $this->migrations->record($name, $batch, sha1($sql));
                $executed[] = $name;
                logger()->deploy("Migración aplicada: {$name} ({$index} sentencias)");
            } catch (\Throwable $e) {
                logger()->error('Fallo en migración', [
                    'migration' => $name,
                    'statement' => $index + 1,
                    'error'     => $e->getMessage(),
                ]);

                return [
                    'executed'  => $executed,
                    'failed'    => $name,
                    'error'     => $e->getMessage(),
                    'batch'     => $batch,
                    // El DDL ya ejecutado no se revierte: hay que revisarlo a mano.
                    'partial'   => $index > 0,
                    'statement' => $index + 1,
                ];
            }
        }

        return ['executed' => $executed, 'failed' => null, 'error' => null, 'batch' => $batch, 'partial' => false, 'statement' => null];
    }

    /**
     * Divide un archivo SQL en sentencias respetando cadenas y comentarios.
     * (No usamos explode(';') porque rompería datos con punto y coma.)
     *
     * @return array<int,string>
     */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $length     = strlen($sql);
        $inSingle   = false;
        $inDouble   = false;
        $inBacktick = false;
        $inLineComment  = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $current      .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if (($char === '-' && $next === '-') || $char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            // Comillas (respetando el escape con barra invertida)
            $escaped = $i > 0 && $sql[$i - 1] === '\\';

            if ($char === "'" && !$inDouble && !$inBacktick && !$escaped) {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && !$inBacktick && !$escaped) {
                $inDouble = !$inDouble;
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $statement = trim($current);

                if ($statement !== '') {
                    $statements[] = $statement;
                }

                $current = '';
                continue;
            }

            $current .= $char;
        }

        $last = trim($current);

        if ($last !== '') {
            $statements[] = $last;
        }

        return $statements;
    }

    /** Historial para el panel SUPER_ADMIN. */
    public function history(int $limit = 50): array
    {
        return $this->migrations->history($limit);
    }

    /** Crea el archivo de una migración nueva (uso en desarrollo local). */
    public function make(string $description): string
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }

        $sequence = str_pad((string) (count($this->available()) + 1), 3, '0', STR_PAD_LEFT);
        $name     = now()->format('Ymd') . '_' . $sequence . '_' . slugify($description) . '.sql';
        $file     = $this->path . '/' . $name;

        file_put_contents($file, <<<SQL
        -- Ruta: /database/migrations/{$name}
        -- {$description}
        -- Generada: {$this->timestamp()}
        --
        -- Reglas:
        --   · Debe poder ejecutarse UNA sola vez.
        --   · Nunca eliminar datos existentes sin respaldo previo.
        --   · Usar IF NOT EXISTS / IF EXISTS cuando el motor lo permita.


        SQL);

        return $name;
    }

    private function timestamp(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
