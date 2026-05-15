<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';

$errors = [];
$stage = !empty($_SESSION['pending_uid']) ? '2fa' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $r = try_login($email, $pass);
        if (!$r['ok']) {
            $errors[] = $r['err'];
        } else {
            $u = $r['user'];
            if ((int)$u['totp_enabled'] === 1 && !empty($u['totp_secret'])) {
                $_SESSION['pending_uid'] = (int)$u['id'];
                $stage = '2fa';
            } else {
                $_SESSION['pending_uid'] = (int)$u['id'];
                $_SESSION['setup_2fa'] = true;
                header('Location: /admin/setup_2fa.php'); exit;
            }
        }
    } elseif ($action === 'totp') {
        $uid  = (int)($_SESSION['pending_uid'] ?? 0);
        $code = (string)($_POST['code'] ?? '');
        $u    = $uid ? user_by_id($uid) : null;
        if (!$u || !totp_verify((string)$u['totp_secret'], $code)) {
            $errors[] = 'Código 2FA incorrecto.';
            $stage = '2fa';
        } else {
            unset($_SESSION['pending_uid']);
            complete_login($u);
            header('Location: /admin/dashboard.php'); exit;
        }
    } elseif ($action === 'recovery_code') {
        $uid      = (int)($_SESSION['pending_uid'] ?? 0);
        $codeRaw  = strtoupper(trim((string)($_POST['recovery_code'] ?? '')));
        $u        = $uid ? user_by_id($uid) : null;
        if (!$u) {
            $errors[] = 'Sesión expirada. Vuelve a iniciar sesión.';
            $stage = 'login';
        } else {
            $codeHash = hash('sha256', $codeRaw);
            $row = db()->prepare(
                "SELECT id FROM totp_recovery_codes WHERE user_id=? AND code_hash=? AND used_at IS NULL LIMIT 1"
            );
            $row->execute([$uid, $codeHash]);
            $found = $row->fetch();
            if (!$found) {
                $errors[] = 'Código de recuperación incorrecto o ya utilizado.';
                $stage = '2fa';
            } else {
                db()->prepare("UPDATE totp_recovery_codes SET used_at=? WHERE id=?")
                    ->execute([gmdate('Y-m-d H:i:s'), (int)$found['id']]);
                audit('2fa_recovery_code_used', $uid);
                unset($_SESSION['pending_uid']);
                complete_login($u);
                header('Location: /admin/dashboard.php'); exit;
            }
        }
    } elseif ($action === 'cancel') {
        unset($_SESSION['pending_uid'], $_SESSION['setup_2fa']);
        $stage = 'login';
    }
}

render_header(t('login.title'), true, 'minimal');
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', t('login.title')]) ?>

    <div class="page-head">
        <h1><?= h(t($stage === '2fa' ? '2fa.title' : 'login.title')) ?></h1>
        <p><?= h(t($stage === '2fa' ? '2fa.sub' : 'login.sub')) ?></p>
    </div>

    <?php if (!empty($_GET['expired'])): ?><div class="alert warn"><div><?= h(t('login.expired')) ?></div></div><?php endif; ?>
    <?php if (!empty($_GET['reset'])): ?><div class="alert ok"><div>Contraseña actualizada correctamente. Ya puedes iniciar sesión.</div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="action-card neutral" style="margin:0 0 20px;flex-direction:row;align-items:stretch;gap:0;padding:0;overflow:hidden">
        <div style="flex:1;padding:34px">
            <h2 style="font-size:1.1rem;margin:0 0 4px"><?= h(t('login.how.title')) ?></h2>
            <ol class="steps" style="margin:0">
                <li><?= h(t('login.how.s1')) ?></li>
                <li><?= h(t('login.how.s2')) ?></li>
                <li><?= h(t('login.how.s3')) ?></li>
            </ol>
        </div>
        <div style="width:380px;flex-shrink:0;position:relative">
            <img src="/assets/photo/foto2.png" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block">
        </div>
    </div>

    <?php if ($stage === 'login'): ?>
    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label for="email"><?= h(t('login.f.email')) ?></label>
            <input id="email" type="email" name="email" required autofocus>
        </div>
        <div class="form-group">
            <label for="pwd"><?= h(t('login.f.pwd')) ?></label>
            <input id="pwd" type="password" name="password" required>
        </div>
        <div class="btn-row"><button class="btn block lg dark" type="submit"><?= h(t('login.submit')) ?></button></div>
        <div style="text-align:center;margin-top:12px">
            <a class="small muted" href="/admin/forgot.php">¿Olvidaste tu contraseña?</a>
        </div>
    </form>
    <?php else: ?>
    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="totp">
        <div class="form-group">
            <label for="code"><?= h(t('2fa.f.code')) ?></label>
            <input class="otp-input" id="code" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus>
        </div>
        <div class="btn-row" style="gap:8px">
            <button class="btn block lg dark" type="submit"><?= h(t('2fa.submit')) ?></button>
        </div>
        <div class="btn-row" style="justify-content:center;margin-top:8px">
            <button class="btn link" type="submit" name="action" value="cancel"><?= h(t('2fa.back')) ?></button>
        </div>
    </form>

    <details class="card" style="margin-top:14px;cursor:pointer">
        <summary style="font-weight:600;padding:0">¿No puedes usar el autenticador? Usa un código de recuperación</summary>
        <form method="post" autocomplete="off" style="margin-top:16px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="recovery_code">
            <div class="form-group">
                <label for="recovery_code">Código de recuperación</label>
                <input id="recovery_code" name="recovery_code" required
                       placeholder="XXXXXX-XXXXXX"
                       style="font-family:var(--font-mono);letter-spacing:2px;text-transform:uppercase">
                <p class="help">Introduce uno de los 8 códigos que guardaste al configurar el 2FA.</p>
            </div>
            <div class="btn-row">
                <button class="btn secondary" type="submit">Verificar código de recuperación</button>
            </div>
        </form>
    </details>
    <?php endif; ?>
</main>
<?php render_footer(true); ?>
