<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const REP_LOGIN_MAX_IP_15M = 10;
const REP_LOGIN_MAX_EMAIL_15M = 5;
const REP_LOGIN_MAX_FAILS_LOCK = 10;
const REP_LOGIN_LOCK_MINUTES = 30;
const REP_SESSION_TTL = 3600;

function reporter_by_email(string $email): ?array {
    $st = db()->prepare("SELECT * FROM reporters WHERE email = ? LIMIT 1");
    $st->execute([strtolower(trim($email))]);
    $r = $st->fetch();
    return $r ?: null;
}
function reporter_by_id(int $id): ?array {
    $st = db()->prepare("SELECT * FROM reporters WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function reporter_rate_limit_ok(string $email): bool {
    $pdo = db();
    $ip = client_ip_hash();
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    $q = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND success=0 AND attempted_at > ?");
    $q->execute([$ip, $since]);
    if ((int)$q->fetchColumn() >= REP_LOGIN_MAX_IP_15M) return false;
    $q = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email=? AND success=0 AND attempted_at > ?");
    $q->execute([$email, $since]);
    if ((int)$q->fetchColumn() >= REP_LOGIN_MAX_EMAIL_15M) return false;
    return true;
}

function reporter_try_login(string $email, string $password): array {
    $email = strtolower(trim($email));
    if (!reporter_rate_limit_ok($email)) return ['ok' => false, 'err' => 'Demasiados intentos. Espera unos minutos.'];
    $r = reporter_by_email($email);
    if (!$r || !$r['activo']) {
        db()->prepare("INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?,?,0,?)")
            ->execute([client_ip_hash(), $email, gmdate('Y-m-d H:i:s')]);
        return ['ok' => false, 'err' => 'Credenciales inválidas'];
    }
    if (!empty($r['locked_until']) && strtotime($r['locked_until'] . ' UTC') > time()) {
        return ['ok' => false, 'err' => 'Cuenta bloqueada temporalmente'];
    }
    if (!password_verify($password, $r['pass_hash'])) {
        db()->prepare("INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?,?,0,?)")
            ->execute([client_ip_hash(), $email, gmdate('Y-m-d H:i:s')]);
        $pdo = db();
        $pdo->prepare("UPDATE reporters SET failed_logins=failed_logins+1 WHERE id=?")->execute([(int)$r['id']]);
        $r2 = reporter_by_id((int)$r['id']);
        if ($r2 && (int)$r2['failed_logins'] >= REP_LOGIN_MAX_FAILS_LOCK) {
            $until = gmdate('Y-m-d H:i:s', time() + REP_LOGIN_LOCK_MINUTES * 60);
            $pdo->prepare("UPDATE reporters SET locked_until=?, failed_logins=0 WHERE id=?")->execute([$until, (int)$r['id']]);
        }
        audit('reporter_login_failed', null, null, ['email' => $email]);
        return ['ok' => false, 'err' => 'Credenciales inválidas'];
    }
    db()->prepare("INSERT INTO login_attempts (ip_hash, email, success, attempted_at) VALUES (?,?,1,?)")
        ->execute([client_ip_hash(), $email, gmdate('Y-m-d H:i:s')]);
    return ['ok' => true, 'reporter' => $r];
}

function reporter_complete_login(array $r): void {
    session_regenerate_id(true);
    $_SESSION['rep_uid'] = (int)$r['id'];
    $_SESSION['rep_ts']  = time();
    $now = gmdate('Y-m-d H:i:s');
    db()->prepare("UPDATE reporters SET failed_logins=0, locked_until=NULL, last_login=? WHERE id=?")
        ->execute([$now, (int)$r['id']]);
    audit('reporter_login_success', null, null, ['rid' => (int)$r['id']]);
}

function reporter_current(): ?array {
    if (empty($_SESSION['rep_uid'])) return null;
    if (!empty($_SESSION['rep_ts']) && (time() - $_SESSION['rep_ts']) > REP_SESSION_TTL) {
        reporter_logout();
        return null;
    }
    $r = reporter_by_id((int)$_SESSION['rep_uid']);
    if (!$r || !$r['activo']) { reporter_logout(); return null; }
    $_SESSION['rep_ts'] = time();
    return $r;
}

function require_reporter(): array {
    $r = reporter_current();
    if (!$r) { header('Location: /login.php'); exit; }
    return $r;
}

function reporter_logout(): void {
    unset($_SESSION['rep_uid'], $_SESSION['rep_ts']);
}

function reporter_register(string $email, string $password, string $nombre = ''): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'err' => 'Email no válido'];
    if (strlen($password) < 8) return ['ok' => false, 'err' => 'La contraseña debe tener al menos 8 caracteres'];
    if (reporter_by_email($email)) return ['ok' => false, 'err' => 'Ya existe una cuenta con este email'];
    $nombre = trim($nombre);
    if (mb_strlen($nombre) > 200) return ['ok' => false, 'err' => 'Nombre demasiado largo'];
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $now = gmdate('Y-m-d H:i:s');
    $st = db()->prepare("INSERT INTO reporters (email, nombre_enc, pass_hash, created_at, updated_at) VALUES (?,?,?,?,?)");
    $st->execute([$email, $nombre !== '' ? enc($nombre) : null, $hash, $now, $now]);
    $rid = (int)db()->lastInsertId();
    audit('reporter_registered', null, null, ['rid' => $rid, 'email' => $email]);
    return ['ok' => true, 'rid' => $rid];
}
