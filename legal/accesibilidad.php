<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/legal_texts.php';

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
$extra = $cfg['accesibilidad_extra'] ?? null;
$emailc = $GLOBALS['CONFIG']['email_compliance'] ?? '';
$tel = $cfg['telefono_canal'] ?? null;
$lang = current_lang();

$telLine = '';
if ($tel) {
    $lbl = $lang === 'en' ? 'Phone' : 'Teléfono';
    $telLine = '<li>' . $lbl . ': <a href="tel:' . h((string)$tel) . '">' . h((string)$tel) . '</a></li>';
}

$body = legal_render('accessibility', [
    'EMP'      => h(empresa()),
    'COMPL'    => h($emailc),
    'HOST'     => h($_SERVER['HTTP_HOST'] ?? ''),
    'DATE'     => date('Y-m-d'),
    'TEL_LINE' => $telLine,
]);

$titles = $lang === 'en'
    ? ['Accessibility statement', 'Digital accessibility', 'In compliance with RD 193/2023, RD 1112/2018, EN 301549 and WCAG 2.1 AA.']
    : ['Declaración de accesibilidad', 'Accesibilidad digital', 'En cumplimiento del RD 193/2023, RD 1112/2018, EN 301549 y WCAG 2.1 AA.'];
$tocT = $lang === 'en'
    ? ['Commitment','Scope','Status','Pending','Alternatives','Complaints']
    : ['Compromiso','Alcance','Estado','Pendiente','Alternativas','Quejas'];

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

    <article class="card prose" style="max-width:100%">
        <?= $body ?>
        <?php if ($extra): ?><hr><div><?= nl2br(h((string)$extra)) ?></div><?php endif; ?>
    </article>
</main>
<?php render_footer(); ?>
