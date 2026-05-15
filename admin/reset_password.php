<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';

// Si ya está autenticado, redirigir al panel
if (!empty($_SESSION['uid'])) { header('Location: /admin/dashboard.php'); exit; }

$token    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$errors   = [];
$user     = null;
$tokenOk  = false;

if ($token !== '') {
    $hash = hash('sha256', $token);
    $st   = db()->prepare("SELECT * FROM users WHERE reset_token_hash=? AND reset_token_expires > ? AND activo=1 LIMIT 1");
    $st->execute([$hash, gmdate('Y-m-d H:i:s')]);
    $user = $st->fetch() ?: null;
    $tokenOk = ($user !== null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenOk) {
    csrf_check();
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');

    if (strlen($p1) < 12) {
        $errors[] = 'La contraseña debe tener al menos 12 caracteres.';
    } elseif ($p1 !== $p2) {
        $errors[] = 'Las contraseñas no coinciden.';
    } else {
        $hash_pass = password_hash($p1, PASSWORD_ARGON2ID);
        db()->prepare("UPDATE users SET pass_hash=?, reset_token_hash=NULL, reset_token_expires=NULL, failed_logins=0, locked_until=NULL, updated_at=? WHERE id=?")
            ->execute([$hash_pass, gmdate('Y-m-d H:i:s'), (int)$user['id']]);
        audit('password_reset_completed', (int)$user['id']);
        header('Location: /admin/login.php?reset=1'); exit;
    }
}

render_header('Restablecer contraseña', true, 'minimal');
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', 'Iniciar sesión' => '/admin/login.php', 'Restablecer contraseña']) ?>

    <div class="page-head">
        <h1>Restablecer contraseña</h1>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <?php if (!$tokenOk): ?>
    <div class="card">
        <div class="alert err"><div>El enlace no es válido o ha caducado (validez: 1 hora).</div></div>
        <div class="btn-row" style="margin-top:16px">
            <a class="btn dark" href="/admin/forgot.php">Solicitar un nuevo enlace</a>
        </div>
    </div>
    <?php else: ?>
    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="form-group">
            <label for="pwd">Nueva contraseña <small>(mínimo 12 caracteres)</small></label>
            <input id="pwd" type="password" name="password" required minlength="12" autofocus autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="pwd2">Confirmar contraseña</label>
            <input id="pwd2" type="password" name="password2" required minlength="12" autocomplete="new-password">
        </div>
        <div class="btn-row">
            <button class="btn dark" type="submit">Restablecer contraseña</button>
        </div>
    </form>
    <?php endif; ?>
</main>
<?php render_footer(true); ?>
