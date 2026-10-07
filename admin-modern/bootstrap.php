<?php
require __DIR__ . '/../app/bootstrap.php';
session_start();

function cfg2(string $path, mixed $default = null): mixed { return cfg($path, $default); }
function aesc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function a_legacy(string|null $s): string { return legacy($s); }

$admin = (array)cfg2('admin', []);
$adminUser = (string)($admin['username'] ?? getenv('CIRITAS_ADMIN_USER') ?: '');
$adminHash = (string)($admin['password_hash'] ?? getenv('CIRITAS_ADMIN_PASSWORD_HASH') ?: '');

function admin_logged_in(): bool { return !empty($_SESSION['ciritas_admin']); }
function admin_require_login(): void { if (!admin_logged_in()) { header('Location: login.php'); exit; } }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(24)); return $_SESSION['_csrf']; }
function csrf_check(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('CSRF validation failed'); } }
function redirect(string $to): never { header('Location: '.$to); exit; }
function column_exists(string $table, string $column): bool {
    static $cache=[]; $k="$table.$column"; if(isset($cache[$k])) return $cache[$k];
    try { $q=db()->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $q->execute([$column]); return $cache[$k]=(bool)$q->fetch(); }
    catch(Throwable) { return $cache[$k]=false; }
}
