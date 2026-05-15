<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/mailer.php';

const SESSION_CODE_TTL = 3600;

$errors = [];

function session_code_valid(): bool {
    return !empty($_SESSION['report_code']) && !empty($_SESSION['report_ts']) && (time() - $_SESSION['report_ts']) < SESSION_CODE_TTL;
}

function load_report_by_code(string $code): ?array {
    $st = db()->prepare("SELECT * FROM reports WHERE codigo_seguimiento=? LIMIT 1");
    $st->execute([$code]);
    $r = $st->fetch();
    return $r ?: null;
}

if (isset($_GET['salir'])) {
    unset($_SESSION['report_code'], $_SESSION['report_ts']);
    header('Location: /seguimiento.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'consultar') {
    csrf_check();
    $cod = strtoupper(trim((string)($_POST['codigo'] ?? '')));
    $cod = preg_replace('/[^A-Z0-9]/', '', $cod);
    if (strlen($cod) === 12) $cod = substr($cod,0,4).'-'.substr($cod,4,4).'-'.substr($cod,8,4);
    $r = strlen($cod) === 14 ? load_report_by_code($cod) : null;
    if (!$r) {
        usleep(random_int(100000, 300000));
        $errors[] = t('track.invalid');
    } else {
        $_SESSION['report_code'] = $r['codigo_seguimiento'];
        $_SESSION['report_ts']   = time();
        audit('report_consulted', null, (int)$r['id']);
        header('Location: /seguimiento.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'mensaje' && session_code_valid()) {
    csrf_check();
    $r = load_report_by_code($_SESSION['report_code']);
    $body = trim((string)($_POST['body'] ?? ''));
    if ($r && !$r['anonimizada'] && mb_strlen($body) >= 1 && mb_strlen($body) <= 10000) {
        $now = gmdate('Y-m-d H:i:s');
        $st = db()->prepare("INSERT INTO communications (report_id, sender, body_enc, created_at) VALUES (?, 'denunciante', ?, ?)");
        $st->execute([$r['id'], enc($body), $now]);
        db()->prepare("UPDATE reports SET updated_at=? WHERE id=?")->execute([$now, $r['id']]);
        audit('message_from_reporter', null, (int)$r['id']);
        notify_new_message_admin($r['codigo_seguimiento']);
    }
    header('Location: /seguimiento.php'); exit;
}

render_header(t('nav.status'));
?>
<main class="mid">
<?php if (!session_code_valid()): ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('nav.status')]) ?>

    <div class="page-head">
        <h1><?= h(t('track.title')) ?></h1>
        <p><?= h(t('track.lead')) ?></p>
    </div>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="action-card neutral" style="margin:0 0 20px;flex-direction:row;align-items:stretch;gap:0;padding:0;overflow:hidden">
        <div style="flex:1;padding:34px">
            <h2 style="font-size:1.1rem;margin:0 0 4px"><?= h(t('track.how.title')) ?></h2>
            <ol class="steps" style="margin:0">
                <li><?= h(t('track.how.s1')) ?></li>
                <li><?= h(t('track.how.s2')) ?></li>
                <li><?= h(t('track.how.s3')) ?></li>
            </ol>
        </div>
        <div style="width:380px;flex-shrink:0;position:relative">
            <img src="/assets/photo/foto3.png" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block">
        </div>
    </div>

    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="consultar">
        <div class="form-group">
            <label for="codigo"><?= h(t('track.f.code')) ?></label>
            <input id="codigo" name="codigo" type="text" placeholder="XXXX-XXXX-XXXX" required maxlength="14" autocomplete="off"
                   style="text-transform:uppercase;letter-spacing:3px;font-size:1.1rem;font-family:var(--font-mono);font-weight:600">
            <p class="help"><?= h(t('track.f.help')) ?></p>
        </div>
        <div class="btn-row"><button class="btn lg dark" type="submit"><?= h(t('track.submit')) ?></button></div>
        <p class="small muted" style="margin:16px 0 0"><?= h(t('track.lost')) ?></p>
    </form>
<?php else:
    $r = load_report_by_code($_SESSION['report_code']);
    if (!$r) { unset($_SESSION['report_code']); header('Location: /seguimiento.php'); exit; }
    $msgs = db()->prepare("SELECT * FROM communications WHERE report_id=? ORDER BY created_at ASC");
    $msgs->execute([$r['id']]);
    $msgs = $msgs->fetchAll();
    db()->prepare("UPDATE communications SET read_at=? WHERE report_id=? AND sender='admin' AND read_at IS NULL")
        ->execute([gmdate('Y-m-d H:i:s'), $r['id']]);
?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('track.d.bc')]) ?>

    <div class="card">
        <div class="space-between">
            <div>
                <div class="small muted" style="margin-bottom:4px"><?= h(t('track.d.code')) ?></div>
                <div style="font-family:var(--font-mono);letter-spacing:2px;font-size:1.25rem;font-weight:600"><?= h($r['codigo_seguimiento']) ?></div>
            </div>
            <span class="badge dot <?= h($r['estado']) ?>"><?= h(t('st.'.$r['estado'])) ?></span>
        </div>

        <hr>

        <div class="kv">
            <div><?= h(t('track.d.date')) ?></div><div><?= h($r['created_at']) ?> UTC</div>
            <div><?= h(t('track.d.ack')) ?></div><div><?= $r['acknowledged_at'] ? h($r['acknowledged_at']) . ' UTC' : '<span class="badge overdue">'.h(t('track.d.pending')).'</span>' ?></div>
            <div><?= h(t('track.d.title')) ?></div><div><?= $r['anonimizada'] ? '<em class="muted">'.h(t('track.d.anon')).'</em>' : h(dec($r['titulo_enc'])) ?></div>
        </div>

        <div class="btn-row">
            <a class="btn dark" href="/seguimiento.php?salir=1"><?= h(t('track.d.close')) ?></a>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('track.chat.title')) ?></h2>
        <?php if (!$msgs): ?>
            <div class="empty"><div class="empty-icon">💬</div><p><?= h(t('track.chat.empty')) ?></p></div>
        <?php else: ?>
        <div class="chat">
            <?php foreach ($msgs as $m): $body = dec($m['body_enc']); ?>
                <div class="msg <?= h($m['sender']) ?>">
                    <?= nl2br(h((string)$body)) ?>
                    <div class="meta"><?= h(t($m['sender'] === 'admin' ? 'track.chat.mgr' : 'track.chat.you')) ?> · <?= h($m['created_at']) ?> UTC</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!$r['anonimizada'] && !in_array($r['estado'], ['resuelta','desestimada','bloqueada'], true)): ?>
        <form method="post" style="margin-top:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="mensaje">
            <label for="body"><?= h(t('track.chat.label')) ?></label>
            <textarea id="body" name="body" required minlength="1" maxlength="10000" placeholder="<?= h(t('track.chat.ph')) ?>"></textarea>
            <div class="btn-row"><button class="btn dark" type="submit"><?= h(t('track.chat.send')) ?></button></div>
        </form>
        <?php else: ?>
            <div class="alert info"><div><?= h(t('track.closed')) ?></div></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</main>
<?php render_footer(); ?>
