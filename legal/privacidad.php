<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/legal_texts.php';

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
$emailc = $GLOBALS['CONFIG']['email_compliance'] ?? '';
$derechos = $cfg['dpo_email'] ?? $emailc;
$lang = current_lang();

$dpoBlock = '';
if (!empty($cfg['dpo_email'])) {
    $name = !empty($cfg['dpo_nombre']) ? (': ' . h((string)$cfg['dpo_nombre'])) : '';
    $title = $lang === 'en' ? 'Data Protection Officer (DPO)' : 'Delegado de Protección de Datos (DPO)';
    $msg = $lang === 'en'
        ? 'You may contact the DPO directly for any data protection matter.'
        : 'Puedes contactar directamente con el DPO para cualquier cuestión de protección de datos.';
    $dpoBlock = '<p><strong>' . $title . '</strong>' . $name . ' — <a href="mailto:' . h((string)$cfg['dpo_email']) . '">' . h((string)$cfg['dpo_email']) . '</a>. ' . $msg . '</p>';
}

$body = legal_render('privacy', [
    'EMP'      => h(empresa()),
    'COMPL'    => h($emailc),
    'DERECHOS' => h($derechos),
    'DPO_BLOCK'=> $dpoBlock,
]);

$titles = $lang === 'en'
    ? ['Privacy · GDPR', 'Data protection', 'Processing of data under GDPR (EU) 2016/679 and LOPDGDD 3/2018.']
    : ['Privacidad · RGPD', 'Protección de datos', 'Tratamiento de datos en cumplimiento del RGPD (UE) 2016/679 y la LOPDGDD 3/2018.'];
$tocT = $lang === 'en'
    ? ['Controller','Purpose','Legal basis','Categories of data','Recipients','Transfers','Retention','Security','Rights','Automated decisions']
    : ['Responsable','Finalidad','Base jurídica','Categorías','Destinatarios','Transferencias','Conservación','Seguridad','Derechos','Decisiones automatizadas'];

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
