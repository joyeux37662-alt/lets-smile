<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class UserManagement
{
    private PDO $db;

    private const MODULE_LABELS = [
        'dashboard' => 'Tableau de bord',
        'agenda' => 'Agenda',
        'patients' => 'Patients',
        'appointments' => 'Rendez-vous',
        'treatments' => 'Traitements',
        'billing' => 'Facturation',
        'payments' => 'Paiements',
        'stock' => 'Stock',
        'documents' => 'Documents',
        'messages' => 'Messages',
        'reports' => 'Rapports',
        'settings' => 'Paramètres',
        'users' => 'Utilisateurs',
    ];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function dashboard(int $cabinetId): array
    {
        $permissions = $this->permissions();

        return [
            'users' => $this->users($cabinetId),
            'roles' => $this->roles($cabinetId),
            'permissions' => $permissions,
            'permissionGroups' => $this->permissionGroups($permissions),
            'rolePermissions' => $this->rolePermissions(),
            'userStats' => $this->stats($cabinetId),
            'auditLogs' => $this->auditLogs($cabinetId),
        ];
    }

    public function createUser(int $cabinetId, array $data, int $actorId): int
    {
        $this->assertRoleExists((int) $data['role_id']);
        $this->assertEmailAvailable((string) $data['email']);

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare("
                INSERT INTO users (role_id, full_name, email, phone, password_hash, status)
                VALUES (:role_id, :full_name, :email, :phone, :password_hash, :status)
            ");
            $statement->execute([
                'role_id' => (int) $data['role_id'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                'status' => $data['status'],
            ]);

            $userId = (int) $this->db->lastInsertId();

            $link = $this->db->prepare("
                INSERT INTO user_cabinets (user_id, cabinet_id)
                VALUES (:user_id, :cabinet_id)
            ");
            $link->execute([
                'user_id' => $userId,
                'cabinet_id' => $cabinetId,
            ]);

            $this->audit($cabinetId, $actorId, 'user.created', 'users', $userId, null, [
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'role_id' => (int) $data['role_id'],
                'status' => $data['status'],
            ]);

            $this->db->commit();

            return $userId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateUser(int $cabinetId, int $userId, array $data, int $actorId): void
    {
        $current = $this->user($cabinetId, $userId);
        $this->assertRoleExists((int) $data['role_id']);
        $this->assertEmailAvailable((string) $data['email'], $userId);

        $sql = "
            UPDATE users
            SET role_id = :role_id,
                full_name = :full_name,
                email = :email,
                phone = :phone,
                status = :status
        ";
        $parameters = [
            'role_id' => (int) $data['role_id'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'status' => $data['status'],
            'id' => $userId,
        ];

        if ((string) ($data['password'] ?? '') !== '') {
            $sql .= ", password_hash = :password_hash";
            $parameters['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id";

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        $this->audit($cabinetId, $actorId, 'user.updated', 'users', $userId, $current, [
            'role_id' => (int) $data['role_id'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'password_changed' => (string) ($data['password'] ?? '') !== '',
        ]);
    }

    public function updateStatus(int $cabinetId, int $userId, string $status, int $actorId): void
    {
        $current = $this->user($cabinetId, $userId);

        $statement = $this->db->prepare("
            UPDATE users
            SET status = :status
            WHERE id = :id
        ");
        $statement->execute([
            'status' => $status,
            'id' => $userId,
        ]);

        $this->audit($cabinetId, $actorId, 'user.status_updated', 'users', $userId, $current, [
            'status' => $status,
        ]);
    }

    public function changeOwnPassword(int $cabinetId, int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->user($cabinetId, $userId, true);

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            throw new RuntimeException('Le mot de passe actuel est incorrect.');
        }

        $statement = $this->db->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE id = :id
        ");
        $statement->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        $this->audit($cabinetId, $userId, 'account.password_updated', 'users', $userId, null, [
            'password_changed' => true,
        ]);
    }

    public function updateRolePermissions(int $cabinetId, int $roleId, array $permissionNames, int $actorId): void
    {
        $role = $this->role($roleId);
        $allPermissions = $this->permissions();
        $availableNames = array_column($allPermissions, 'name');
        $selectedNames = array_values(array_intersect($permissionNames, $availableNames));

        if ($role['name'] === 'super_admin') {
            $selectedNames = $availableNames;
        }

        if ($role['name'] === 'admin_cabinet') {
            $selectedNames = array_values(array_unique(array_merge($selectedNames, [
                'dashboard.view',
                'settings.manage',
                'users.manage',
            ])));
        }

        $before = $this->rolePermissions()[$roleId] ?? [];

        $this->db->beginTransaction();

        try {
            $delete = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
            $delete->execute(['role_id' => $roleId]);

            if ($selectedNames !== []) {
                $insert = $this->db->prepare("
                    INSERT INTO role_permissions (role_id, permission_id)
                    SELECT :role_id, id
                    FROM permissions
                    WHERE name = :name
                ");

                foreach ($selectedNames as $name) {
                    $insert->execute([
                        'role_id' => $roleId,
                        'name' => $name,
                    ]);
                }
            }

            $this->audit($cabinetId, $actorId, 'role.permissions_updated', 'roles', $roleId, [
                'permissions' => array_keys($before),
            ], [
                'permissions' => $selectedNames,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function users(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                u.id,
                u.role_id,
                u.full_name,
                u.email,
                u.phone,
                u.status,
                u.last_login_at,
                u.created_at,
                r.name AS role_name,
                r.label AS role_label,
                COUNT(DISTINCT rp.permission_id) AS permission_count
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN role_permissions rp ON rp.role_id = u.role_id
            WHERE uc.cabinet_id = :cabinet_id
            GROUP BY u.id, u.role_id, u.full_name, u.email, u.phone, u.status, u.last_login_at, u.created_at, r.name, r.label
            ORDER BY u.status = 'active' DESC, u.full_name ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function roles(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                r.id,
                r.name,
                r.label,
                COUNT(DISTINCT uc.user_id) AS user_count,
                COUNT(DISTINCT rp.permission_id) AS permission_count
            FROM roles r
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN users u ON u.role_id = r.id
            LEFT JOIN user_cabinets uc ON uc.user_id = u.id AND uc.cabinet_id = :cabinet_id
            GROUP BY r.id, r.name, r.label
            ORDER BY FIELD(r.name, 'super_admin', 'admin_cabinet', 'dentiste', 'assistant', 'secretaire', 'comptable', 'lecture_seule'), r.label ASC
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function permissions(): array
    {
        $statement = $this->db->query("
            SELECT id, name, label
            FROM permissions
            ORDER BY name ASC
        ");

        return $statement->fetchAll();
    }

    private function permissionGroups(array $permissions): array
    {
        $groups = [];

        foreach ($permissions as $permission) {
            [$module] = explode('.', (string) $permission['name'], 2);
            $groups[$module]['label'] = self::MODULE_LABELS[$module] ?? ucfirst($module);
            $groups[$module]['permissions'][] = $permission;
        }

        return $groups;
    }

    private function rolePermissions(): array
    {
        $statement = $this->db->query("
            SELECT rp.role_id, p.name
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            ORDER BY p.name ASC
        ");

        $items = [];

        foreach ($statement->fetchAll() as $row) {
            $items[(int) $row['role_id']][(string) $row['name']] = true;
        }

        return $items;
    }

    private function stats(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                COUNT(*) AS total_users,
                SUM(u.status = 'active') AS active_users,
                SUM(u.status = 'blocked') AS blocked_users
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            WHERE uc.cabinet_id = :cabinet_id
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);
        $userStats = $statement->fetch() ?: [];

        $roles = (int) $this->db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        $permissions = (int) $this->db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();

        return [
            'total_users' => (int) ($userStats['total_users'] ?? 0),
            'active_users' => (int) ($userStats['active_users'] ?? 0),
            'blocked_users' => (int) ($userStats['blocked_users'] ?? 0),
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    private function auditLogs(int $cabinetId): array
    {
        $statement = $this->db->prepare("
            SELECT
                al.action,
                al.entity_type,
                al.entity_id,
                al.created_at,
                u.full_name AS actor_name
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.cabinet_id = :cabinet_id
              AND al.action IN ('user.created', 'user.updated', 'user.status_updated', 'role.permissions_updated', 'account.password_updated')
            ORDER BY al.created_at DESC
            LIMIT 8
        ");
        $statement->execute(['cabinet_id' => $cabinetId]);

        return $statement->fetchAll();
    }

    private function user(int $cabinetId, int $userId, bool $withPassword = false): array
    {
        $columns = $withPassword ? 'u.*' : 'u.id, u.role_id, u.full_name, u.email, u.phone, u.status, u.last_login_at, u.created_at';
        $statement = $this->db->prepare("
            SELECT {$columns}
            FROM users u
            INNER JOIN user_cabinets uc ON uc.user_id = u.id
            WHERE u.id = :id
              AND uc.cabinet_id = :cabinet_id
            LIMIT 1
        ");
        $statement->execute([
            'id' => $userId,
            'cabinet_id' => $cabinetId,
        ]);

        $user = $statement->fetch();

        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable dans ce cabinet.');
        }

        return $user;
    }

    private function role(int $roleId): array
    {
        $statement = $this->db->prepare("SELECT * FROM roles WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $roleId]);
        $role = $statement->fetch();

        if (!$role) {
            throw new RuntimeException('Rôle introuvable.');
        }

        return $role;
    }

    private function assertRoleExists(int $roleId): void
    {
        $this->role($roleId);
    }

    private function assertEmailAvailable(string $email, ?int $exceptUserId = null): void
    {
        $sql = "SELECT id FROM users WHERE email = :email";
        $parameters = ['email' => $email];

        if ($exceptUserId !== null) {
            $sql .= " AND id <> :id";
            $parameters['id'] = $exceptUserId;
        }

        $sql .= " LIMIT 1";
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        if ($statement->fetch()) {
            throw new RuntimeException('Cette adresse email est déjà utilisée.');
        }
    }

    private function audit(int $cabinetId, int $actorId, string $action, string $entityType, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $statement = $this->db->prepare("
            INSERT INTO audit_logs (cabinet_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (:cabinet_id, :user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)
        ");
        $statement->execute([
            'cabinet_id' => $cabinetId,
            'user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);
    }
}
