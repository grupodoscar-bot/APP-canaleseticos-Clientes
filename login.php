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
    $email = (string)($_POST['email'] ?? '');
    $pwd   = (string)($_POST['password'] ?? '');
    $r = reporter_try_login($email, $pwd);
    if (!$r['ok']) {
        $errors[] = $r['err'];
    } else {
        reporter_complete_login($r['reporter']);
        header('Location: ' . ($next !== '' ? $next : '/mis-denuncias.php')); exit;
    }
}

render_header(t('rep.login.title'));
?>
<main class="mid">
    <?= breadcrumb([t('nav.home') => '/index.php', t('rep.login.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('rep.login.title')) ?></h1>
        <p><?= h(t('rep.login.sub')) ?></p>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <?php if ($next): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>

        <div class="form-group">
            <label for="email"><?= h(t('rep.login.email')) ?></label>
            <input id="email" type="email" name="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="pwd"><?= h(t('rep.login.pwd')) ?></label>
            <input id="pwd" type="password" name="password" required>
        </div>

        <div class="btn-row"><button class="btn block lg dark" type="submit"><?= h(t('rep.login.submit')) ?></button></div>
    </form>

    <div class="card" style="margin-top:20px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--n-100);display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= ic('user', 22) ?></div>
            <div>
                <div style="font-weight:600;margin-bottom:2px"><?= h(t('rep.login.no_account')) ?></div>
                <div class="small muted"><?= h(t('rep.reg.sub')) ?></div>
            </div>
        </div>
        <a class="btn dark" href="/registro.php<?= $next ? '?next=' . h($next) : '' ?>"><?= h(t('rep.login.create')) ?> <?= ic('arrow', 16) ?></a>
    </div>
</main>
<?php render_footer(); ?>
