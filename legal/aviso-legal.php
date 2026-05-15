<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/legal_texts.php';

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
$extra = $cfg['aviso_legal_extra'] ?? null;
$emailc = $GLOBALS['CONFIG']['email_compliance'] ?? '';
$lang = current_lang();

$body = legal_render('notice', [
    'EMP'   => h(empresa()),
    'COMPL' => h($emailc),
    'HOST'  => h($_SERVER['HTTP_HOST'] ?? ''),
]);

$titles = $lang === 'en'
    ? ['Legal notice', 'Legal information', 'In compliance with Law 34/2002 (LSSI-CE) on information society services.']
    : ['Aviso legal', 'Información legal', 'En cumplimiento de la Ley 34/2002 (LSSI-CE) de servicios de la sociedad de la información.'];
$tocT = $lang === 'en'
    ? ['Ownership','Contact','Terms of use','IP rights','Liability','Applicable law']
    : ['Titularidad','Contacto','Condiciones','Propiedad intelectual','Responsabilidad','Ley aplicable'];
$addH = $lang === 'en' ? 'Additional provisions' : 'Disposiciones adicionales';

render_header($titles[0]);
?>
<main class="mid" style="max-width:90%">
    <?= breadcrumb([t('nav.home') => '/index.php', $titles[0]]) ?>

    <div class="page-head">
        <span class="pill"><?= h($titles[1]) ?></span>
        <h1 style="margin-top:10px"><?= h($titles[0]) ?></h1>
        <p><?= h($titles[2]) ?></p>
    </div>

    <div class="toc" style="position:static;margin-bottom:24px">
        <h4><?= $lang === 'en' ? 'Index' : 'Índice' ?></h4>
        <ol>
            <?php foreach ($tocT as $i => $tt): ?><li><a href="#s<?= $i+1 ?>"><?= h($tt) ?></a></li><?php endforeach; ?>
            <?php if ($extra): ?><li><a href="#s7"><?= h($addH) ?></a></li><?php endif; ?>
        </ol>
    </div>

    <article class="card prose" style="max-width:100%">
        <?= $body ?>
        <?php if ($extra): ?>
            <hr>
            <h2 id="s7"><?= h($addH) ?></h2>
            <div><?= nl2br(h((string)$extra)) ?></div>
        <?php endif; ?>
    </article>
</main>
<?php render_footer(); ?>
