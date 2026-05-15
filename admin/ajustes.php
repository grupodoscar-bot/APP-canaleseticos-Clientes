<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/retention.php';
require __DIR__ . '/../includes/mailer.php';
$me = require_auth();
require_admin($me);

$errors = []; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $empresa = trim((string)($_POST['empresa'] ?? ''));
        $emailc  = trim((string)($_POST['email_compliance'] ?? ''));
        $pol     = trim((string)($_POST['politica_extra'] ?? ''));
        $avis    = trim((string)($_POST['aviso_legal_extra'] ?? ''));
        $telC    = trim((string)($_POST['telefono_canal'] ?? ''));
        $presE   = trim((string)($_POST['presencial_email'] ?? ''));
        $dpoN    = trim((string)($_POST['dpo_nombre'] ?? ''));
        $dpoE    = trim((string)($_POST['dpo_email'] ?? ''));
        $accExt  = trim((string)($_POST['accesibilidad_extra'] ?? ''));
        $lang    = in_array(($_POST['idioma_default'] ?? 'es'), ['es','en'], true) ? $_POST['idioma_default'] : 'es';
        if ($empresa === '' || !filter_var($emailc, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('set.err.invalid');
        } elseif ($dpoE !== '' && !filter_var($dpoE, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('set.err.invalid');
        } else {
            db()->prepare("UPDATE tenants_config SET empresa=?, email_compliance=?, politica_extra=?, aviso_legal_extra=?, telefono_canal=?, presencial_email=?, dpo_nombre=?, dpo_email=?, accesibilidad_extra=?, idioma_default=?, updated_at=? WHERE id=1")
                ->execute([$empresa, $emailc, $pol ?: null, $avis ?: null, $telC ?: null, $presE ?: null, $dpoN ?: null, $dpoE ?: null, $accExt ?: null, $lang, gmdate('Y-m-d H:i:s')]);
            audit('config_updated', (int)$me['id']);
            $ok = t('set.ok.saved');
        }
    } elseif ($act === 'upload_doc') {
        $kind = $_POST['kind'] ?? '';
        if (!in_array($kind, ['dpia','rat'], true)) { $errors[] = 'Tipo inválido.'; }
        else {
            $f = $_FILES['doc'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Error al subir.'; }
            elseif ($f['size'] > 10*1024*1024) { $errors[] = 'Máximo 10 MB.'; }
            else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($f['tmp_name']);
                if ($mime !== 'application/pdf') { $errors[] = 'Solo se admite PDF.'; }
                else {
                    $prev = (string)(db()->query("SELECT {$kind}_path FROM tenants_config WHERE id=1")->fetchColumn() ?: '');
                    if ($prev) { @unlink(APP_ROOT . '/' . ltrim($prev, '/')); }
                    $name = $kind . '_' . bin2hex(random_bytes(8)) . '.pdf.enc';
                    enc_file($f['tmp_name'], APP_ROOT . '/uploads/' . $name);
                    $rel = 'uploads/' . $name;
                    $now2 = gmdate('Y-m-d H:i:s');
                    db()->prepare("UPDATE tenants_config SET {$kind}_path=?, {$kind}_fecha=?, updated_at=? WHERE id=1")
                        ->execute([$rel, $now2, $now2]);
                    audit('doc_uploaded', (int)$me['id'], null, ['kind' => $kind]);
                    $ok = strtoupper($kind) . ' ' . t('set.ok.doc');
                }
            }
        }
    } elseif ($act === 'remove_doc') {
        $kind = $_POST['kind'] ?? '';
        if (in_array($kind, ['dpia','rat'], true)) {
            $prev = (string)(db()->query("SELECT {$kind}_path FROM tenants_config WHERE id=1")->fetchColumn() ?: '');
            if ($prev) @unlink(APP_ROOT . '/' . ltrim($prev, '/'));
            db()->prepare("UPDATE tenants_config SET {$kind}_path=NULL, {$kind}_fecha=NULL, updated_at=? WHERE id=1")->execute([gmdate('Y-m-d H:i:s')]);
            audit('doc_removed', (int)$me['id'], null, ['kind' => $kind]);
            $ok = t('set.ok.doc_rm');
        }
    } elseif ($act === 'logo_upload') {
        $f = $_FILES['logo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'No se ha podido subir el archivo.';
        } elseif ($f['size'] > 1024*1024) {
            $errors[] = 'Máximo 1 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($f['tmp_name']);
            $allow = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/svg+xml'=>'svg'];
            if (!isset($allow[$mime])) {
                $errors[] = 'Formato no permitido. Usa PNG, JPG, WEBP o SVG.';
            } else {
                foreach (glob(APP_ROOT . '/assets/branding/logo.*') ?: [] as $old) @unlink($old);
                $ext = $allow[$mime];
                $dest = 'assets/branding/logo.' . $ext;
                $absDest = APP_ROOT . '/' . $dest;
                @mkdir(dirname($absDest), 0755, true);
                if (!move_uploaded_file($f['tmp_name'], $absDest)) {
                    $errors[] = 'No se pudo guardar el archivo (¿permisos de assets/branding/?).';
                } else {
                    @chmod($absDest, 0644);
                    db()->prepare("UPDATE tenants_config SET logo_path=?, updated_at=? WHERE id=1")
                        ->execute([$dest, gmdate('Y-m-d H:i:s')]);
                    audit('logo_uploaded', (int)$me['id'], null, ['mime'=>$mime,'size'=>(int)$f['size']]);
                    $ok = t('set.ok.logo');
                }
            }
        }
    } elseif ($act === 'logo_remove') {
        $rel = (string)(db()->query("SELECT logo_path FROM tenants_config WHERE id=1")->fetchColumn() ?: '');
        if ($rel) {
            $abs = APP_ROOT . '/' . ltrim($rel, '/');
            if (is_file($abs)) @unlink($abs);
        }
        db()->prepare("UPDATE tenants_config SET logo_path=NULL, updated_at=? WHERE id=1")->execute([gmdate('Y-m-d H:i:s')]);
        audit('logo_removed', (int)$me['id']);
        $ok = t('set.ok.logo_rm');
    } elseif ($act === 'save_smtp') {
        $smtpHost   = trim((string)($_POST['smtp_host'] ?? ''));
        $smtpPort   = max(1, min(65535, (int)($_POST['smtp_port'] ?? 587)));
        $smtpUser   = trim((string)($_POST['smtp_user'] ?? ''));
        $smtpPass   = (string)($_POST['smtp_pass'] ?? '');
        $smtpFrom   = trim((string)($_POST['smtp_from'] ?? ''));
        $smtpSecure = in_array(($_POST['smtp_secure'] ?? ''), ['tls','ssl','none'], true) ? $_POST['smtp_secure'] : 'tls';
        if ($smtpFrom !== '' && !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'La dirección "De:" no es válida.';
        } else {
            // Mantener contraseña existente si no se introduce una nueva
            if ($smtpPass !== '') {
                $passEnc = enc($smtpPass);
            } else {
                $passEnc = db()->query("SELECT smtp_pass_enc FROM tenants_config WHERE id=1")->fetchColumn() ?: null;
            }
            db()->prepare("UPDATE tenants_config SET smtp_host=?, smtp_port=?, smtp_user=?, smtp_pass_enc=?, smtp_from=?, smtp_secure=?, updated_at=? WHERE id=1")
                ->execute([$smtpHost ?: null, $smtpPort, $smtpUser ?: null, $passEnc, $smtpFrom ?: null, $smtpSecure, gmdate('Y-m-d H:i:s')]);
            audit('smtp_config_updated', (int)$me['id']);
            $ok = 'Configuración SMTP guardada correctamente.';
        }
    } elseif ($act === 'test_smtp') {
        $testTo = compliance_email();
        if (!$testTo) {
            $errors[] = 'Configura primero el email del responsable de cumplimiento (sección Empresa).';
        } else {
            $sent = send_mail(
                $testTo,
                '[Canal Ético] Correo de prueba',
                "Este es un correo de prueba enviado desde el panel de administración de Canal Ético.\n\n"
                . "Si lo recibes, el servidor de correo está configurado correctamente.\n\n"
                . "Fecha: " . gmdate('Y-m-d H:i:s') . " UTC"
            );
            if ($sent) {
                $ok = 'Correo de prueba enviado a ' . $testTo . '.';
            } else {
                $errors[] = 'Error al enviar el correo de prueba. Revisa la configuración SMTP.';
            }
        }
    } elseif ($act === 'run_retention') {
        $res = run_retention();
        audit('retention_manual_run', (int)$me['id'], null, $res);
        $ok = t('set.ok.retention') . ' ' . (int)$res['anonimizadas'];
    }
}

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch();
render_header(t('set.title'), true);
?>
<main class="wide">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('set.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('set.title')) ?></h1>
        <p><?= h(t('set.sub')) ?></p>
    </div>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('set.company')) ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('set.company.name')) ?></label>
                    <input name="empresa" required value="<?= h((string)($cfg['empresa'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label><?= h(t('set.company.email')) ?></label>
                    <input type="email" name="email_compliance" required value="<?= h((string)($cfg['email_compliance'] ?? '')) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('set.lang')) ?></label>
                    <select name="idioma_default">
                        <option value="es" <?= ($cfg['idioma_default']??'es')==='es'?'selected':'' ?>>Español</option>
                        <option value="en" <?= ($cfg['idioma_default']??'')==='en'?'selected':'' ?>>English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= h(t('set.phone')) ?> <small><?= h(t('set.phone.hint')) ?></small></label>
                    <input type="tel" name="telefono_canal" value="<?= h((string)($cfg['telefono_canal'] ?? '')) ?>" placeholder="+34 900 000 000">
                </div>
            </div>
            <div class="form-group">
                <label><?= h(t('set.meetingEmail')) ?> <small><?= h(t('common.optional')) ?></small></label>
                <input type="email" name="presencial_email" value="<?= h((string)($cfg['presencial_email'] ?? '')) ?>">
            </div>

            <div class="section-title"><?= h(t('set.dpo')) ?></div>
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('set.dpo.name')) ?> <small><?= h(t('common.optional')) ?></small></label>
                    <input type="text" name="dpo_nombre" value="<?= h((string)($cfg['dpo_nombre'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label><?= h(t('set.dpo.email')) ?> <small><?= h(t('set.dpo.hint')) ?></small></label>
                    <input type="email" name="dpo_email" value="<?= h((string)($cfg['dpo_email'] ?? '')) ?>">
                </div>
            </div>

            <div class="section-title"><?= h(t('set.legal')) ?></div>
            <div class="form-group">
                <label><?= h(t('set.legal.policy')) ?></label>
                <textarea name="politica_extra" style="min-height:120px"><?= h((string)($cfg['politica_extra'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label><?= h(t('set.legal.notice')) ?></label>
                <textarea name="aviso_legal_extra" style="min-height:120px"><?= h((string)($cfg['aviso_legal_extra'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label><?= h(t('set.legal.accessibility')) ?></label>
                <textarea name="accesibilidad_extra" style="min-height:100px"><?= h((string)($cfg['accesibilidad_extra'] ?? '')) ?></textarea>
            </div>

            <div class="btn-row"><button class="btn" type="submit"><?= h(t('set.save')) ?></button></div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('set.docs')) ?></h2>
        <p class="muted"><?= h(t('set.docs.hint')) ?></p>

        <div class="grid-2">
            <?php foreach (['dpia' => 'DPIA', 'rat' => 'RAT/RoPA'] as $kind => $label):
                $path = $cfg[$kind.'_path'] ?? null;
                $fecha = $cfg[$kind.'_fecha'] ?? null;
            ?>
            <div style="padding:18px;background:var(--n-25);border:1px solid var(--border);border-radius:var(--radius-lg)">
                <h3 style="margin-top:0"><?= h($label) ?></h3>

                <?php if ($path): ?>
                    <div class="alert ok" style="margin:10px 0"><div><?= h(t('set.docs.uploaded')) ?> <strong><?= h(substr((string)$fecha, 0, 10)) ?></strong></div></div>
                    <div class="btn-row" style="margin-top:8px">
                        <a class="btn sm" href="/admin/download_doc.php?kind=<?= $kind ?>"><?= h(t('set.docs.download')) ?></a>
                        <form method="post" data-confirm="<?= h(t('set.docs.confirm')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove_doc"><input type="hidden" name="kind" value="<?= $kind ?>"><button class="btn sm ghost" type="submit" style="color:var(--err)"><?= h(t('set.docs.delete')) ?></button></form>
                    </div>
                <?php else: ?>
                    <div class="empty" style="margin:10px 0;padding:20px"><p class="small muted"><?= h(t('set.docs.none')) ?></p></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" style="margin-top:10px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_doc">
                    <input type="hidden" name="kind" value="<?= $kind ?>">
                    <input type="file" name="doc" accept="application/pdf" required style="padding:6px">
                    <div class="btn-row" style="margin-top:8px"><button class="btn sm secondary" type="submit"><?= h(t('set.docs.upload_btn')) ?></button></div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('set.logo')) ?></h2>
        <p class="muted"><?= h(t('set.logo.hint')) ?></p>

        <?php $logoUrl = brand_logo_url(); ?>
        <?php if ($logoUrl): ?>
            <div style="display:flex;align-items:center;gap:20px;padding:18px;background:var(--n-25);border:1px solid var(--border);border-radius:var(--radius-lg);margin:14px 0">
                <img src="<?= h($logoUrl) ?>" alt="<?= h(t('set.logo.current')) ?>" style="max-height:70px;max-width:220px;background:#fff;padding:8px;border-radius:8px;border:1px solid var(--border)">
                <div style="flex:1">
                    <div class="small muted" style="margin-bottom:4px"><?= h(t('set.logo.current')) ?></div>
                    <code style="font-size:.8rem"><?= h($logoUrl) ?></code>
                </div>
                <form method="post" data-confirm="<?= h(t('set.logo.confirm')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="logo_remove">
                    <button class="btn sm ghost" type="submit" style="color:var(--err)"><?= h(t('set.logo.remove')) ?></button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty" style="margin:14px 0"><div class="empty-icon">🖼️</div><p><?= h(t('set.logo.none')) ?></p></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logo_upload">
            <div class="form-group">
                <label><?= h(t('set.logo.upload')) ?> <small><?= h(t('set.logo.formats')) ?></small></label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
            </div>
            <div class="btn-row"><button class="btn" type="submit"><?= h(t('set.logo.btn')) ?></button></div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Correo electrónico (SMTP)</h2>
        <p class="muted">Configura el servidor de envío para que las notificaciones lleguen a su destino. Sin esta configuración se usa <code>mail()</code> de PHP, que muchos hostings bloquean.</p>

        <?php
        $smtpRow    = db()->query("SELECT smtp_host, smtp_port, smtp_user, smtp_from, smtp_secure FROM tenants_config WHERE id=1")->fetch();
        $smtpHost   = (string)($smtpRow['smtp_host'] ?? '');
        $smtpPort   = (int)($smtpRow['smtp_port'] ?? 587);
        $smtpUser   = (string)($smtpRow['smtp_user'] ?? '');
        $smtpFrom   = (string)($smtpRow['smtp_from'] ?? '');
        $smtpSecure = (string)($smtpRow['smtp_secure'] ?? 'tls');
        $suggestedFrom = smtp_default_from();
        ?>

        <?php if ($smtpHost === ''): ?>
        <div class="alert warn" style="margin-bottom:14px"><div>
            <strong>Sin SMTP configurado.</strong>
            Sugerencia: crea la cuenta <strong><?= h($suggestedFrom) ?></strong> en Plesk y úsala aquí.
            La mayoría de hostings solo envían correo desde cuentas de su propio dominio.
        </div></div>
        <?php else: ?>
        <div class="alert ok" style="margin-bottom:14px"><div>SMTP configurado: <strong><?= h($smtpHost) ?>:<?= h((string)$smtpPort) ?></strong> · <?= h($smtpUser) ?></div></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_smtp">
            <div class="grid-2">
                <div class="form-group">
                    <label>Servidor SMTP <small>(p.ej. mail.empresa.com o localhost)</small></label>
                    <input type="text" name="smtp_host" value="<?= h($smtpHost) ?>" placeholder="mail.tudominio.com">
                </div>
                <div class="form-group">
                    <label>Puerto</label>
                    <select name="smtp_port">
                        <option value="587" <?= $smtpPort === 587 ? 'selected' : '' ?>>587 — STARTTLS (recomendado)</option>
                        <option value="465" <?= $smtpPort === 465 ? 'selected' : '' ?>>465 — SSL implícito</option>
                        <option value="25"  <?= $smtpPort === 25  ? 'selected' : '' ?>>25 — Sin cifrado</option>
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Seguridad</label>
                    <select name="smtp_secure">
                        <option value="tls"  <?= $smtpSecure === 'tls'  ? 'selected' : '' ?>>STARTTLS (puerto 587)</option>
                        <option value="ssl"  <?= $smtpSecure === 'ssl'  ? 'selected' : '' ?>>SSL implícito (puerto 465)</option>
                        <option value="none" <?= $smtpSecure === 'none' ? 'selected' : '' ?>>Sin cifrado (no recomendado)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Dirección "De:" <small>Deja vacío para usar <?= h($suggestedFrom) ?></small></label>
                    <input type="email" name="smtp_from" value="<?= h($smtpFrom) ?>" placeholder="<?= h($suggestedFrom) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Usuario SMTP</label>
                    <input type="text" name="smtp_user" value="<?= h($smtpUser) ?>" autocomplete="off" placeholder="<?= h($suggestedFrom) ?>">
                </div>
                <div class="form-group">
                    <label>Contraseña SMTP <?php if ($smtpHost !== ''): ?><small>(deja vacío para mantener la actual)</small><?php endif; ?></label>
                    <input type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= $smtpHost !== '' ? '••••••••' : '' ?>">
                </div>
            </div>
            <div class="btn-row">
                <button class="btn" type="submit">Guardar configuración SMTP</button>
            </div>
        </form>

        <hr style="margin:20px 0">

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_smtp">
            <p class="small muted" style="margin:0 0 10px">Envía un correo de prueba al email del responsable de cumplimiento.</p>
            <div class="btn-row">
                <button class="btn secondary" type="submit">Enviar correo de prueba</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= h(t('set.retention')) ?></h2>
        <p><?= t('set.retention.desc') ?></p>
        <p class="small muted"><?= h(t('set.retention.cron')) ?></p>
        <pre>php <?= h(APP_ROOT) ?>/includes/retention.php</pre>
        <form method="post" data-confirm="<?= h(t('set.retention.confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_retention">
            <div class="btn-row"><button class="btn secondary" type="submit"><?= h(t('set.retention.btn')) ?></button></div>
        </form>
    </div>

    <div class="card" style="border-color:var(--err-border);background:var(--err-bg)">
        <h2 style="margin-top:0;color:#6e1b1b"><?= h(t('set.danger')) ?></h2>
        <p style="color:#6e1b1b"><?= h(t('set.danger.desc')) ?></p>
        <div class="btn-row"><a class="btn danger" href="/admin/reset.php"><?= h(t('set.danger.btn')) ?></a></div>
    </div>

    <div class="card card-dark">
        <h2 style="margin-top:0;color:#fff"><?= h(t('set.sys')) ?></h2>
        <div class="kv">
            <div style="color:rgba(255,255,255,.55)"><?= h(t('set.sys.domain')) ?></div><div><code style="background:rgba(255,255,255,.1);color:#fff;border-color:transparent"><?= h($_SERVER['HTTP_HOST'] ?? '') ?></code></div>
            <div style="color:rgba(255,255,255,.55)"><?= h(t('set.sys.php')) ?></div><div><?= h(PHP_VERSION) ?></div>
            <div style="color:rgba(255,255,255,.55)"><?= h(t('set.sys.installed')) ?></div><div><?= h((string)($GLOBALS['CONFIG']['installed_at'] ?? '')) ?> UTC</div>
            <div style="color:rgba(255,255,255,.55)"><?= h(t('set.sys.uploads')) ?></div><div><?= is_writable(APP_ROOT . '/uploads') ? '<span class="badge resuelta">'.h(t('set.sys.writable')).'</span>' : '<span class="badge overdue">'.h(t('set.sys.notwritable')).'</span>' ?></div>
        </div>
    </div>
</main>
<?php render_footer(true); ?>
