<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();
require_admin($me);

$errors = []; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $now = gmdate('Y-m-d H:i:s');

    if ($act === 'create') {
        $fd = trim((string)($_POST['fecha_deteccion'] ?? ''));
        $tp = trim((string)($_POST['tipo'] ?? ''));
        $ds = trim((string)($_POST['descripcion'] ?? ''));
        $da = trim((string)($_POST['datos_afectados'] ?? ''));
        $pa = (int)($_POST['personas_afectadas'] ?? 0);
        $ma = trim((string)($_POST['medidas_adoptadas'] ?? ''));
        if ($fd === '' || $tp === '' || mb_strlen($ds) < 20) {
            $errors[] = t('inc.err.req');
        } else {
            $st = db()->prepare("INSERT INTO security_incidents (fecha_deteccion, tipo, descripcion, datos_afectados, personas_afectadas, medidas_adoptadas, user_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)");
            $st->execute([$fd, $tp, $ds, $da ?: null, $pa ?: null, $ma ?: null, (int)$me['id'], $now, $now]);
            audit('incident_created', (int)$me['id'], null, ['tipo' => $tp]);
            $ok = t('inc.ok.created');
        }
    } elseif ($act === 'notify_aepd') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE security_incidents SET notificado_aepd=1, fecha_notificacion_aepd=?, updated_at=? WHERE id=?")
            ->execute([$now, $now, $id]);
        audit('incident_aepd_notified', (int)$me['id'], null, ['id' => $id]);
        $ok = t('inc.ok.aepd');
    } elseif ($act === 'notify_users') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE security_incidents SET notificado_afectados=1, updated_at=? WHERE id=?")
            ->execute([$now, $id]);
        $ok = t('inc.ok.users');
    } elseif ($act === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("UPDATE security_incidents SET cerrado=1, updated_at=? WHERE id=?")
            ->execute([$now, $id]);
        audit('incident_closed', (int)$me['id'], null, ['id' => $id]);
        $ok = t('inc.ok.closed');
    }
}

$rows = db()->query("SELECT * FROM security_incidents ORDER BY cerrado ASC, fecha_deteccion DESC")->fetchAll();

$typeMap = [
    'acceso_no_autorizado'=>'inc.type.unauthorized',
    'fuga_datos'=>'inc.type.leak',
    'perdida_datos'=>'inc.type.loss',
    'alteracion'=>'inc.type.alter',
    'phishing'=>'inc.type.phishing',
    'malware'=>'inc.type.malware',
    'error_humano'=>'inc.type.human',
    'otros'=>'inc.type.other',
];

