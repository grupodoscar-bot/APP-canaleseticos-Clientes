<?php
declare(strict_types=1);
// Auto-install wizard / Asistente de instalación AUTOMÁTICA (SQLite zero-config).

// Mini i18n (sin BD todavía)
$LANG = 'es';
if (!empty($_GET['lang']) && in_array($_GET['lang'], ['es','en'], true)) $LANG = $_GET['lang'];
elseif (!empty($_COOKIE['instlang']) && in_array($_COOKIE['instlang'], ['es','en'], true)) $LANG = $_COOKIE['instlang'];
elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match('/^en/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'])) $LANG = 'en';
setcookie('instlang', $LANG, time()+3600, '/');

$T = [
'es' => [
    'already'  => 'Ya instalado. Elimina config.php para reinstalar (¡cuidado, perderás la configuración!).',
    'php'      => 'PHP ≥ 8.0',
    'openssl'  => 'Extensión openssl',
    'pdosqlite'=> 'Extensión pdo_sqlite',
    'fileinfo' => 'Extensión fileinfo',
    'mbstring' => 'Extensión mbstring',
    'writable' => 'Escritura en raíz (para config.php)',
    'private'  => 'Carpeta /private (o creable)',
    'uploads'  => 'Carpeta /uploads (o creable)',
    'title'    => 'Instalación automática',
    'sub'      => 'Un solo formulario. Sin configurar base de datos.',
    'env'      => 'Comprobación del entorno',
    'env_err'  => 'Corrige los puntos marcados antes de continuar. Normalmente basta con activar <code>pdo_sqlite</code> en PHP Settings de Plesk o ajustar permisos de carpeta a <code>755</code>.',
    'company'  => 'Empresa',
    'cname'    => 'Nombre de la empresa',
    'cemail'   => 'Email del responsable de compliance',
    'cemail_h' => 'recibe avisos de nuevas denuncias',
    'cfrom'    => 'Email remitente (From)',
    'cfrom_h'  => 'opcional',
    'admin'    => 'Usuario administrador',
    'aemail'   => 'Email admin',
    'apwd'     => 'Contraseña',
    'apwd_h'   => 'mín. 10',
    'apwd2'    => 'Repetir contraseña',
    'btn'      => 'Instalar ahora',
    'foot'     => 'Se creará la base de datos SQLite en <code>private/canal.sqlite</code>, se generarán las claves de cifrado, se borrarán los archivos por defecto del hosting y el panel quedará listo.',
    'ok_title' => 'Instalación completada',
    'ok_sub'   => 'El canal ético está listo para funcionar.',
    'ok_alert' => 'Base de datos SQLite creada, claves de cifrado AES-256 generadas y usuario administrador activo.',
    'ok_clean' => 'Archivos de plantilla eliminados:',
    'ok_warn'  => '<strong>Recordatorio de seguridad:</strong> cuando pases a producción, borra <code>install.php</code> por FTP. Mientras exista <code>config.php</code> queda desactivado, pero si alguien eliminara el config podría reinstalar.',
    'ok_next'  => 'Siguiente paso',
    'ok_next_d'=> 'Accede al panel y configura tu 2FA (obligatorio en el primer login).',
    'ok_go'    => 'Entrar al panel',
    'ok_home'  => 'Ver portada pública',
    'err_req'  => 'Campo requerido:',
    'err_match'=> 'Las contraseñas no coinciden',
    'err_pwd'  => 'La contraseña admin debe tener al menos 10 caracteres',
    'err_ae'   => 'Email admin inválido',
    'err_ce'   => 'Email compliance inválido',
    'err_conf' => 'No se pudo escribir config.php — comprueba permisos de carpeta',
    'err_inst' => 'Error instalación:',
    'brand_sub'=> 'Asistente de instalación',
],
'en' => [
    'already'  => 'Already installed. Delete config.php to reinstall (careful, configuration will be lost).',
    'php'      => 'PHP ≥ 8.0',
    'openssl'  => 'openssl extension',
    'pdosqlite'=> 'pdo_sqlite extension',
    'fileinfo' => 'fileinfo extension',
    'mbstring' => 'mbstring extension',
    'writable' => 'Writable root (for config.php)',
    'private'  => '/private folder (or creatable)',
    'uploads'  => '/uploads folder (or creatable)',
    'title'    => 'Automatic installation',
    'sub'      => 'One single form. No database setup.',
    'env'      => 'Environment check',
    'env_err'  => 'Fix the marked items before continuing. Usually it\'s enough to enable <code>pdo_sqlite</code> in Plesk PHP settings or set folder permissions to <code>755</code>.',
    'company'  => 'Company',
    'cname'    => 'Company name',
    'cemail'   => 'Compliance officer email',
    'cemail_h' => 'receives new report alerts',
    'cfrom'    => 'From email',
    'cfrom_h'  => 'optional',
    'admin'    => 'Administrator user',
    'aemail'   => 'Admin email',
    'apwd'     => 'Password',
    'apwd_h'   => 'min. 10',
    'apwd2'    => 'Repeat password',
    'btn'      => 'Install now',
    'foot'     => 'SQLite database will be created at <code>private/canal.sqlite</code>, encryption keys generated, default hosting files removed, and the panel left ready.',
    'ok_title' => 'Installation completed',
    'ok_sub'   => 'The ethics channel is ready to operate.',
    'ok_alert' => 'SQLite database created, AES-256 encryption keys generated and admin user active.',
    'ok_clean' => 'Removed template files:',
    'ok_warn'  => '<strong>Security reminder:</strong> when moving to production, delete <code>install.php</code> via FTP. While <code>config.php</code> exists the installer stays disabled, but if someone deleted it they could reinstall.',
    'ok_next'  => 'Next step',
    'ok_next_d'=> 'Go to the panel and set up your 2FA (required on first login).',
    'ok_go'    => 'Enter the panel',
    'ok_home'  => 'See public landing',
    'err_req'  => 'Required field:',
    'err_match'=> 'Passwords do not match',
    'err_pwd'  => 'Admin password must be at least 10 characters',
    'err_ae'   => 'Invalid admin email',
    'err_ce'   => 'Invalid compliance email',
    'err_conf' => 'Could not write config.php — check folder permissions',
    'err_inst' => 'Install error:',
    'brand_sub'=> 'Installation wizard',
],
];
function it(string $k): string { global $T, $LANG; return $T[$LANG][$k] ?? $T['es'][$k] ?? $k; }

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    http_response_code(403);
    exit(it('already'));
}

