<?php
/**
 * โหลดค่าจากไฟล์ .env (KEY=VALUE) แล้วกำหนด YII_DEBUG / YII_ENV
 */
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, $default = null)
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return match (strtolower($v)) {
        'true' => true,
        'false' => false,
        default => $v,
    };
}

defined('YII_ENV') or define('YII_ENV', env('YII_ENV', 'prod'));
defined('YII_DEBUG') or define('YII_DEBUG', (bool)env('YII_DEBUG', false));
