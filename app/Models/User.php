<?php
/**
 * Ruta: /app/Models/User.php
 * Personal interno del sistema (SUPER_ADMIN, ADMIN, RECEPTION, BARBER).
 */

namespace App\Models;

use App\Support\Role;
use Core\Model;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = [
        'branch_id', 'first_name', 'last_name', 'email', 'phone', 'password',
        'role', 'avatar', 'status', 'must_change_password',
        'reset_token', 'reset_expires_at', 'last_login_at',
    ];

    protected array $hidden = ['password', 'reset_token', 'reset_expires_at'];

    public function findByEmail(string $email): ?array
    {
        return $this->db()->selectOne(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1",
            ['email' => mb_strtolower(trim($email))]
        );
    }

    /** Crea un usuario hasheando la contraseña. */
    public function createUser(array $data): int
    {
        $data['email']    = mb_strtolower(trim((string) $data['email']));
        $data['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);

        return $this->create($data);
    }

    public function updatePassword(int $id, string $plainPassword): void
    {
        $this->db()->update(
            $this->table,
            [
                'password'             => password_hash($plainPassword, PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'reset_token'          => null,
                'reset_expires_at'     => null,
                'updated_at'           => now()->format('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }

    public function touchLastLogin(int $id): void
    {
        $this->db()->update(
            $this->table,
            ['last_login_at' => now()->format('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int,array> Usuarios con su barbero asociado si lo tienen. */
    public function listWithBarber(array $filters = []): array
    {
        $sql = "SELECT u.*, b.id AS barber_id, b.display_name AS barber_name, br.name AS branch_name
                FROM {$this->table} u
                LEFT JOIN barbers b   ON b.user_id = u.id
                LEFT JOIN branches br ON br.id = u.branch_id
                WHERE 1 = 1";

        $bindings = [];

        if (!empty($filters['role'])) {
            $sql              .= ' AND u.role = :role';
            $bindings['role']  = $filters['role'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql                .= ' AND u.status = :status';
            $bindings['status']  = (int) $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql                .= ' AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)';
            $bindings['search']  = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY FIELD(u.role, "SUPER_ADMIN","ADMIN","RECEPTION","BARBER"), u.first_name';

        return $this->hideMany($this->db()->select($sql, $bindings));
    }

    public function fullName(array $user): string
    {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }

    /** Evita que quede el sistema sin ningún SUPER_ADMIN activo. */
    public function activeSuperAdminCount(?int $excludingId = null): int
    {
        $sql      = "SELECT COUNT(*) FROM {$this->table} WHERE role = :role AND status = 1";
        $bindings = ['role' => Role::SUPER_ADMIN];

        if ($excludingId !== null) {
            $sql            .= ' AND id != :id';
            $bindings['id']  = $excludingId;
        }

        return (int) $this->db()->scalar($sql, $bindings);
    }

    public function findByResetToken(string $token): ?array
    {
        return $this->db()->selectOne(
            "SELECT * FROM {$this->table}
             WHERE reset_token = :token AND reset_expires_at > NOW() AND status = 1
             LIMIT 1",
            ['token' => hash('sha256', $token)]
        );
    }

    public function storeResetToken(int $id, string $token, int $minutes = 60): void
    {
        $this->db()->update(
            $this->table,
            [
                'reset_token'      => hash('sha256', $token),
                'reset_expires_at' => now()->modify("+{$minutes} minutes")->format('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $id]
        );
    }
}
