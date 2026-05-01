<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

function base_path(string $path = ''): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function app_path(string $path = ''): string
{
    return base_path('app' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)));
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)));
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)));
}

function env_value(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($base !== '/' && $base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    return '/' . trim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function old(string $key, mixed $default = ''): mixed
{
    $value = $_SESSION['_old'][$key] ?? $default;
    unset($_SESSION['_old'][$key]);

    return $value;
}

function remember_old(array $data, array $keys): void
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $_SESSION['_old'][$key] = $data[$key];
        }
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function format_money(float|int|string|null $amount): string
{
    $value = (float) ($amount ?? 0);

    return number_format($value, 0, ',', ' ') . ' ' . config('app.currency_symbol', 'Ar');
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return (new DateTimeImmutable($date))->format('d/m/Y');
}

function format_time(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return (new DateTimeImmutable($date))->format('H:i');
}

function full_name(array $person): string
{
    return trim(($person['last_name'] ?? '') . ' ' . ($person['first_name'] ?? ''));
}

function initials(array $person): string
{
    $first = strtoupper(substr((string) ($person['first_name'] ?? ''), 0, 1));
    $last = strtoupper(substr((string) ($person['last_name'] ?? ''), 0, 1));
    $value = $last . $first;

    return $value !== '' ? $value : 'LS';
}
