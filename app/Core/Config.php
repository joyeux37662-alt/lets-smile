<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        [$file, $name] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset(self::$items[$file])) {
            $path = base_path('config' . DIRECTORY_SEPARATOR . $file . '.php');
            self::$items[$file] = is_file($path) ? require $path : [];
        }

        if ($name === null) {
            return self::$items[$file] ?? $default;
        }

        $value = self::$items[$file] ?? [];

        foreach (explode('.', $name) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
