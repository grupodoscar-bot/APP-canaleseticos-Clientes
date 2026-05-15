<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();
require_admin($me);

$CONFIRM_WORD = t('reset.confirm_word', 'BORRAR TODO');
$errors = []; $done = false; $report = [];

function wipe_dir_contents(string $dir, array $keep = []): array {
    $deleted = [];
    if (!is_dir($dir)) return $deleted;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (in_array($f, $keep, true)) continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $sub) {
                $sub->isDir() ? @rmdir($sub->getRealPath()) : @unlink($sub->getRealPath());
            }
            @rmdir($p);
        } else {
            @unlink($p);
        }
        $deleted[] = $f;
    }
    return $deleted;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $word = trim((string)($_POST['confirm'] ?? ''));
    if ($word !== $CONFIRM_WORD) {
        $errors[] = t('reset.err.word') . ' ' . $CONFIRM_WORD;
    } else {
        audit('factory_reset', (int)$me['id']);

        $report['uploads_borrados'] = wipe_dir_contents(APP_ROOT . '/uploads', ['.htaccess', 'index.html']);

        foreach (glob(APP_ROOT . '/assets/branding/logo.*') ?: [] as $oldLogo) @unlink($oldLogo);

        $report['bd_borradas'] = [];
        foreach (['canal.sqlite', 'canal.sqlite-wal', 'canal.sqlite-shm', 'canal.sqlite-journal', 'stripe_debug.log'] as $f) {
            $p = APP_ROOT . '/private/' . $f;
            if (is_file($p) && @unlink($p)) $report['bd_borradas'][] = $f;
        }

        $report['config_borrado'] = is_file(APP_ROOT . '/config.php') && @unlink(APP_ROOT . '/config.php');

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        $done = true;
    }
}

render_header(t('reset.title'), true);
?>
<main class="mid">
<?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('nav.settings') => '/admin/ajustes.php', t('reset.title')]) ?>

<?php if ($done): ?>
    <div class="card card-accent">
        <div class="alert ok"><div><strong><?= h(t('reset.ok.title')) ?>.</strong> <?= h(t('reset.ok.body')) ?></div></div>

        <h2><?= h(t('reset.ok.summary')) ?></h2>
        <ul class="clean">
            <li><span><?= $report['config_borrado'] ? '✓' : '—' ?></span><span><code>config.php</code> <?= h(t($report['config_borrado'] ? 'reset.ok.config' : 'reset.ok.config_no')) ?></span></li>
            <li><span>✓</span><span><?= h(t('reset.ok.db')) ?>: <?= $report['bd_borradas'] ? implode(', ', $report['bd_borradas']) : '—' ?></span></li>
            <li><span>✓</span><span><?= h(t('reset.ok.files')) ?>: <?= count($report['uploads_borrados']) ?> <?= h(t('reset.ok.files.u')) ?></span></li>
        </ul>

        <?php if (is_file(APP_ROOT . '/install.php')): ?>
            <div class="btn-row"><a class="btn" href="/install.php"><?= h(t('reset.ok.goinstall')) ?></a></div>
        <?php else: ?>
            <div class="alert warn"><div><?= t('reset.ok.missing') ?></div></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card" style="border-color:var(--err-border)">
        <h1 style="color:#6e1b1b"><?= h(t('reset.title')) ?></h1>
        <p class="muted"><?= h(t('reset.sub')) ?></p>

        <div class="alert err">
            <div>
                <?= h(t('reset.danger')) ?>
                <ul>
                    <li><?= t('reset.danger.1') ?></li>
                    <li><?= t('reset.danger.2') ?></li>
                    <li><?= t('reset.danger.3') ?></li>
                    <li><?= t('reset.danger.4') ?></li>
                    <li><?= t('reset.danger.5') ?></li>
                </ul>
            </div>
        </div>

        <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

        <form method="post" autocomplete="off" data-confirm="<?= h(t('reset.btn.confirm')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label><?= h(t('reset.confirm.label')) ?> <code><?= h($CONFIRM_WORD) ?></code></label>
                <input name="confirm" required autocomplete="off" placeholder="<?= h($CONFIRM_WORD) ?>" style="letter-spacing:2px;font-weight:600;font-family:var(--font-mono)">
            </div>
            <div class="btn-row">
                <button class="btn danger" type="submit"><?= h(t('reset.btn')) ?></button>
                <a class="btn ghost" href="/admin/ajustes.php"><?= h(t('common.cancel')) ?></a>
            </div>
        </form>

        <p class="help" style="margin-top:14px"><?= h(t('reset.session')) ?>: <strong><?= h((string)$me['email']) ?></strong>. <?= h(t('reset.session.end')) ?></p>
    </div>
<?php endif; ?>
</main>
<?php render_footer(true); ?>