render_header(t('nav.incidents'), true);
?>
<main class="wide">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('nav.incidents')]) ?>

    <div class="page-head">
        <span class="pill"><?= h(t('inc.pill')) ?></span>
        <h1 style="margin-top:10px"><?= h(t('inc.title')) ?></h1>
        <p><?= t('inc.sub') ?></p>
    </div>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="card card-accent">
        <h2 style="margin-top:0"><?= h(t('inc.create')) ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('inc.f.date')) ?></label>
                    <input type="datetime-local" name="fecha_deteccion" required>
                </div>
                <div class="form-group">
                    <label><?= h(t('inc.f.type')) ?></label>
                    <select name="tipo" required>
                        <option value="">—</option>
                        <?php foreach ($typeMap as $k => $tk): ?>
                            <option value="<?= h($k) ?>"><?= h(t($tk)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><?= h(t('inc.f.desc')) ?></label>
                <textarea name="descripcion" required minlength="20"></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('inc.f.data')) ?></label>
                    <input type="text" name="datos_afectados" placeholder="<?= h(t('inc.f.data.ph')) ?>">
                </div>
                <div class="form-group">
                    <label><?= h(t('inc.f.people')) ?> <small><?= h(t('inc.f.people.hint')) ?></small></label>
                    <input type="number" name="personas_afectadas" min="0">
                </div>
            </div>
            <div class="form-group">
                <label><?= h(t('inc.f.measures')) ?></label>
                <textarea name="medidas_adoptadas" placeholder="<?= h(t('inc.f.measures.ph')) ?>"></textarea>
            </div>
            <div class="btn-row"><button class="btn" type="submit"><?= h(t('inc.btn.create')) ?></button></div>
        </form>
    </div>

    <h2><?= h(t('inc.registered')) ?></h2>

    <?php if (!$rows): ?>
        <div class="card"><div class="empty"><div class="empty-icon">✅</div><p><?= h(t('inc.empty')) ?></p></div></div>
    <?php else: foreach ($rows as $i):
        $hoursSince = (time() - strtotime($i['fecha_deteccion'] . ' UTC')) / 3600;
        $overdue = !$i['notificado_aepd'] && $hoursSince > 72;
    ?>
        <div class="card" <?= $i['cerrado'] ? 'style="opacity:.75"' : '' ?>>
            <div class="space-between">
                <div>
                    <div class="small muted">#<?= (int)$i['id'] ?> · <?= h(t($typeMap[$i['tipo']] ?? 'inc.type.other')) ?></div>
                    <strong><?= h($i['fecha_deteccion']) ?></strong>
                </div>
                <div>
                    <?php if ($i['cerrado']): ?><span class="badge resuelta"><?= h(t('inc.closed')) ?></span>
                    <?php elseif ($overdue): ?><span class="badge overdue"><?= h(t('inc.aepd_pending')) ?> · +<?= (int)$hoursSince ?>h</span>
                    <?php else: ?><span class="badge recibida"><?= h(t('inc.open')) ?> · <?= (int)$hoursSince ?>h</span>
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <div class="kv">
                <div><?= h(t('inc.kv.desc')) ?></div><div style="white-space:pre-wrap"><?= h($i['descripcion']) ?></div>
                <?php if ($i['datos_afectados']): ?><div><?= h(t('inc.kv.data')) ?></div><div><?= h($i['datos_afectados']) ?></div><?php endif; ?>
                <?php if ($i['personas_afectadas']): ?><div><?= h(t('inc.kv.people')) ?></div><div><?= (int)$i['personas_afectadas'] ?></div><?php endif; ?>
                <?php if ($i['medidas_adoptadas']): ?><div><?= h(t('inc.kv.measures')) ?></div><div style="white-space:pre-wrap"><?= h($i['medidas_adoptadas']) ?></div><?php endif; ?>
                <div><?= h(t('inc.kv.aepd')) ?></div><div><?= $i['notificado_aepd'] ? '✅ ' . h((string)$i['fecha_notificacion_aepd']) : '❌ ' . h(t('dash.pending')) ?></div>
                <div><?= h(t('inc.kv.users')) ?></div><div><?= $i['notificado_afectados'] ? '✅' : '❌' ?></div>
            </div>

            <?php if (!$i['cerrado']): ?>
            <div class="btn-row">
                <?php if (!$i['notificado_aepd']): ?>
                    <a class="btn" href="https://sedeagpd.gob.es/sede-electronica-web/" target="_blank" rel="noopener"><?= h(t('inc.aepd_go')) ?> ↗</a>
                    <form method="post" data-confirm="<?= h(t('inc.confirm.aepd')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="notify_aepd"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn secondary" type="submit"><?= h(t('inc.btn.aepd')) ?></button></form>
                <?php endif; ?>
                <?php if (!$i['notificado_afectados']): ?>
                    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="notify_users"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn secondary" type="submit"><?= h(t('inc.btn.users')) ?></button></form>
                <?php endif; ?>
                <form method="post" data-confirm="<?= h(t('inc.confirm.close')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn ghost" type="submit"><?= h(t('inc.btn.close')) ?></button></form>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>

    <div class="card" style="background:var(--n-25)">
        <h3 style="margin-top:0"><?= h(t('inc.template')) ?></h3>
        <p class="muted small"><?= h(t('inc.template.hint')) ?></p>
        <pre>1. Nature of the breach
2. Categories and approximate number of data subjects affected
3. Categories and approximate number of records affected
4. DPO or contact point
5. Likely consequences
6. Measures taken or proposed
7. Mitigation measures
8. If notification delayed beyond 72h: reasons for delay</pre>
    </div>
</main>
<?php render_footer(true); ?>
