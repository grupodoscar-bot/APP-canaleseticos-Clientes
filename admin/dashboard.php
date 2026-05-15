<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();

$filtro = $_GET['estado'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if (in_array($filtro, ['recibida','en_curso','resuelta','desestimada','bloqueada'], true)) {
    $where[] = 'estado = ?'; $params[] = $filtro;
}
if ($q !== '' && preg_match('/^[A-Z0-9\-]{4,14}$/i', $q)) {
    $where[] = 'codigo_seguimiento = ?'; $params[] = strtoupper($q);
}
$sql = "SELECT r.*, (SELECT COUNT(*) FROM communications c WHERE c.report_id=r.id AND c.sender='denunciante' AND c.read_at IS NULL) AS unread
        FROM reports r" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY r.created_at DESC LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$counts = db()->query("SELECT estado, COUNT(*) c FROM reports GROUP BY estado")->fetchAll();
$cmap = []; foreach ($counts as $r) $cmap[$r['estado']] = (int)$r['c'];
$total = array_sum($cmap);

$overdue_count = (int)db()->query("SELECT COUNT(*) FROM reports WHERE acknowledged_at IS NULL AND datetime(created_at) < datetime('now','-7 days')")->fetchColumn();

render_header(t('nav.panel'), true);
?>
<main class="wide">
    <div class="page-head">
        <div class="space-between">
            <div>
                <h1 style="margin-bottom:6px"><?= h(t('dash.title')) ?></h1>
                <p><?= h(t('dash.session')) ?>: <strong><?= h((string)$me['email']) ?></strong> · <?= h(t('dash.role')) ?> <?= h((string)$me['role']) ?></p>
            </div>
        </div>
    </div>

    <div class="grid-4">
        <div class="stat"><div class="num"><?= $total ?></div><div class="lbl"><?= h(t('dash.stat.total')) ?></div></div>
        <div class="stat"><div class="num"><?= (int)($cmap['recibida'] ?? 0) ?></div><div class="lbl"><?= h(t('dash.stat.new')) ?></div></div>
        <div class="stat"><div class="num"><?= (int)($cmap['en_curso'] ?? 0) ?></div><div class="lbl"><?= h(t('dash.stat.open')) ?></div></div>
        <div class="stat <?= $overdue_count > 0 ? 'err' : 'ok' ?>"><div class="num"><?= $overdue_count ?></div><div class="lbl"><?= h(t('dash.stat.overdue')) ?></div></div>
    </div>

    <div class="card card-compact" style="margin-top:20px">
        <form method="get" class="filter-bar">
            <div class="form-group" style="margin:0">
                <label for="q"><?= h(t('dash.f.search')) ?></label>
                <input id="q" name="q" value="<?= h($q) ?>" placeholder="XXXX-XXXX-XXXX">
            </div>
            <div class="form-group" style="margin:0">
                <label for="estado"><?= h(t('dash.f.state')) ?></label>
                <select id="estado" name="estado">
                    <option value="all"><?= h(t('dash.f.all')) ?> (<?= $total ?>)</option>
                    <?php foreach (['recibida','en_curso','resuelta','desestimada','bloqueada'] as $k): ?>
                        <option value="<?= h($k) ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= h(t('st.'.$k)) ?> (<?= (int)($cmap[$k] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button class="btn secondary" type="submit"><?= h(t('dash.f.submit')) ?></button></div>
        </form>
    </div>

    <?php if (!$rows): ?>
        <div class="card">
            <div class="empty"><div class="empty-icon">📭</div><p><?= h(t('dash.empty')) ?></p></div>
        </div>
    <?php else: ?>
    <div style="margin-top:14px">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th><?= h(t('dash.col.code')) ?></th><th><?= h(t('dash.col.cat')) ?></th><th><?= h(t('dash.col.state')) ?></th><th><?= h(t('dash.col.recv')) ?></th><th><?= h(t('dash.col.ack')) ?></th><th><?= h(t('dash.col.msgs')) ?></th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $age_days = (time() - strtotime($r['created_at'] . ' UTC')) / 86400;
                    $overdue = empty($r['acknowledged_at']) && $age_days > 7;
                ?>
                    <tr>
                        <td><code><?= h($r['codigo_seguimiento']) ?></code></td>
                        <td><?= h(t('cat.'.$r['categoria'])) ?></td>
                        <td><span class="badge dot <?= h($r['estado']) ?>"><?= h(t('st.'.$r['estado'])) ?></span></td>
                        <td class="small muted"><?= h(substr($r['created_at'], 0, 10)) ?></td>
                        <td><?= $r['acknowledged_at']
                                ? '<span class="small muted">'.h(substr($r['acknowledged_at'], 0, 10)).'</span>'
                                : ($overdue
                                    ? '<span class="badge overdue">'.(int)$age_days.h(t('dash.days_no_ack')).'</span>'
                                    : '<span class="small muted">'.h(t('dash.pending')).'</span>') ?></td>
                        <td><?= (int)$r['unread'] > 0 ? '<span class="pill">'.(int)$r['unread'].' '.h(t('dash.new_msgs')).'</span>' : '<span class="small muted">—</span>' ?></td>
                        <td><a class="btn sm secondary" href="/admin/denuncia.php?id=<?= (int)$r['id'] ?>"><?= h(t('dash.open')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php render_footer(true); ?>
