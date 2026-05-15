<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/reporter_auth.php';
require __DIR__ . '/includes/layout.php';

if (reporter_current()) { header('Location: /mis-denuncias.php'); exit; }

$errors = [];
$next = isset($_GET['next']) ? (string)$_GET['next'] : '';
if ($next === '' && !empty($_POST['next'])) $next = (string)$_POST['next'];
if ($next !== '' && !str_starts_with($next, '/')) $next = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!public_rate_limit_check('registro', 3)) {
        $errors[] = 'Demasiados intentos de registro desde tu dirección IP. Espera una hora e inténtalo de nuevo.';
    } else {
        public_rate_limit_record('registro');
    }
    $email = trim((string)($_POST['email'] ?? ''));
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    $accept = !empty($_POST['accept']);

    if (!$accept) $errors[] = t('rep.reg.err.accept');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('rep.reg.err.email');
    if ($p1 !== $p2) $errors[] = t('rep.reg.err.mismatch');
    if (!$errors) {
        $r = reporter_register($email, $p1, $nombre);
        if (!$r['ok']) { $errors[] = $r['err']; }
        else {
            $u = reporter_by_id((int)$r['rid']);
            reporter_complete_login($u);
            header('Location: ' . ($next !== '' ? $next : '/mis-denuncias.php')); exit;
        }
    }
}

render_header(t('rep.reg.title'));
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', t('rep.reg.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('rep.reg.title')) ?></h1>
        <p><?= h(t('rep.reg.sub')) ?></p>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>

        <div class="form-group">
            <label for="email"><?= h(t('rep.reg.email')) ?></label>
            <input id="email" type="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="nombre"><?= h(t('rep.reg.nombre')) ?> <small><?= h(t('rep.reg.nombre.hint')) ?></small></label>
            <input id="nombre" type="text" name="nombre" maxlength="200" value="<?= h($_POST['nombre'] ?? '') ?>">
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label for="pwd"><?= h(t('rep.reg.pwd')) ?> <small><?= h(t('rep.reg.pwd.hint')) ?></small></label>
                <input id="pwd" type="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="pwd2"><?= h(t('rep.reg.pwd2')) ?></label>
                <input id="pwd2" type="password" name="password2" required minlength="8">
            </div>
        </div>

        <hr>

        <label class="check-inline">
            <input type="checkbox" name="accept" value="1" required>
            <span><?= t('rep.reg.accept') ?></span>
        </label>

        <div class="btn-row">
            <button class="btn lg dark" type="submit"><?= h(t('rep.reg.submit')) ?></button>
            <a class="btn ghost" href="/login.php<?= $next ? '?next=' . h($next) : '' ?>"><?= h(t('rep.reg.go_login')) ?></a>
        </div>
    </form>

    <div class="card" style="margin-top:20px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--n-100);display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= ic('lock', 22) ?></div>
            <div>
                <div style="font-weight:600;margin-bottom:2px"><?= h(t('rep.reg.have_account')) ?></div>
                <div class="small muted"><?= h(t('rep.login.sub')) ?></div>
            </div>
        </div>
        <a class="btn dark" href="/login.php<?= $next ? '?next=' . h($next) : '' ?>"><?= h(t('rep.reg.go_login')) ?> <?= ic('arrow', 16) ?></a>
    </div>
</main>
<?php render_footer(); ?>
