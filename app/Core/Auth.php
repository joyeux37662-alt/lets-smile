<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function cabinet(): ?array
    {
        return $_SESSION['cabinet'] ?? null;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'role' => $user['role_name'],
            'role_label' => $user['role_label'],
            'permissions' => array_filter(explode(',', (string) ($user['permissions'] ?? ''))),
        ];

        $_SESSION['cabinet'] = [
            'id' => $user['cabinet_id'],
            'name' => $user['cabinet_name'],
            'slug' => $user['cabinet_slug'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function can(string $permission): bool
    {
        $user = self::user();

        if (!$user) {
            return false;
        }

        if (in_array($user['role'] ?? '', ['super_admin', 'admin_cabinet'], true)) {
            return true;
        }

        return in_array($permission, $user['permissions'] ?? [], true);
    }
}
