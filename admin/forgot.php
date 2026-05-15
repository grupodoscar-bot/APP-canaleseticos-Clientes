<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/mailer.php';

// Si ya está autenticado, redirigir al panel
if (!empty($_SESSION['uid'])) { header('Location: /admin/dashboard.php'); exit; }

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Introduce un email válido.';
    } else {
        // Siempre mostramos "éxito" para no revelar si el email existe
        $u = user_by_email($email);
        if ($u && $u['activo']) {
            $token    = bin2hex(random_bytes(32));
            $hash     = hash('sha256', $token);
            $expires  = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hora

            db()->prepare("UPDATE users SET reset_token_hash=?, reset_token_expires=? WHERE id=?")
                ->execute([$hash, $expires, (int)$u['id']]);

            $link = app_url('admin/reset_password.php?token=' . $token);
            $emp  = empresa();
            send_mail(
                $email,
                '[Canal Ético] Recuperación de contraseña',
                "Hola,\n\n"
                . "Has solicitado restablecer tu contraseña en {$emp} Canal Ético.\n\n"
                . "Usa este enlace (válido 1 hora):\n{$link}\n\n"
                . "Si no solicitaste esto, ignora este mensaje.\n\n"
                . "Saludos,\n{$emp} — Canal Ético"
            );
            audit('password_reset_requested', (int)$u['id']);
        }
        $sent = true;
    }
}

render_header('Recuperar contraseña', true, 'minimal');
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', 'Iniciar sesión' => '/admin/login.php', 'Recuperar contraseña']) ?>

    <div class="page-head">
        <h1>Recuperar contraseña</h1>
        <p>Introduce tu email de administrador y te enviaremos un enlace para restablecer la contraseña.</p>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <?php if ($sent): ?>
    <div class="card">
        <div class="alert ok"><div>Si el email está registrado, recibirás un enlace en los próximos minutos.</div></div>
        <p class="muted" style="margin-top:10px">Revisa también la carpeta de spam. El enlace caduca en 1 hora.</p>
        <div class="btn-row" style="margin-top:16px">
            <a class="btn dark" href="/admin/login.php">Volver al inicio de sesión</a>
        </div>
    </div>
    <?php else: ?>
    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email de administrador</label>
            <input id="email" type="email" name="email" required autofocus>
        </div>
        <div class="btn-row">
            <button class="btn dark" type="submit">Enviar enlace de recuperación</button>
            <a class="btn ghost" href="/admin/login.php">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
</main>
<?php render_footer(true); ?>
