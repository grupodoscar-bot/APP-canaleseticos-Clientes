<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/reporter_auth.php';
require __DIR__ . '/includes/layout.php';

$rep = require_reporter();
$nombre = $rep['nombre_enc'] ? (string)dec($rep['nombre_enc']) : '';

$st = db()->prepare("SELECT * FROM reports WHERE reporter_id = ? ORDER BY created_at DESC");
$st->execute([(int)$rep['id']]);
$reports = $st->fetchAll();

render_header(t('rep.panel.title'));
?>
<main class="mid" style="max-width:90%">
    <?= breadcrumb([t('nav.home') => '/index.php', t('rep.panel.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('rep.panel.title')) ?></h1>
        <p><?= h(t('rep.panel.lead')) ?></p>
    </div>

    <div class="grid-sidebar" style="align-items:start">
        <div>
            <div class="card">
                <div class="space-between" style="margin-bottom:14px">
                    <h2 style="margin:0;font-size:1.3rem"><?= h(t('rep.panel.title')) ?> <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($reports) ?> <?= h(count($reports) === 1 ? t('rep.panel.report') : t('rep.panel.reports')) ?>)</span></h2>
                    <a class="btn dark" href="/denunciar.php?modo=identificada"><?= h(t('rep.panel.new')) ?></a>
                </div>

                <?php if (!$reports): ?>
                    <div class="empty"><div class="empty-icon">📄</div><p><?= h(t('rep.panel.empty')) ?></p></div>
                <?php else: ?>
                    <table>
                        <thead><tr>
                            <th><?= h(t('rep.panel.col.code')) ?></th>
                            <th><?= h(t('rep.panel.col.cat')) ?></th>
                            <th><?= h(t('rep.panel.col.state')) ?></th>
                            <th><?= h(t('rep.panel.col.date')) ?></th>
                            <th style="text-align:right"><?= h(t('rep.panel.col.act')) ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><code><?= h($r['codigo_seguimiento']) ?></code></td>
                                <td><?= h(t('cat.'.$r['categoria'])) ?></td>
                                <td><span class="badge dot <?= h($r['estado']) ?>"><?= h(t('st.'.$r['estado'])) ?></span></td>
                                <td class="small muted"><?= h(substr((string)$r['created_at'], 0, 10)) ?></td>
                                <td style="text-align:right"><a class="btn sm dark" href="/mi-denuncia.php?id=<?= (int)$r['id'] ?>"><?= h(t('rep.panel.view')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <aside>
            <div class="card" style="position:sticky;top:calc(var(--header-h) + 18px)">
                <h3 style="margin-top:0"><?= h(t('rep.panel.account')) ?></h3>
                <div class="kv">
                    <div><?= h(t('rep.panel.hi')) ?></div><div><?= h($nombre !== '' ? $nombre : (string)$rep['email']) ?></div>
                    <div>Email</div><div><code><?= h((string)$rep['email']) ?></code></div>
                    <div><?= h(t('rep.panel.created')) ?></div><div class="small muted"><?= h((string)$rep['created_at']) ?></div>
                    <?php if ($rep['last_login']): ?>
                    <div><?= h(t('rep.panel.lastlogin')) ?></div><div class="small muted"><?= h((string)$rep['last_login']) ?></div>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="btn-row"><a class="btn block ghost" href="/logout.php"><?= h(t('rep.panel.logout')) ?></a></div>
            </div>
        </aside>
    </div>
</main>
<?php render_footer(); ?>
