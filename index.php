<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

render_header(t('nav.report'));
?>

<section class="hero">
    <div class="hero-inner" style="max-width:none;width:100%;display:flex;align-items:center;padding:0 5%;gap:0;text-align:left">
    <div style="width:50%;flex-shrink:0;text-align:left">
        <span class="eyebrow"><?= h(t('home.eyebrow')) ?></span>
        <h1><?= h(t('home.title_pre')) ?> <em><?= h(t('home.title_em')) ?></em><br><?= h(t('home.title_post')) ?></h1>
        <p class="lead"><?= h(t('home.lead_pre')) ?> <strong><?= h(empresa()) ?></strong><?= h(t('home.lead_post')) ?></p>
        <div class="btn-row">
            <a class="btn dark lg" href="/denunciar.php"><?= ic('send', 18) ?> <?= h(t('home.cta.report')) ?></a>
            <a class="btn secondary lg" href="/seguimiento.php"><?= ic('search', 18) ?> <?= h(t('home.cta.consult')) ?></a>
        </div>
        <div class="hero-trust">
            <div><?= ic('clock', 16) ?> <strong><?= h(t('home.trust.days7')) ?></strong> <?= h(t('home.trust.ack')) ?></div>
            <div><?= ic('check', 16) ?> <strong><?= h(t('home.trust.months3')) ?></strong> <?= h(t('home.trust.resolve')) ?></div>
            <div><?= ic('lock', 16) ?> <strong>AES-256</strong> <?= h(t('home.trust.crypto')) ?></div>
            <div><?= ic('eye-off', 16) ?> <strong><?= h(t('home.trust.anon')) ?></strong></div>
        </div>
    </div>
    <div style="width:10%;flex-shrink:0"></div>
    <img src="/assets/photo/foto4.png" alt="" style="width:30%;flex-shrink:0;border-radius:14px;object-fit:cover;display:block">
    </div>
</section>

<section style="background:var(--n-200);padding:60px 0">
    <div class="wide" style="width:90%;margin:0 auto">
        <div class="section-heading" style="margin-top:0">
            <span class="eyebrow"><?= h(t('home.guarantees.eyebrow')) ?></span>
            <h2><?= h(t('home.guarantees.title')) ?></h2>
            <p><?= h(t('home.guarantees.lead')) ?></p>
        </div>
        <div class="grid-3">
            <div class="feature">
                <div class="ic"><?= ic('shield', 20) ?></div>
                <h3><?= h(t('home.feature.conf.title')) ?></h3>
                <p><?= h(t('home.feature.conf.body')) ?></p>
            </div>
            <div class="feature">
                <div class="ic"><?= ic('eye-off', 20) ?></div>
                <h3><?= h(t('home.feature.anon.title')) ?></h3>
                <p><?= h(t('home.feature.anon.body')) ?></p>
            </div>
            <div class="feature">
                <div class="ic"><?= ic('scale', 20) ?></div>
                <h3><?= h(t('home.feature.legal.title')) ?></h3>
                <p><?= h(t('home.feature.legal.body')) ?></p>
            </div>
        </div>
    </div>
</section>

<section style="background:var(--n-0);padding:60px 0">
    <div class="wide" style="width:90%;margin:0 auto">
        <div class="section-heading" style="margin-top:0">
            <span class="eyebrow"><?= h(t('home.proceed.eyebrow')) ?></span>
            <h2><?= h(t('home.proceed.title')) ?></h2>
            <p><?= h(t('home.proceed.lead')) ?></p>
        </div>
        <div class="grid-2">
            <div class="action-card neutral" style="background:var(--n-200);border-color:var(--n-200)">
                <div class="ic-lg"><?= ic('send', 26) ?></div>
                <h2><?= h(t('home.action.report.title')) ?></h2>
                <p><?= h(t('home.action.report.body')) ?></p>
                <ol class="steps">
                    <li><?= h(t('home.action.report.s1')) ?></li>
                    <li><?= h(t('home.action.report.s2')) ?></li>
                    <li><?= h(t('home.action.report.s3')) ?></li>
                </ol>
                <div class="btn-row"><a class="btn dark" href="/denunciar.php"><?= h(t('home.action.report.btn')) ?> <?= ic('arrow', 16) ?></a></div>
            </div>
            <div class="action-card neutral" style="background:var(--n-200);border-color:var(--n-200)">
                <div class="ic-lg"><?= ic('search', 26) ?></div>
                <h2><?= h(t('home.action.consult.title')) ?></h2>
                <p><?= h(t('home.action.consult.body')) ?></p>
                <ol class="steps">
                    <li><?= h(t('home.action.consult.s1')) ?></li>
                    <li><?= h(t('home.action.consult.s2')) ?></li>
                    <li><?= h(t('home.action.consult.s3')) ?></li>
                </ol>
                <div class="btn-row"><a class="btn dark" href="/seguimiento.php"><?= h(t('home.action.consult.btn')) ?> <?= ic('arrow', 16) ?></a></div>
            </div>
        </div>
    </div>
</section>

<section style="background:var(--n-200);padding:60px 0">
    <div class="wide" style="width:90%;margin:0 auto">
        <div class="section-heading" style="margin-top:0">
            <span class="eyebrow"><?= h(t('home.timeline.eyebrow')) ?></span>
            <h2><?= h(t('home.timeline.title')) ?></h2>
            <p><?= h(t('home.timeline.lead')) ?></p>
        </div>
        <div class="card">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;width:100%">
                <div style="display:flex;gap:10px;align-items:flex-start;padding:16px;background:var(--n-100);border-radius:10px">
                    <span style="flex-shrink:0;margin-top:2px"><?= ic('send', 16) ?></span>
                    <span><strong><?= h(t('home.timeline.s1.t')) ?></strong><br><span class="muted" style="font-size:.88rem"><?= h(t('home.timeline.s1.d')) ?></span></span>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-start;padding:16px;background:var(--n-100);border-radius:10px">
                    <span style="flex-shrink:0;margin-top:2px"><?= ic('check', 16) ?></span>
                    <span><strong><?= h(t('home.timeline.s2.t')) ?></strong><br><span class="muted" style="font-size:.88rem"><?= h(t('home.timeline.s2.d')) ?></span></span>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-start;padding:16px;background:var(--n-100);border-radius:10px">
                    <span style="flex-shrink:0;margin-top:2px"><?= ic('search', 16) ?></span>
                    <span><strong><?= h(t('home.timeline.s3.t')) ?></strong><br><span class="muted" style="font-size:.88rem"><?= h(t('home.timeline.s3.d')) ?></span></span>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-start;padding:16px;background:var(--n-100);border-radius:10px">
                    <span style="flex-shrink:0;margin-top:2px"><?= ic('scale', 16) ?></span>
                    <span><strong><?= h(t('home.timeline.s4.t')) ?></strong><br><span class="muted" style="font-size:.88rem"><?= h(t('home.timeline.s4.d')) ?></span></span>
                </div>
            </div>
            <div class="btn-row" style="margin-top:20px"><a class="btn dark" href="/legal/politica-canal.php"><?= h(t('home.timeline.btn')) ?></a></div>
        </div>
        <p class="small muted center" style="margin-top:24px;max-width:680px;margin-left:auto;margin-right:auto"><?= h(t('home.warning')) ?></p>
    </div>
</section>


<?php render_footer(); ?>
