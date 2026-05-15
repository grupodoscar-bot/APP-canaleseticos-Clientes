<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/legal_texts.php';

$lang = current_lang();
$body = legal_render('cookies', []);

$titles = $lang === 'en'
    ? ['Cookie policy', 'LSSI-CE art. 22.2', 'Information on cookies under Law 34/2002 (LSSI-CE) and the 2024 AEPD cookie guide.']
    : ['Política de cookies', 'LSSI-CE art. 22.2', 'Información sobre el uso de cookies conforme a la Ley 34/2002 (LSSI-CE) y la Guía de la AEPD sobre cookies (ed. 2024).'];
$tocT = $lang === 'en'
    ? ['Cookies used','Technical detail','Cookies NOT used','Local storage','Changes']
    : ['Cookies utilizadas','Detalle técnico','Cookies NO utilizadas','Almacenamiento','Cambios'];

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
        </ol>
    </div>

    <article class="card prose" style="max-width:100%"><?= $body ?></article>
</main>
<?php render_footer(); ?>
