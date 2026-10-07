<?php
$config = require __DIR__ . '/../config/config.php';
$local = __DIR__ . '/../config/local.php';
if (is_file($local)) {
    $override = require $local;
    $config = array_replace_recursive($config, is_array($override) ? $override : []);
}

function cfg(string $path, mixed $default = null): mixed {
    global $config;
    $value = $config;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return $default;
        $value = $value[$key];
    }
    return $value;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', cfg('db.host'), (int)cfg('db.port',3306), cfg('db.name'), cfg('db.charset','latin1'));
    $pdo = new PDO($dsn, (string)cfg('db.user'), (string)cfg('db.password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function legacy(string|null $value): string {
    if ($value === null || $value === '') return (string)$value;
    if (mb_check_encoding($value, 'UTF-8')) return $value;
    return mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
}
