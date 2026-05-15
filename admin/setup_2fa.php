<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';

$uid = (int)($_SESSION['pending_uid'] ?? 0);
if (!$uid || empty($_SESSION['setup_2fa'])) { header('Location: /admin/login.php'); exit; }
$u = user_by_id($uid);
if (!$u) { header('Location: /admin/login.php'); exit; }

if (empty($_SESSION['new_totp'])) {
    $_SESSION['new_totp'] = totp_secret_new();
}
$secret = (string)$_SESSION['new_totp'];
$uri = totp_uri($secret, (string)$u['email'], empresa() . ' Canal');
$errors = [];
$showRecoveryCodes = null; // se asigna tras verificación exitosa

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $code = (string)($_POST['code'] ?? '');
    if (!totp_verify($secret, $code)) {
        $errors[] = 'Código incorrecto. Vuelve a intentarlo.';
    } else {
        // Guardar secreto TOTP
        db()->prepare("UPDATE users SET totp_secret=?, totp_enabled=1 WHERE id=?")->execute([$secret, $uid]);

        // Generar 8 códigos de recuperación de un solo uso
        $chars     = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin O, I, 0, 1
        $plainCodes = [];
        db()->prepare("DELETE FROM totp_recovery_codes WHERE user_id=?")->execute([$uid]);
        $insStmt = db()->prepare("INSERT INTO totp_recovery_codes (user_id, code_hash, created_at) VALUES (?,?,?)");
        $now = gmdate('Y-m-d H:i:s');
        for ($i = 0; $i < 8; $i++) {
            $code_str = '';
            for ($j = 0; $j < 6; $j++) $code_str .= $chars[random_int(0, strlen($chars) - 1)];
            $code_str .= '-';
            for ($j = 0; $j < 6; $j++) $code_str .= $chars[random_int(0, strlen($chars) - 1)];
            $plainCodes[] = $code_str;
            $insStmt->execute([$uid, hash('sha256', $code_str), $now]);
        }

        unset($_SESSION['new_totp'], $_SESSION['setup_2fa'], $_SESSION['pending_uid']);
        $u = user_by_id($uid);
        complete_login($u);
        audit('2fa_enabled', $uid);
        $showRecoveryCodes = $plainCodes; // mostrar antes de redirigir
    }
}

render_header(t('setup.title'), true, 'minimal');
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', t('setup.title')]) ?>

<?php if ($showRecoveryCodes): ?>
    <div class="page-head">
        <h1>Guarda tus códigos de recuperación</h1>
        <p>Estos códigos te permiten acceder si pierdes el dispositivo 2FA. <strong>Solo se muestran una vez.</strong></p>
    </div>

    <div class="card" style="border-color:var(--warn-border,#f59e0b);background:var(--warn-bg,#fffbeb)">
        <div class="alert warn" style="margin-bottom:16px"><div>
            <strong>Guárdalos ahora.</strong> Cada código solo puede usarse una vez y no podrás volver a verlos.
        </div></div>

        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:14px 0">
            <?php foreach ($showRecoveryCodes as $rc): ?>
            <div style="font-family:var(--font-mono);font-size:1.05rem;letter-spacing:2px;background:var(--n-50,#f9fafb);border:1px solid var(--border);padding:10px 14px;border-radius:8px;text-align:center">
                <?= h($rc) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="small muted" style="margin:14px 0 0">
            Si pierdes el acceso al autenticador, introduce uno de estos códigos en la pantalla de inicio de sesión.
            Cada código desaparece tras ser usado.
        </p>
    </div>

    <div class="btn-row" style="margin-top:16px">
        <a class="btn dark" href="/admin/dashboard.php">Continuar al panel &rarr;</a>
    </div>

<?php else: ?>
    <div class="page-head">
        <h1><?= h(t('setup.title')) ?></h1>
        <p><?= h(t('setup.sub')) ?></p>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="card">
        <div class="section-title"><?= h(t('setup.step1')) ?></div>
        <p class="small muted"><?= t('setup.step1_d') ?></p>

        <div style="display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap;margin:10px 0 14px">
            <div id="qrcode" data-uri="<?= h($uri) ?>" style="flex-shrink:0;width:180px;height:180px;background:var(--n-100);border-radius:10px;display:flex;align-items:center;justify-content:center">
                <span class="muted small">Generando QR…</span>
            </div>
            <div style="flex:1;min-width:200px">
                <p class="small muted" style="margin:0 0 8px">O introduce esta clave manualmente:</p>
                <div class="code-box" style="font-size:1.15rem;letter-spacing:4px"><?= h(chunk_split($secret, 4, ' ')) ?></div>
            </div>
        </div>

        <details style="margin:4px 0 14px">
            <summary class="small muted" style="cursor:pointer">URI <code>otpauth://</code></summary>
            <p class="small" style="word-break:break-all;margin-top:8px"><code><?= h($uri) ?></code></p>
            <p class="help">Emisor: <strong><?= h(empresa()) ?> Canal</strong> · <?= h((string)$u['email']) ?> · TOTP · SHA-1 · 6 · 30 s</p>
        </details>

        <div class="section-title"><?= h(t('setup.step2')) ?></div>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="code"><?= h(t('setup.code.label')) ?></label>
                <input class="otp-input" id="code" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus>
            </div>
            <div class="btn-row"><button class="btn block lg dark" type="submit"><?= h(t('setup.submit')) ?></button></div>
        </form>
    </div>
<?php endif; ?>
</main>
<?php render_footer(true); ?>
