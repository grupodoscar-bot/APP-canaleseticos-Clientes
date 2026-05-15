<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/reporter_auth.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/mailer.php';

$rep = require_reporter();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT * FROM reports WHERE id=? AND reporter_id=? LIMIT 1");
$st->execute([$id, (int)$rep['id']]);
$r = $st->fetch();
if (!$r) { http_response_code(404); exit('Denuncia no encontrada'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'mensaje') {
    csrf_check();
    $body = trim((string)($_POST['body'] ?? ''));
    if (!$r['anonimizada'] && mb_strlen($body) >= 1 && mb_strlen($body) <= 10000) {
        $now = gmdate('Y-m-d H:i:s');
        $st = db()->prepare("INSERT INTO communications (report_id, sender, body_enc, created_at) VALUES (?, 'denunciante', ?, ?)");
        $st->execute([(int)$r['id'], enc($body), $now]);
        db()->prepare("UPDATE reports SET updated_at=? WHERE id=?")->execute([$now, (int)$r['id']]);
        audit('message_from_reporter', null, (int)$r['id']);
        notify_new_message_admin((string)$r['codigo_seguimiento']);
    }
    header('Location: /mi-denuncia.php?id=' . (int)$r['id']); exit;
}

$msgs = db()->prepare("SELECT * FROM communications WHERE report_id=? ORDER BY created_at ASC");
$msgs->execute([(int)$r['id']]);
$msgs = $msgs->fetchAll();
db()->prepare("UPDATE communications SET read_at=? WHERE report_id=? AND sender='admin' AND read_at IS NULL")
    ->execute([gmdate('Y-m-d H:i:s'), (int)$r['id']]);

render_header(t('rep.det.title'));
?>
<main class="mid" style="max-width:90%">
    <?= breadcrumb([t('nav.home') => '/index.php', t('rep.panel.title') => '/mis-denuncias.php', t('rep.det.bc')]) ?>

    <div class="card">
        <div class="space-between">
            <div>
                <div class="small muted" style="margin-bottom:4px"><?= h(t('track.d.code')) ?></div>
                <div style="font-family:var(--font-mono);letter-spacing:2px;font-size:1.25rem;font-weight:600"><?= h((string)$r['codigo_seguimiento']) ?></div>
            </div>
            <span class="badge dot <?= h((string)$r['estado']) ?>"><?= h(t('st.'.$r['estado'])) ?></span>
        </div>

        <hr>

        <div class="kv">
            <div><?= h(t('track.d.date')) ?></div><div><?= h((string)$r['created_at']) ?> UTC</div>
            <div><?= h(t('track.d.ack')) ?></div><div><?= $r['acknowledged_at'] ? h((string)$r['acknowledged_at']) . ' UTC' : '<span class="badge overdue">'.h(t('track.d.pending')).'</span>' ?></div>
            <div><?= h(t('case.kv.category')) ?></div><div><?= h(t('cat.'.$r['categoria'])) ?></div>
            <div><?= h(t('track.d.title')) ?></div><div><?= $r['anonimizada'] ? '<em class="muted">'.h(t('track.d.anon')).'</em>' : h((string)dec((string)$r['titulo_enc'])) ?></div>
        </div>

        <div class="btn-row">
            <a class="btn ghost" href="/mis-denuncias.php"><?= h(t('rep.det.back')) ?></a>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('rep.det.section.chat')) ?></h2>
        <?php if (!$msgs): ?>
            <div class="empty"><div class="empty-icon">💬</div><p><?= h(t('track.chat.empty')) ?></p></div>
        <?php else: ?>
        <div class="chat">
            <?php foreach ($msgs as $m): $body = dec((string)$m['body_enc']); ?>
                <div class="msg <?= h((string)$m['sender']) ?>">
                    <?= nl2br(h((string)$body)) ?>
                    <div class="meta"><?= h(t($m['sender'] === 'admin' ? 'track.chat.mgr' : 'track.chat.you')) ?> · <?= h((string)$m['created_at']) ?> UTC</div>
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
</main>
<?php render_footer(); ?>
