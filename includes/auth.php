<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/totp.php';

const LOGIN_MAX_IP_15M   = 10;
const LOGIN_MAX_EMAIL_15M = 5;
const LOGIN_MAX_FAILS_LOCK = 10;
const LOGIN_LOCK_MINUTES = 30;

function user_by_email(string $email): ?array {
    $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();
    return $u ?: null;
}
function user_by_id(int $id): ?array {
    $st = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
}

function rate_limit_ok(string $email): bool {
    $pdo = db();
    $ip = client_ip_hash();
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $q = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND success=0 AND attempted_at > ?");
    $q->execute([$ip, $since]);
    if ((int)$q->fetchColumn() >= LOGIN_MAX_IP_15M) return false;
    $q = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email=? AND success=0 AND attempted_at > ?");
    $q->execute([$email, $since]);
    if ((int)$q->fetchColumn() >= LOGIN_MAX_EMAIL_15M) return false;
    return true;
}
function record_attempt(string $email, bool $ok): void {
    $st = db()->prepare("INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?,?,?,?)");
    $st->execute([client_ip_hash(), $email, $ok ? 1 : 0, gmdate('Y-m-d H:i:s')]);
}
function user_locked(array $u): bool {
    return !empty($u['locked_until']) && strtotime($u['locked_until'] . ' UTC') > time();
}
function bump_failed(int $userId): void {
    $pdo = db();
    $pdo->prepare("UPDATE users SET failed_logins=failed_logins+1 WHERE id=?")->execute([$userId]);
    $u = user_by_id($userId);
    if ($u && (int)$u['failed_logins'] >= LOGIN_MAX_FAILS_LOCK) {
        $until = gmdate('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60);
        $pdo->prepare("UPDATE users SET locked_until=?, failed_logins=0 WHERE id=?")->execute([$until, $userId]);
    }
}
function reset_failed(int $userId): void {
    db()->prepare("UPDATE users SET failed_logins=0, locked_until=NULL, last_login=? WHERE id=?")
        ->execute([gmdate('Y-m-d H:i:s'), $userId]);
}

function try_login(string $email, string $password): array {
    if (!rate_limit_ok($email)) return ['ok' => false, 'err' => 'Demasiados intentos. Espera unos minutos.'];
    $u = user_by_email($email);
    if (!$u || !$u['activo']) {
        record_attempt($email, false);
        return ['ok' => false, 'err' => 'Credenciales inválidas'];
    }
    if (user_locked($u)) {
        return ['ok' => false, 'err' => 'Cuenta bloqueada temporalmente'];
    }
    if (!password_verify($password, $u['pass_hash'])) {
        record_attempt($email, false);
        bump_failed((int)$u['id']);
        audit('login_failed', (int)$u['id'], null, ['email' => $email]);
        return ['ok' => false, 'err' => 'Credenciales inválidas'];
    }
    record_attempt($email, true);
    return ['ok' => true, 'user' => $u];
}

function complete_login(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid']  = (int)$u['id'];
    $_SESSION['role'] = $u['role'];
    $_SESSION['login_ts'] = time();
    reset_failed((int)$u['id']);
    audit('login_success', (int)$u['id']);
}

function require_auth(): array {
    if (empty($_SESSION['uid'])) {
        header('Location: login.php'); exit;
    }
    // Expiración de sesión: 60 min inactividad
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
        session_destroy();
        header('Location: login.php?expired=1'); exit;
    }
    $_SESSION['last_activity'] = time();
    $u = user_by_id((int)$_SESSION['uid']);
    if (!$u || !$u['activo']) { session_destroy(); header('Location: login.php'); exit; }
    return $u;
}
function require_admin(array $u): void {
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Requiere rol admin'); }
}
function logout(): void {
    $uid = $_SESSION['uid'] ?? null;
    if ($uid) audit('logout', (int)$uid);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