// Pre-chequeo del entorno
$reqs = [];
$reqs[] = [it('php'), version_compare(PHP_VERSION, '8.0.0', '>=')];
$reqs[] = [it('openssl'), extension_loaded('openssl')];
$reqs[] = [it('pdosqlite'), extension_loaded('pdo_sqlite')];
$reqs[] = [it('fileinfo'), extension_loaded('fileinfo')];
$reqs[] = [it('mbstring'), extension_loaded('mbstring')];
$reqs[] = [it('writable'), is_writable(__DIR__)];
$reqs[] = [it('private'), is_dir(__DIR__ . '/private') || @mkdir(__DIR__ . '/private', 0755, true)];
$reqs[] = [it('uploads'), is_dir(__DIR__ . '/uploads') || @mkdir(__DIR__ . '/uploads', 0755, true)];

$envOk = !in_array(false, array_column($reqs, 1), true);

function schema_sqlite(): array {
    return [
        "CREATE TABLE IF NOT EXISTS tenants_config (
            id INTEGER PRIMARY KEY,
            empresa TEXT NOT NULL,
            email_compliance TEXT NOT NULL,
            logo_path TEXT NULL,
            color_brand TEXT NULL,
            politica_extra TEXT NULL,
            aviso_legal_extra TEXT NULL,
            updated_at TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            pass_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'officer' CHECK (role IN ('admin','officer')),
            totp_secret TEXT NULL,
            totp_enabled INTEGER NOT NULL DEFAULT 0,
            failed_logins INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            last_login TEXT NULL
        )",
        "CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo_seguimiento TEXT NOT NULL UNIQUE,
            categoria TEXT NOT NULL,
            titulo_enc TEXT NOT NULL,
            descripcion_enc TEXT NOT NULL,
            relacion TEXT NULL,
            estado TEXT NOT NULL DEFAULT 'recibida' CHECK (estado IN ('recibida','en_curso','resuelta','desestimada','bloqueada')),
            en_investigacion INTEGER NOT NULL DEFAULT 0,
            anonimizada INTEGER NOT NULL DEFAULT 0,
            acknowledged_at TEXT NULL,
            assigned_user_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            resolved_at TEXT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_reports_estado ON reports(estado, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_reports_codigo ON reports(codigo_seguimiento)",
        "CREATE TABLE IF NOT EXISTS report_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            filename_original_enc TEXT NOT NULL,
            filename_disk TEXT NOT NULL,
            mime TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_att_report ON report_attachments(report_id)",
        "CREATE TABLE IF NOT EXISTS communications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER NOT NULL,
            sender TEXT NOT NULL CHECK (sender IN ('denunciante','admin')),
            user_id INTEGER NULL,
            body_enc TEXT NOT NULL,
            created_at TEXT NOT NULL,
            read_at TEXT NULL,
            FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_com_report ON communications(report_id)",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            action TEXT NOT NULL,
            report_id INTEGER NULL,
            ip_hash TEXT NOT NULL,
            ua_hash TEXT NOT NULL,
            meta TEXT NULL,
            created_at TEXT NOT NULL,
            prev_hash TEXT NOT NULL,
            row_hash TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_audit_report ON audit_logs(report_id)",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_hash TEXT NOT NULL,
            email TEXT NOT NULL,
            success INTEGER NOT NULL DEFAULT 0,
            attempted_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_la_ip ON login_attempts(ip_hash, attempted_at)",
        "CREATE INDEX IF NOT EXISTS idx_la_email ON login_attempts(email, attempted_at)",
    ];
}

