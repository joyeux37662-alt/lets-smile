<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public function findForLogin(string $cabinetSlug, string $email): ?array
    {
        $sql = "
            SELECT
                users.*,
                roles.name AS role_name,
                roles.label AS role_label,
                cabinets.id AS cabinet_id,
                cabinets.name AS cabinet_name,
                cabinets.slug AS cabinet_slug,
                (
                    SELECT GROUP_CONCAT(permissions.name ORDER BY permissions.name SEPARATOR ',')
                    FROM role_permissions
                    INNER JOIN permissions ON permissions.id = role_permissions.permission_id
                    WHERE role_permissions.role_id = roles.id
                ) AS permissions
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            INNER JOIN user_cabinets ON user_cabinets.user_id = users.id
            INNER JOIN cabinets ON cabinets.id = user_cabinets.cabinet_id
            WHERE cabinets.slug = :cabinet
              AND users.email = :email
              AND users.status = 'active'
            LIMIT 1
        ";

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'cabinet' => $cabinetSlug,
            'email' => $email,
        ]);

        $user = $statement->fetch();

        return $user ?: null;
    }

    public function markLoggedIn(int $userId): void
    {
        $statement = Database::connection()->prepare("
            UPDATE users
            SET last_login_at = NOW()
            WHERE id = :id
        ");
        $statement->execute(['id' => $userId]);
    }
}
