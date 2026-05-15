<?php
declare(strict_types=1);

if (defined('APP_BOOTSTRAPPED')) return;
define('APP_BOOTSTRAPPED', true);

define('APP_ROOT', dirname(__DIR__));
define('APP_CONFIG', APP_ROOT . '/config.php');

// Sesión segura
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        // Lax (no Strict) para que la sesión sobreviva al redirect de vuelta desde
        // stripe.com tras completar el pago. Sigue siendo seguro ante CSRF (los
        // POST cross-site no envían cookie; el CSRF token además protege los POST).
        'samesite' => 'Lax',
    ]);
    session_name('CANALSID');
    session_start();
}

// Headers de seguridad
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.stripe.com; style-src 'self' 'unsafe-inline'; script-src 'self' https://js.stripe.com; connect-src 'self' https://api.stripe.com; form-action 'self' https://checkout.stripe.com https://*.stripe.com; frame-src https://js.stripe.com https://hooks.stripe.com https://*.stripe.com; frame-ancestors 'none'; base-uri 'self'");
    if (!empty($_SERVER['HTTPS'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Si no hay config y no estamos en install, redirige a install
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!file_exists(APP_CONFIG)) {
    if ($script !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

$CONFIG = require APP_CONFIG;
$GLOBALS['CONFIG'] = $CONFIG;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/central_client.php';

// Gate del servidor central (activar/desactivar desde canaleseticos.es)
// - Solo lanzamos el check remoto en GET (nunca en POST, para no ralentizar formularios)
// - Si está bloqueado (inactive/suspended), redirige a maintenance (excepto admin/ajustes)
// - Si está pending, solo se permite inicio + panel admin
$__scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__scriptPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$__isMaintenancePage = in_array($__scriptName, ['maintenance.php', 'install.php'], true);
$__isAdminArea = strpos($__scriptPath, '/admin/') !== false;
$__isLegal     = strpos($__scriptPath, '/legal/') !== false;
$__isIndex     = $__scriptName === 'index.php';
$__isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
if (!$__isMaintenancePage) {
    if ($__isGet) {
        try { central_lazy_check(); } catch (Throwable $e) { /* silencioso */ }
    }
    if (central_is_blocked()) {
        // Permitimos todo el área admin (login + licencia) para que el admin
        // pueda consultar el estado y forzar un refresh.
        if (!$__isAdminArea) {
            header('Location: /maintenance.php');
            exit;
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string { return $_SESSION['csrf']; }
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(400);
        exit('CSRF inválido');
    }
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function app_url(string $path = ''): string {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/' . ltrim($path, '/');
}
function empresa(): string { return (string)($GLOBALS['CONFIG']['empresa'] ?? 'Canal Ético'); }

/**
 * Rate limiting para formularios públicos (denunciar, reunión, registro).
 * Usa la misma tabla login_attempts con email='_form:<accion>'.
 * Falla abierto en errores de BD para no bloquear usuarios legítimos.
 */
function public_rate_limit_check(string $action, int $limit, int $window_seconds = 3600): bool {
    try {
        $ip    = client_ip_hash();
        $since = gmdate('Y-m-d H:i:s', time() - $window_seconds);
        $q = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND email=? AND attempted_at > ?");
        $q->execute([$ip, '_form:' . $action, $since]);
        return (int)$q->fetchColumn() < $limit;
    } catch (Throwable $e) { return true; }
}

function public_rate_limit_record(string $action): void {
    try {
        db()->prepare("INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?,?,0,?)")
            ->execute([client_ip_hash(), '_form:' . $action, gmdate('Y-m-d H:i:s')]);
    } catch (Throwable $e) {}
}

function brand_logo_url(): ?string {
    static $cached = null;
    if ($cached !== null) return $cached ?: null;
    try {
        $rel = db()->query("SELECT logo_path FROM tenants_config WHERE id=1")->fetchColumn();
        if (!$rel) { $cached = ''; return null; }
        $abs = APP_ROOT . '/' . ltrim((string)$rel, '/');
        if (!is_file($abs)) { $cached = ''; return null; }
        $cached = '/' . ltrim((string)$rel, '/') . '?v=' . filemtime($abs);
        return $cached;
    } catch (Throwable $e) { $cached = ''; return null; }
}