function cleanup_plesk_files(string $root): array {
    $deleted = [];
    foreach (['index.html', 'index.htm', 'default.html', 'default.htm'] as $f) {
        $p = $root . '/' . $f;
        if (is_file($p) && @unlink($p)) $deleted[] = $f;
    }
    $doc = $root . '/documentacion';
    if (is_dir($doc)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($doc, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        if (@rmdir($doc)) $deleted[] = 'documentacion/';
    }
    return $deleted;
}

$errors = [];
$data = [
    'empresa'          => $_POST['empresa'] ?? '',
    'email_compliance' => $_POST['email_compliance'] ?? '',
    'email_from'       => $_POST['email_from'] ?? '',
    'admin_email'      => $_POST['admin_email'] ?? '',
    'admin_pass'       => $_POST['admin_pass'] ?? '',
    'admin_pass2'      => $_POST['admin_pass2'] ?? '',
];
$done = false;
$deletedFiles = [];
$selfDeleted = false;

if ($envOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['empresa','email_compliance','admin_email','admin_pass'] as $k) {
        if (trim($data[$k]) === '') $errors[] = it('err_req') . ' ' . $k;
    }
    if ($data['admin_pass'] !== $data['admin_pass2']) $errors[] = it('err_match');
    if (strlen($data['admin_pass']) < 10) $errors[] = it('err_pwd');
    if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = it('err_ae');
    if (!filter_var($data['email_compliance'], FILTER_VALIDATE_EMAIL)) $errors[] = it('err_ce');

    if (!$errors) {
        try {
            @mkdir(__DIR__ . '/private', 0755, true);
            @mkdir(__DIR__ . '/uploads', 0755, true);

            $htBlock = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
            $privHt = __DIR__ . '/private/.htaccess';
            if (!file_exists($privHt)) file_put_contents($privHt, $htBlock);
            $upHt = __DIR__ . '/uploads/.htaccess';
            if (!file_exists($upHt)) file_put_contents($upHt, $htBlock);

            // Crear SQLite desde cero: si hay BD residual (reinstalación), borrarla
            $dbPath = __DIR__ . '/private/canal.sqlite';
            foreach ([$dbPath, $dbPath.'-wal', $dbPath.'-shm', $dbPath.'-journal'] as $leftover) {
                if (is_file($leftover)) @unlink($leftover);
            }
            // Limpiar log de debug de Stripe de instalaciones anteriores
            $stripeLog = __DIR__ . '/private/stripe_debug.log';
            if (is_file($stripeLog)) @unlink($stripeLog);
            $pdo = new PDO('sqlite:' . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            foreach (schema_sqlite() as $sql) { $pdo->exec($sql); }
            @chmod($dbPath, 0640);

            $now = gmdate('Y-m-d H:i:s');

            $st = $pdo->prepare("INSERT OR REPLACE INTO tenants_config (id, empresa, email_compliance, updated_at) VALUES (1, ?, ?, ?)");
            $st->execute([$data['empresa'], $data['email_compliance'], $now]);

            $hash = password_hash($data['admin_pass'], PASSWORD_ARGON2ID);
            $st = $pdo->prepare("INSERT INTO users (email, pass_hash, role, created_at, activo) VALUES (?,?, 'admin', ?, 1)");
            $st->execute([$data['admin_email'], $hash, $now]);

            $appKey  = base64_encode(random_bytes(32));
            $hmacKey = base64_encode(random_bytes(32));
            $emailFrom = trim($data['email_from']) ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

            $config = [
                'db' => [
                    'driver' => 'sqlite',
                    'path'   => $dbPath,
                ],
                'app_key'          => $appKey,
                'hmac_key'         => $hmacKey,
                'empresa'          => $data['empresa'],
                'email_compliance' => $data['email_compliance'],
                'email_from'       => $emailFrom,
                'smtp'             => null,
                'installed_at'     => $now,
                'central_url'      => 'https://canaleseticos.es',
                'stripe_secret_key'=> 'rk_test_51I6umeGzm8CG4SCC76wI7wRB9mc0o71OD1EV2qGHnaTi8wHUN2sppytmYOVQGVQ686858ZfrJNUzApyBCDcdfDIG00faQcffQN',
                'stripe_price_eur' => 12000,
            ];

            $export = "<?php\n// Generado por install.php el {$now}. NO COMMITEAR. Contiene claves secretas.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configPath, $export, LOCK_EX) === false) {
                throw new RuntimeException(it('err_conf'));
            }
            @chmod($configPath, 0640);

            // Registro en el servidor central canaleseticos.es (best-effort).
            $GLOBALS['CONFIG'] = $config;
            require_once __DIR__ . '/includes/central_client.php';
            $regHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $regRes = central_register($regHost, (string)$data['empresa'], (string)$data['admin_email']);
            $centralOk = !empty($regRes['ok']);
            $centralErr = $centralOk ? '' : (string)($regRes['err'] ?? 'unknown');

            $deletedFiles = cleanup_plesk_files(__DIR__);
            $selfDeleted = false; // auto-borrado desactivado: así puedes reinstalar sin volver a subir install.php

            $done = true;
        } catch (Throwable $e) {
            $errors[] = it('err_inst') . ' ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="<?= $LANG ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= it('title') ?> — Canal Ético</title>
<link rel="stylesheet" href="/assets/css/style.css">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#1f54c7">
</head><body>
<header class="topbar"><div class="topbar-inner">
    <span class="brand">
        <span class="brand-logo">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L3 6v6c0 5 3.8 9.3 9 10 5.2-.7 9-5 9-10V6l-9-4z"/><path d="M9 12l2 2 4-4"/></svg>
        </span>
        <span>Canal Ético<small><?= it('brand_sub') ?></small></span>
    </span>
    <div class="lang-switch">
        <a href="?lang=es" class="<?= $LANG==='es'?'active':'' ?>">ES</a>
        <a href="?lang=en" class="<?= $LANG==='en'?'active':'' ?>">EN</a>
    </div>
</div></header>

<main class="mid">
<?php if ($done): ?>
    <div class="card">
        <div class="card-head">
            <div class="brand-logo" style="background:linear-gradient(135deg,var(--ok),#064d35);width:44px;height:44px;border-radius:12px">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </div>
            <div>
                <h1 style="margin:0"><?= it('ok_title') ?></h1>
                <p class="muted" style="margin:4px 0 0"><?= it('ok_sub') ?></p>
            </div>
        </div>

        <div class="alert ok"><div><?= it('ok_alert') ?></div></div>

        <?php if ($deletedFiles): ?>
            <p class="small muted"><?= it('ok_clean') ?> <?= htmlspecialchars(implode(', ', $deletedFiles), ENT_QUOTES) ?></p>
        <?php endif; ?>

        <div class="alert warn"><div><?= it('ok_warn') ?></div></div>

        <div class="section-title"><?= it('ok_next') ?></div>
        <p><?= it('ok_next_d') ?></p>
        <div class="btn-row">
            <a class="btn lg" href="/admin/login.php"><?= it('ok_go') ?></a>
            <a class="btn secondary lg" href="/index.php"><?= it('ok_home') ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="page-head">
        <span class="pill"><?= it('brand_sub') ?></span>
        <h1 style="margin-top:10px"><?= it('title') ?></h1>
        <p><?= it('sub') ?></p>
    </div>

    <div class="card card-compact">
        <div class="section-title" style="margin-top:0"><?= it('env') ?></div>
        <ul class="clean">
            <?php foreach ($reqs as $r): ?>
                <li>
                    <span style="color:<?= $r[1] ? 'var(--ok)' : 'var(--err)' ?>">
                        <?php if ($r[1]): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                        <?php else: ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        <?php endif; ?>
                    </span>
                    <span><?= htmlspecialchars($r[0], ENT_QUOTES) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!$envOk): ?>
        <div class="alert err"><div><?= it('env_err') ?></div></div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= htmlspecialchars($e, ENT_QUOTES) ?></div></div><?php endforeach; ?>

    <form method="post" autocomplete="off" class="card" <?= !$envOk ? 'style="opacity:.5;pointer-events:none"' : '' ?>>
        <div class="section-title" style="margin-top:0"><?= it('company') ?></div>
        <div class="form-group">
            <label><?= it('cname') ?></label>
            <input name="empresa" required value="<?= htmlspecialchars($data['empresa'], ENT_QUOTES) ?>" placeholder="<?= $LANG==='en' ? 'My Company Ltd.' : 'Mi Empresa S.L.' ?>">
        </div>
        <div class="form-group">
            <label><?= it('cemail') ?> <small><?= it('cemail_h') ?></small></label>
            <input type="email" name="email_compliance" required value="<?= htmlspecialchars($data['email_compliance'], ENT_QUOTES) ?>" placeholder="compliance@example.com">
        </div>
        <div class="form-group">
            <label><?= it('cfrom') ?> <small><?= it('cfrom_h') ?></small></label>
            <input type="email" name="email_from" value="<?= htmlspecialchars($data['email_from'], ENT_QUOTES) ?>" placeholder="no-reply@<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="section-title"><?= it('admin') ?></div>
        <div class="form-group">
            <label><?= it('aemail') ?></label>
            <input type="email" name="admin_email" required value="<?= htmlspecialchars($data['admin_email'], ENT_QUOTES) ?>">
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label><?= it('apwd') ?> <small><?= it('apwd_h') ?></small></label>
                <input type="password" name="admin_pass" required minlength="10">
            </div>
            <div class="form-group">
                <label><?= it('apwd2') ?></label>
                <input type="password" name="admin_pass2" required minlength="10">
            </div>
        </div>

        <div class="btn-row"><button class="btn lg block" type="submit"><?= it('btn') ?></button></div>
        <p class="help" style="text-align:center;margin-top:12px"><?= it('foot') ?></p>
    </form>
<?php endif; ?>
</main>
</body></html>
