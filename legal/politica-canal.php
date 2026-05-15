<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/legal_texts.php';

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
$extra = $cfg['politica_extra'] ?? null;
$emailc = $GLOBALS['CONFIG']['email_compliance'] ?? '';
$tel    = $cfg['telefono_canal'] ?? null;

$lang = current_lang();
$telLine = '';
if ($tel) {
    $label = $lang === 'en' ? 'By phone' : 'Por teléfono';
    $telLine = '<li><strong>' . $label . '</strong>: <a href="tel:' . h((string)$tel) . '">' . h((string)$tel) . '</a></li>';
}
$dpoLine = '';
if (!empty($cfg['dpo_email'])) {
    $dpoName = !empty($cfg['dpo_nombre']) ? ' (' . h((string)$cfg['dpo_nombre']) . ')' : '';
    $dpoLabel = $lang === 'en' ? 'Data Protection Officer (DPO)' : 'Delegado de Protección de Datos (DPO)';
    $dpoLine = '<br>' . $dpoLabel . $dpoName . ': <a href="mailto:' . h((string)$cfg['dpo_email']) . '">' . h((string)$cfg['dpo_email']) . '</a>';
}

$body = legal_render('policy', [
    'EMP'       => h(empresa()),
    'DATE'      => date('Y-m-d'),
    'COMPL'     => h($emailc),
    'TEL_LINE'  => $telLine,
    'DPO_LINE'  => $dpoLine,
]);

$titles = $lang === 'en'
    ? ['Channel policy', 'Normative document', 'Conditions, guarantees and procedure under Law 2/2023 and Directive (EU) 2019/1937.']
    : ['Política del canal', 'Documento normativo', 'Condiciones, garantías y procedimiento conforme a la Ley 2/2023 y la Directiva (UE) 2019/1937.'];
$tocTitles = $lang === 'en'
    ? ['Purpose','Who may report','Information','Channels & format','Guarantees','Deadlines','System Manager','False reports','Retention','External channel · A.A.I.','Contact']
    : ['Finalidad','Personas','Información','Canales y formato','Garantías','Plazos','Responsable','Falsas','Conservación','A.A.I.','Contacto'];
$addHeading = $lang === 'en' ? 'Additional provisions' : 'Disposiciones adicionales';

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
            <?php foreach ($tocTitles as $i => $tt): ?><li><a href="#s<?= $i+1 ?>"><?= h($tt) ?></a></li><?php endforeach; ?>
            <?php if ($extra): ?><li><a href="#s12"><?= h($addHeading) ?></a></li><?php endif; ?>
        </ol>
    </div>

    <article class="card prose" style="max-width:100%">
        <?= $body ?>
        <?php if ($extra): ?>
            <hr>
            <h2 id="s12"><?= h($addHeading) ?></h2>
            <div><?= nl2br(h((string)$extra)) ?></div>
        <?php endif; ?>
    </article>
</main>
<?php render_footer(); ?>
