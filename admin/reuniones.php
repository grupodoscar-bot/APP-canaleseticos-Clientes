<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();

$errors = []; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $rid = (int)($_POST['id'] ?? 0);
    $now = gmdate('Y-m-d H:i:s');

    if ($act === 'agendar') {
        $fecha = trim((string)($_POST['fecha_agenda'] ?? ''));
        $notas = trim((string)($_POST['notas'] ?? ''));
        if ($fecha === '') { $errors[] = t('mgt.err.date'); }
        else {
            db()->prepare("UPDATE reunion_requests SET estado='agendada', fecha_agenda=?, notas_gestor_enc=?, updated_at=? WHERE id=?")
                ->execute([$fecha, enc($notas), $now, $rid]);
            audit('meeting_scheduled', (int)$me['id'], null, ['rid' => $rid]);
            $ok = t('mgt.ok.scheduled');
        }
    } elseif (in_array($act, ['celebrada','cancelada'], true)) {
        db()->prepare("UPDATE reunion_requests SET estado=?, updated_at=? WHERE id=?")
            ->execute([$act, $now, $rid]);
        audit('meeting_' . $act, (int)$me['id'], null, ['rid' => $rid]);
        $ok = t('mgt.ok.updated');
    }
}

$rows = db()->query("SELECT * FROM reunion_requests ORDER BY
    CASE estado WHEN 'pendiente' THEN 0 WHEN 'agendada' THEN 1 WHEN 'celebrada' THEN 2 ELSE 3 END,
    created_at DESC")->fetchAll();

$stateKey = ['pendiente'=>'mgt.st.pending','agendada'=>'mgt.st.scheduled','celebrada'=>'mgt.st.held','cancelada'=>'mgt.st.cancelled'];

render_header(t('nav.meetings'), true);
?>
<main class="wide">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('nav.meetings')]) ?>

    <div class="page-head">
        <h1><?= h(t('mgt.title')) ?></h1>
        <p><?= t('mgt.sub') ?></p>
    </div>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <?php if (!$rows): ?>
        <div class="card"><div class="empty"><div class="empty-icon">📅</div><p><?= h(t('mgt.empty')) ?></p></div></div>
    <?php else: foreach ($rows as $r):
        $age = (time() - strtotime($r['created_at'] . ' UTC')) / 86400;
        $overdue = $r['estado'] === 'pendiente' && $age > 7;
    ?>
        <div class="card">
            <div class="space-between">
                <div class="small muted"><?= h($r['created_at']) ?> UTC</div>
                <div>
                    <span class="badge <?= $r['estado']==='pendiente' && $overdue ? 'overdue' : '' ?>"><?= h(t($stateKey[$r['estado']] ?? 'mgt.st.pending')) ?></span>
                    <?php if ($overdue): ?> <span class="badge overdue">+<?= (int)$age ?>d</span><?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="kv">
                <div><?= h(t('mgt.kv.motive')) ?></div><div style="white-space:pre-wrap"><?= h((string)dec($r['motivo_enc'])) ?></div>
                <?php if ($r['contacto_enc']): ?><div><?= h(t('mgt.kv.contact')) ?></div><div><code><?= h((string)dec($r['contacto_enc'])) ?></code></div><?php endif; ?>
                <?php if ($r['preferencia_horario']): ?><div><?= h(t('mgt.kv.hours')) ?></div><div><?= h((string)$r['preferencia_horario']) ?></div><?php endif; ?>
                <?php if ($r['fecha_agenda']): ?><div><?= h(t('mgt.kv.scheduled')) ?></div><div><strong><?= h($r['fecha_agenda']) ?></strong></div><?php endif; ?>
                <?php if ($r['notas_gestor_enc']): ?><div><?= h(t('mgt.kv.notes')) ?></div><div style="white-space:pre-wrap"><?= h((string)dec($r['notas_gestor_enc'])) ?></div><?php endif; ?>
            </div>

            <?php if ($r['estado'] === 'pendiente'): ?>
            <hr>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="agendar">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label><?= h(t('mgt.f.date')) ?></label>
                        <input type="datetime-local" name="fecha_agenda" required>
                    </div>
                    <div class="form-group">
                        <label><?= h(t('mgt.f.notes')) ?> <small><?= h(t('mgt.f.notes.enc')) ?></small></label>
                        <input type="text" name="notas" placeholder="<?= h(t('mgt.f.notes.ph')) ?>">
                    </div>
                </div>
                <div class="btn-row"><button class="btn" type="submit"><?= h(t('mgt.btn.schedule')) ?></button></div>
            </form>
            <?php elseif ($r['estado'] === 'agendada'): ?>
            <div class="btn-row">
                <form method="post" style="display:inline" data-confirm="<?= h(t('mgt.confirm.held')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn secondary" name="action" value="celebrada" type="submit"><?= h(t('mgt.btn.held')) ?></button></form>
                <form method="post" style="display:inline" data-confirm="<?= h(t('mgt.confirm.cancel')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost" name="action" value="cancelada" type="submit"><?= h(t('mgt.btn.cancel')) ?></button></form>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</main>
<?php render_footer(true); ?>
