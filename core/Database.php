<?php
/**
 * Ruta: /core/Database.php
 * Conexión PDO única + helpers de consulta preparada.
 * Regla del proyecto: JAMÁS concatenar entrada de usuario en SQL (spec §56).
 */

namespace Core;

use PDO;
use PDOException;
use PDOStatement;

final class Database
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;
    private int $transactions = 0;

    private function __construct(private readonly array $config)
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(config('database'));
    }

    /** Conexión perezosa: no se conecta hasta la primera consulta. */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['driver'] ?? 'mysql',
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? '3306',
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );

        $options = ($this->config['options'] ?? []) + [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->config['username'] ?? '', $this->config['password'] ?? '', $options);
            // Zona horaria de la sesión MySQL alineada con la app.
            $this->pdo->exec("SET time_zone = '" . $this->mysqlOffset() . "'");
        } catch (PDOException $e) {
            logger()->emergency('No se pudo conectar a la base de datos', ['error' => $e->getMessage()]);
            throw new \RuntimeException('No se pudo conectar a la base de datos.', 500, $e);
        }

        return $this->pdo;
    }

    private function mysqlOffset(): string
    {
        $tz     = new \DateTimeZone(config('app.timezone', 'America/Santiago'));
        $offset = $tz->getOffset(new \DateTime('now', $tz));
        $sign   = $offset < 0 ? '-' : '+';
        $offset = abs($offset);

        return sprintf('%s%02d:%02d', $sign, intdiv($offset, 3600), intdiv($offset % 3600, 60));
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type  = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /** @return array<int,array<string,mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->query($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->query($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $bindings = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`{$column}` = :set_{$column}";
        }

        $params = [];
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);

        return $this->statement($sql, $params + $bindings);
    }

    public function delete(string $table, string $where, array $bindings = []): int
    {
        return $this->statement(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $bindings);
    }

    // ---- Transacciones (soportan anidamiento con SAVEPOINT) ----

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactions + 1));
        }

        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo()->commit();
        }

        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions <= 1) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->transactions = 0;

            return;
        }

        $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactions);
        $this->transactions--;
    }

    /** Ejecuta un callback dentro de una transacción con rollback automático. */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function isConnected(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function tableExists(string $table): bool
    {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t',
            ['t' => $table]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }
}
