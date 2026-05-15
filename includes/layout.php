<?php
function brand_logo_svg(): string {
    return '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true">
<path d="M12 2L3 6v6c0 5 3.8 9.3 9 10 5.2-.7 9-5 9-10V6l-9-4z" stroke="currentColor" stroke-width="2" fill="rgba(255,255,255,.14)"/>
<path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
}

function bc_arrow(): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>';
}

function ic(string $name, int $s = 20): string {
    $icons = [
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'eye-off'=> '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><path d="M1 1l22 22"/>',
        'scale' => '<path d="M12 3v18"/><path d="M3 7h18"/><path d="M3 7l3 7c.3 1 1 2 3 2s2.7-1 3-2L9 7"/><path d="M15 7l3 7c.3 1 1 2 3 2s2.7-1 3-2l-3-7"/>',
        'send'  => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        'search'=> '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'lock'  => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'arrow' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'key'   => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L21 2"/><path d="M17 6l4 4"/><path d="M15 8l4 4"/>',
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0114 0v1"/>',
        'list'  => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/>',
    ];
    return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}

function lang_switcher_inline(): string {
    $lang = current_lang();
    $url = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $es = $url . '?lang=es';
    $en = $url . '?lang=en';
    return '<div class="lang-switch" role="group" aria-label="' . h(t('lang.switch')) . '">'
        . '<a href="' . h($es) . '" class="' . ($lang==='es'?'active':'') . '">ES</a>'
        . '<a href="' . h($en) . '" class="' . ($lang==='en'?'active':'') . '">EN</a>'
        . '</div>';
}

function render_header(string $title, bool $admin = false, string $mode = 'auto'): void {
    $emp = empresa();
    $prefix = '/';
    if ($mode === 'auto') $mode = $admin ? 'admin' : 'public';
    $htmlLang = current_lang();
    $currentRep = null;
    if ($mode === 'public' && function_exists('reporter_current')) {
        $currentRep = reporter_current();
    }
    ?><!doctype html>
<html lang="<?= h($htmlLang) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · <?= h($emp) ?></title>
<link rel="stylesheet" href="<?= $prefix ?>assets/css/style.css">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#1f54c7">
<meta name="color-scheme" content="light">
</head><body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $prefix ?>index.php">
            <?php $logo = brand_logo_url(); if ($logo): ?>
                <img class="brand-logo-img" src="<?= h($logo) ?>" alt="<?= h($emp) ?>">
            <?php else: ?>
                <span class="brand-logo"><?= brand_logo_svg() ?></span>
            <?php endif; ?>
            <span>
                <?= h($emp) ?>
                <small>Canal Ético</small>
            </span>
        </a>

        <nav class="main-nav" aria-label="Principal">
        <?php if ($mode === 'admin'): ?>
            <a href="/admin/dashboard.php"><?= h(t('nav.panel')) ?></a>
            <a href="/admin/reuniones.php"><?= h(t('nav.meetings')) ?></a>
            <a href="/admin/libro-registro.php"><?= h(t('nav.record')) ?></a>
            <a href="/admin/incidentes.php"><?= h(t('nav.incidents')) ?></a>
            <a href="/admin/usuarios.php"><?= h(t('nav.users')) ?></a>
            <a href="/admin/ajustes.php"><?= h(t('nav.settings')) ?></a>
            <a href="/admin/licencia.php">Licencia</a>
            <a href="/admin/logout.php"><?= h(t('nav.logout')) ?></a>
        <?php elseif ($mode === 'minimal'): ?>
            <a href="/index.php"><?= h(t('common.back_home')) ?></a>
        <?php else: ?>
            <a href="/index.php"><?= h(t('nav.home')) ?></a>
            <a href="/seguimiento.php"><?= h(t('nav.status')) ?></a>
            <a href="/reunion.php"><?= h(t('nav.meeting')) ?></a>
            <?php if ($currentRep): ?>
                <a href="/mis-denuncias.php"><?= h(t('nav.myreports')) ?></a>
                <a href="/logout.php"><?= h(t('nav.logout')) ?></a>
            <?php else: ?>
                <a href="/login.php"><?= h(t('nav.login')) ?></a>
            <?php endif; ?>
            <a href="/denunciar.php" class="cta"><?= h(t('nav.report')) ?></a>
        <?php endif; ?>
        </nav>

        <div class="topbar-right">
            <?= lang_switcher_inline() ?>
            <?php if (is_file(APP_ROOT . '/assets/branding/logo_canal.png')): ?>
                <span class="topbar-divider" aria-hidden="true"></span>
                <img class="channel-logo" src="/assets/branding/logo_canal.png?v=<?= filemtime(APP_ROOT . '/assets/branding/logo_canal.png') ?>" alt="Canal Ético">
            <?php endif; ?>
        </div>
    </div>
</header>
<?php
    // Franja de aviso cuando el tenant está desactivado (visible en admin + público)
    if (function_exists('central_is_blocked') && central_is_blocked()) {
        ?>
        <div style="background:#b91c1c;color:#fff;padding:12px 24px;text-align:center;font-weight:600;font-size:.95rem;border-bottom:2px solid #7f1d1d;position:sticky;top:var(--header-h);z-index:40">
            ⚠ Canal desactivado por el operador del servicio. Los usuarios no pueden acceder. Entra en <a href="/admin/licencia.php" style="color:#fff;text-decoration:underline">Licencia</a> para refrescar el estado o contactar con soporte.
        </div>
        <?php
    }
}

function render_footer(bool $admin = false): void {
    ?>
<footer class="site-footer">
    <div class="inner">
        <div>
            <div class="legal-links">
                <a href="/legal/politica-canal.php"><?= h(t('footer.policy')) ?></a>
                <a href="/legal/privacidad.php"><?= h(t('footer.privacy')) ?></a>
                <a href="/legal/aviso-legal.php"><?= h(t('footer.legal')) ?></a>
                <a href="/legal/cookies.php"><?= h(t('footer.cookies')) ?></a>
                <a href="/legal/accesibilidad.php"><?= h(t('footer.accessibility')) ?></a>
                <?php if (!$admin): ?><a href="/admin/login.php"><?= h(t('nav.admin')) ?></a><?php endif; ?>
            </div>
            <div class="fine">&copy; <?= date('Y') ?> <?= h(empresa()) ?>. <?= h(t('footer.rights')) ?></div>
            <div class="compliance"><?= h(t('footer.compliance')) ?></div>
        </div>
        <div class="tech">
            <?= h(t('footer.tech1')) ?><br>
            <?= h(t('footer.tech2')) ?><br>
            <span class="compliance">Build v1.0</span>
        </div>
    </div>
</footer>
<script src="/assets/js/app.js" defer></script>
</body></html>
<?php
}

function breadcrumb(array $parts): string {
    $html = '<nav class="breadcrumb" aria-label="Breadcrumb">';
    $last = count($parts) - 1;
    $i = 0;
    foreach ($parts as $label => $url) {
        if (is_int($label)) { $label = $url; $url = null; }
        if ($url && $i !== $last) {
            $html .= '<a href="' . h($url) . '">' . h((string)$label) . '</a>';
        } else {
            $html .= '<span>' . h((string)$label) . '</span>';
        }
        if ($i !== $last) $html .= bc_arrow();
        $i++;
    }
    $html .= '</nav>';
    return $html;
}
