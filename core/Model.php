<?php
/**
 * Ruta: /core/Model.php
 * Modelo base: acceso a datos con PDO preparado. Los modelos concretos
 * encapsulan las consultas propias de su entidad; las vistas nunca consultan.
 */

namespace Core;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    /** Columnas asignables masivamente. */
    protected array $fillable = [];

    /** Columnas nunca devueltas al exterior (tokens, hashes...). */
    protected array $hidden = [];

    protected bool $timestamps = true;

    public function db(): Database
    {
        return Database::instance();
    }

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    // ---- Lectura ----

    public function find(int|string $id): ?array
    {
        return $this->db()->selectOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1",
            ['id' => $id]
        );
    }

    public function findOrFail(int|string $id): array
    {
        $row = $this->find($id);

        if ($row === null) {
            throw Exceptions\HttpException::notFound('Registro no encontrado');
        }

        return $row;
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $this->guardColumn($column);

        return $this->db()->selectOne(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value LIMIT 1",
            ['value' => $value]
        );
    }

    /**
     * @param array<string,mixed> $conditions  columna => valor (o [operador, valor])
     */
    public function where(array $conditions = [], ?string $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        [$sql, $bindings] = $this->buildWhere($conditions);

        $query = "SELECT * FROM `{$this->table}` {$sql}";

        if ($orderBy !== null) {
            $query .= ' ORDER BY ' . $this->sanitizeOrderBy($orderBy);
        }
        if ($limit !== null) {
            $query .= ' LIMIT ' . (int) $limit;
            if ($offset !== null) {
                $query .= ' OFFSET ' . (int) $offset;
            }
        }

        return $this->db()->select($query, $bindings);
    }

    public function firstWhere(array $conditions): ?array
    {
        return $this->where($conditions, null, 1)[0] ?? null;
    }

    public function all(?string $orderBy = null): array
    {
        return $this->where([], $orderBy);
    }

    public function count(array $conditions = []): int
    {
        [$sql, $bindings] = $this->buildWhere($conditions);

        return (int) $this->db()->scalar("SELECT COUNT(*) FROM `{$this->table}` {$sql}", $bindings);
    }

    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Paginación simple y consistente para las tablas de administración.
     *
     * @return array{data:array,total:int,page:int,per_page:int,last_page:int}
     */
    public function paginate(array $conditions = [], int $page = 1, int $perPage = 20, ?string $orderBy = null): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total   = $this->count($conditions);

        return [
            'data'      => $this->where($conditions, $orderBy, $perPage, ($page - 1) * $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ---- Escritura ----

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['created_at'] ??= now()->format('Y-m-d H:i:s');
            $data['updated_at'] ??= $data['created_at'];
        }

        return $this->db()->insert($this->table, $data);
    }

    public function update(int|string $id, array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['updated_at'] = now()->format('Y-m-d H:i:s');
        }

        if ($data === []) {
            return 0;
        }

        return $this->db()->update(
            $this->table,
            $data,
            "`{$this->primaryKey}` = :pk_id",
            ['pk_id' => $id]
        );
    }

    public function updateWhere(array $conditions, array $data): int
    {
        [$sql, $bindings] = $this->buildWhere($conditions);
        $data             = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['updated_at'] = now()->format('Y-m-d H:i:s');
        }

        return $this->db()->update($this->table, $data, trim(str_replace('WHERE', '', $sql)) ?: '1=1', $bindings);
    }

    public function delete(int|string $id): int
    {
        return $this->db()->delete($this->table, "`{$this->primaryKey}` = :id", ['id' => $id]);
    }

    // ---- Utilidades ----

    /** Elimina del arreglo las claves ocultas antes de exponer datos. */
    public function withoutHidden(array $row): array
    {
        foreach ($this->hidden as $key) {
            unset($row[$key]);
        }

        return $row;
    }

    public function hideMany(array $rows): array
    {
        return array_map([$this, 'withoutHidden'], $rows);
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array{0:string,1:array}
     */
    protected function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $clauses  = [];
        $bindings = [];
        $index    = 0;

        foreach ($conditions as $column => $value) {
            // Cláusula cruda: ['raw:booking_date >= :from' => ['from' => ...]]
            if (str_starts_with((string) $column, 'raw:')) {
                $clauses[] = '(' . substr((string) $column, 4) . ')';
                $bindings  = array_merge($bindings, (array) $value);
                continue;
            }

            $operator = '=';
            if (is_array($value) && count($value) === 2 && is_string($value[0])) {
                [$operator, $value] = $value;
                $operator           = $this->sanitizeOperator($operator);
            }

            $this->guardColumn((string) $column);

            if ($value === null) {
                $clauses[] = "`{$column}` " . ($operator === '!=' ? 'IS NOT NULL' : 'IS NULL');
                continue;
            }

            if (is_array($value)) {
                $placeholders = [];
                foreach (array_values($value) as $item) {
                    $key                 = 'w' . $index++;
                    $placeholders[]      = ':' . $key;
                    $bindings[$key]      = $item;
                }
                $clauses[] = "`{$column}` " . ($operator === '!=' ? 'NOT IN' : 'IN') . ' (' . implode(', ', $placeholders ?: [':empty']) . ')';
                if ($placeholders === []) {
                    $bindings['empty'] = null;
                }
                continue;
            }

            $key            = 'w' . $index++;
            $clauses[]      = "`{$column}` {$operator} :{$key}";
            $bindings[$key] = $value;
        }

        return ['WHERE ' . implode(' AND ', $clauses), $bindings];
    }

    protected function sanitizeOperator(string $operator): string
    {
        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'];

        return in_array(strtoupper($operator), $allowed, true) ? strtoupper($operator) : '=';
    }

    /** Impide inyección por nombre de columna proveniente de filtros. */
    protected function guardColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new \InvalidArgumentException('Columna inválida: ' . $column);
        }
    }

    protected function sanitizeOrderBy(string $orderBy): string
    {
        $parts = [];

        foreach (explode(',', $orderBy) as $piece) {
            $piece               = trim($piece);
            [$column, $direction] = array_pad(preg_split('/\s+/', $piece) ?: [], 2, 'ASC');
            $this->guardColumn((string) $column);
            $direction = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';
            $parts[]   = "`" . str_replace('.', '`.`', (string) $column) . "` {$direction}";
        }

        return implode(', ', $parts);
    }
}
