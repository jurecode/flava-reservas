<?php
/**
 * Ruta: /app/Services/System/BackupService.php
 *
 * Respaldos previos a cada actualización (spec §125, §126).
 * Se guardan en /storage/backups, FUERA del directorio público.
 */

namespace App\Services\System;

use Core\Database;

final class BackupService
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = rtrim($path ?? STORAGE_PATH . '/backups', '/');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Crea un respaldo completo: base de datos + metadatos.
     *
     * @return array{name:string,path:string,database:?string,size:int,created_at:string}
     */
    public function create(string $label = 'manual', array $metadata = []): array
    {
        $name      = 'backup_' . now()->format('Y-m-d_His') . '_' . slugify($label);
        $directory = $this->path . '/' . $name;

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo crear el directorio de respaldo. Revisa permisos en /storage/backups.');
        }

        $dumpFile = null;

        try {
            $dumpFile = $this->dumpDatabase($directory . '/database.sql');
        } catch (\Throwable $e) {
            logger()->error('No se pudo respaldar la base de datos', ['error' => $e->getMessage()]);
        }

        $meta = array_merge([
            'name'       => $name,
            'label'      => $label,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'version'    => config('version.version'),
            'database'   => config('database.database'),
            'php'        => PHP_VERSION,
            'has_dump'   => $dumpFile !== null,
        ], $metadata);

        @file_put_contents(
            $directory . '/metadata.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // Protección extra por si el directorio quedara accesible.
        @file_put_contents($directory . '/.htaccess', "Require all denied\n");

        logger()->deploy('Respaldo creado', ['name' => $name, 'has_dump' => $dumpFile !== null]);

        return [
            'name'       => $name,
            'path'       => $directory,
            'database'   => $dumpFile,
            'size'       => $this->directorySize($directory),
            'created_at' => $meta['created_at'],
        ];
    }

    /**
     * Vuelca la base de datos. Usa mysqldump si está disponible; si no, genera
     * el dump desde PHP con PDO (funciona en hostings sin exec()).
     */
    public function dumpDatabase(string $destination): string
    {
        if ($this->tryMysqldump($destination)) {
            return $destination;
        }

        return $this->phpDump($destination);
    }

    private function tryMysqldump(string $destination): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (in_array('proc_open', $disabled, true)) {
            return false;
        }

        $config = config('database');

        // La contraseña se pasa por variable de entorno, nunca en la línea de
        // comandos (sería visible en la lista de procesos del servidor).
        // --set-gtid-purged=OFF : sin esto MySQL 8+ añade SET @@GLOBAL.GTID_PURGED
        //   al volcado, y la restauración falla con el error 3546. Un respaldo
        //   que no se puede restaurar no sirve de nada.
        // --no-tablespaces      : evita necesitar el privilegio PROCESS, que los
        //   hostings compartidos no conceden.
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --quick'
            . ' --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF'
            . ' --default-character-set=utf8mb4 %s',
            escapeshellarg((string) $config['host']),
            escapeshellarg((string) $config['port']),
            escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['database'])
        );

        $handle = @fopen($destination, 'wb');

        if ($handle === false) {
            return false;
        }

        $process = @proc_open(
            $command,
            [1 => $handle, 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['MYSQL_PWD' => (string) $config['password'], 'PATH' => getenv('PATH') ?: '/usr/bin:/bin:/usr/local/bin']
        );

        if (!is_resource($process)) {
            fclose($handle);

            return false;
        }

        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);
        fclose($handle);

        if ($code !== 0 || filesize($destination) === 0) {
            @unlink($destination);
            logger()->warning('mysqldump no disponible, se usará el volcado PHP', ['detail' => mb_substr($stderr, 0, 200)]);

            return false;
        }

        return true;
    }

    /**
     * Volcado portable con PDO: sirve en cualquier hosting compartido.
     *
     * Omite las columnas GENERADAS (como `bookings.active_slot`): el motor las
     * calcula solo y escribirlas en un INSERT provoca el error 3105.
     */
    private function phpDump(string $destination): string
    {
        $db     = Database::instance();
        $handle = @fopen($destination, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo escribir el archivo de respaldo.');
        }

        $write = static function (string $text) use ($handle): void {
            fwrite($handle, $text);
        };

        $write("-- Respaldo Flava Studio\n");
        $write('-- Base: ' . config('database.database') . "\n");
        $write('-- Fecha: ' . now()->format('Y-m-d H:i:s') . "\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = array_map(
            static fn (array $row): string => (string) reset($row),
            $db->select('SHOW TABLES')
        );

        foreach ($tables as $table) {
            $create = $db->selectOne("SHOW CREATE TABLE `{$table}`");
            $write("DROP TABLE IF EXISTS `{$table}`;\n");
            $write(($create['Create Table'] ?? '') . ";\n\n");

            $columns = $this->insertableColumns($table);

            if ($columns === []) {
                continue;
            }

            $columnList = '`' . implode('`, `', $columns) . '`';
            $select     = '`' . implode('`, `', $columns) . '`';
            $offset     = 0;
            $chunk      = 500;

            while (true) {
                $rows = $db->select("SELECT {$select} FROM `{$table}` LIMIT {$chunk} OFFSET {$offset}");

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $values = array_map(
                        static fn ($value): string => $value === null ? 'NULL' : $db->pdo()->quote((string) $value),
                        array_values($row)
                    );

                    $write(sprintf(
                        "INSERT INTO `%s` (%s) VALUES (%s);\n",
                        $table,
                        $columnList,
                        implode(', ', $values)
                    ));
                }

                $offset += $chunk;
            }

            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        return $destination;
    }

    /**
     * Columnas que se pueden insertar: excluye las generadas (STORED/VIRTUAL).
     *
     * @return array<int,string>
     */
    private function insertableColumns(string $table): array
    {
        $rows = Database::instance()->select(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND (EXTRA IS NULL OR EXTRA NOT LIKE '%GENERATED%')
             ORDER BY ORDINAL_POSITION",
            ['t' => $table]
        );

        return array_column($rows, 'COLUMN_NAME');
    }

    /** @return array<int,array{name:string,created_at:string,size:int,metadata:array}> */
    public function list(int $limit = 30): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $directories = glob($this->path . '/backup_*', GLOB_ONLYDIR) ?: [];
        rsort($directories, SORT_STRING);

        $backups = [];

        foreach (array_slice($directories, 0, $limit) as $directory) {
            $metadata = [];
            $metaFile = $directory . '/metadata.json';

            if (is_file($metaFile)) {
                $metadata = json_decode((string) file_get_contents($metaFile), true) ?? [];
            }

            $backups[] = [
                'name'       => basename($directory),
                'created_at' => $metadata['created_at'] ?? date('Y-m-d H:i:s', (int) filemtime($directory)),
                'size'       => $this->directorySize($directory),
                'metadata'   => $metadata,
            ];
        }

        return $backups;
    }

    public function exists(string $name): bool
    {
        return $this->isValidName($name) && is_dir($this->path . '/' . $name);
    }

    /** Elimina respaldos antiguos conservando los N más recientes. */
    public function prune(int $keep = 10): int
    {
        $directories = glob($this->path . '/backup_*', GLOB_ONLYDIR) ?: [];
        rsort($directories, SORT_STRING);

        $removed = 0;

        foreach (array_slice($directories, $keep) as $directory) {
            if ($this->removeDirectory($directory)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function sqlFileFor(string $name): ?string
    {
        if (!$this->isValidName($name)) {
            return null;
        }

        $file = $this->path . '/' . $name . '/database.sql';

        return is_file($file) ? $file : null;
    }

    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}_[a-z0-9\-]+$/', $name);
    }

    private function directorySize(string $directory): int
    {
        $size = 0;

        foreach (glob($directory . '/*') ?: [] as $file) {
            $size += is_file($file) ? (int) filesize($file) : $this->directorySize($file);
        }

        return $size;
    }

    private function removeDirectory(string $directory): bool
    {
        foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (in_array(basename($file), ['.', '..'], true)) {
                continue;
            }

            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }

        return @rmdir($directory);
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }
}
