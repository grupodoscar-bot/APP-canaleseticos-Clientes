<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();

// Filtros
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$where = []; $params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'fecha_recepcion >= ?'; $params[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'fecha_recepcion <= ?'; $params[] = $to . ' 23:59:59'; }

$sql = "SELECT * FROM libro_registro" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY fecha_recepcion DESC";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$total = count($rows);

// Export CSV
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="libro-registro-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Código', 'Categoría', 'Canal', 'Relación', 'Recepción', 'Acuse', 'Resolución', 'Estado final', 'Archivable desde', 'Hash']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['codigo_seguimiento'], $r['categoria'], $r['canal'], $r['relacion'], $r['fecha_recepcion'], $r['fecha_acuse'], $r['fecha_resolucion'], $r['estado_final'], $r['archivable_desde'], $r['hash_integridad']]);
    }
    fclose($out);
    audit('libro_registro_export', (int)$me['id'], null, ['total' => $total]);
    exit;
}

render_header(t('nav.record'), true);
?>
<main class="wide">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('nav.record')]) ?>

    <div class="page-head">
        <span class="pill"><?= h(t('book.pill')) ?></span>
        <h1 style="margin-top:10px"><?= h(t('book.title')) ?></h1>
        <p><?= t('book.sub') ?></p>
    </div>

    <div class="alert info"><div><?= t('book.info') ?></div></div>

    <div class="card card-compact">
        <form method="get" class="filter-bar">
            <div class="form-group" style="margin:0">
                <label><?= h(t('book.f.from')) ?></label>
                <input type="date" name="from" value="<?= h($from) ?>">
            </div>
            <div class="form-group" style="margin:0">
                <label><?= h(t('book.f.to')) ?></label>
                <input type="date" name="to" value="<?= h($to) ?>">
            </div>
            <div style="display:flex;gap:8px">
                <button class="btn secondary" type="submit"><?= h(t('book.f.filter')) ?></button>
                <a class="btn" href="?csv=1<?= $from?'&from='.h($from):'' ?><?= $to?'&to='.h($to):'' ?>"><?= h(t('book.f.csv')) ?></a>
            </div>
        </form>
    </div>

    <p class="small muted"><?= h(t('book.total')) ?>: <strong><?= $total ?></strong> <?= h(t('book.entries')) ?></p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th><?= h(t('book.col.id')) ?></th><th><?= h(t('book.col.code')) ?></th><th><?= h(t('book.col.cat')) ?></th><th><?= h(t('book.col.ch')) ?></th><th><?= h(t('book.col.rcv')) ?></th><th><?= h(t('book.col.ack')) ?></th><th><?= h(t('book.col.res')) ?></th><th><?= h(t('book.col.state')) ?></th><th><?= h(t('book.col.arch')) ?></th><th><?= h(t('book.col.hash')) ?></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="center muted"><?= h(t('book.empty')) ?></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="small muted"><?= (int)$r['id'] ?></td>
                    <td><code><?= h($r['codigo_seguimiento']) ?></code></td>
                    <td class="small"><?= h(t('cat.'.$r['categoria'])) ?></td>
                    <td><span class="badge"><?= h((string)$r['canal']) ?></span></td>
                    <td class="small muted"><?= h(substr((string)$r['fecha_recepcion'], 0, 10)) ?></td>
                    <td class="small muted"><?= h(substr((string)($r['fecha_acuse'] ?? ''), 0, 10)) ?: '<span class="badge overdue">—</span>' ?></td>
                    <td class="small muted"><?= h(substr((string)($r['fecha_resolucion'] ?? ''), 0, 10)) ?: '<span class="muted">—</span>' ?></td>
                    <td><?= $r['estado_final'] ? '<span class="badge '.h((string)$r['estado_final']).'">'.h(t('st.'.$r['estado_final'])).'</span>' : '<span class="muted">—</span>' ?></td>
                    <td class="small muted"><?= h(substr((string)$r['archivable_desde'], 0, 10)) ?></td>
                    <td class="small muted" title="<?= h((string)$r['hash_integridad']) ?>"><code><?= h(substr((string)$r['hash_integridad'], 0, 10)) ?>…</code></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php render_footer(true); ?>
