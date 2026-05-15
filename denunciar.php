<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/reporter_auth.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/mailer.php';

$modo = (string)($_GET['modo'] ?? $_POST['modo'] ?? '');
if (!in_array($modo, ['', 'anonima', 'identificada'], true)) $modo = '';
$rep = reporter_current();

if ($modo === 'identificada' && !$rep) {
    header('Location: /login.php?next=' . urlencode('/denunciar.php?modo=identificada'));
    exit;
}

const MAX_FILES = 5;
const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ALLOWED_MIME = [
    'application/pdf' => 'pdf',
    'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp',
    'text/plain' => 'txt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
];

$CAT_KEYS = ['fraude','corrupcion','blanqueo','acoso_laboral','acoso_sexual','discrim_lgtbi','discrim','menores','datos','seguridad','conflicto','contratacion','competencia','otros'];
$REL_KEYS = ['empleado','ex_empleado','proveedor','candidato','tercero','ns_nc'];
$CATEGORIAS = []; foreach ($CAT_KEYS as $k) $CATEGORIAS[$k] = t('cat.'.$k);
$RELACIONES = []; foreach ($REL_KEYS as $k) $RELACIONES[$k] = t('rel.'.$k);

$errors = [];
$codigo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!public_rate_limit_check('denunciar', 5)) {
        $errors[] = 'Demasiadas denuncias desde tu dirección IP. Espera una hora e inténtalo de nuevo.';
    } else {
        public_rate_limit_record('denunciar');
    }
    $categoria = $_POST['categoria'] ?? '';
    $relacion  = $_POST['relacion'] ?? 'ns_nc';
    $titulo    = trim((string)($_POST['titulo'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $acepta = !empty($_POST['acepta']);

    if (!isset($CATEGORIAS[$categoria])) $errors[] = 'Selecciona una categoría válida.';
    if (mb_strlen($titulo) < 5 || mb_strlen($titulo) > 200) $errors[] = 'El título debe tener entre 5 y 200 caracteres.';
    if (mb_strlen($descripcion) < 30) $errors[] = 'La descripción debe tener al menos 30 caracteres.';
    if (mb_strlen($descripcion) > 20000) $errors[] = 'La descripción es demasiado larga.';
    if (!$acepta) $errors[] = 'Debes aceptar la política del canal y la declaración de veracidad.';
    if (!isset($RELACIONES[$relacion])) $relacion = 'ns_nc';

    $files = [];
    if (!empty($_FILES['adjuntos']) && is_array($_FILES['adjuntos']['name'])) {
        $n = count($_FILES['adjuntos']['name']);
        if ($n > MAX_FILES) $errors[] = 'Máximo ' . MAX_FILES . ' archivos.';
        for ($i = 0; $i < $n; $i++) {
            if (($_FILES['adjuntos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES['adjuntos']['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = 'Error subiendo un adjunto.'; continue; }
            $tmp  = $_FILES['adjuntos']['tmp_name'][$i];
            $size = (int)$_FILES['adjuntos']['size'][$i];
            $orig = (string)$_FILES['adjuntos']['name'][$i];
            if ($size <= 0 || $size > MAX_FILE_BYTES) { $errors[] = "Archivo «{$orig}»: tamaño inválido (máx 10 MB)."; continue; }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
            if (!isset(ALLOWED_MIME[$mime])) { $errors[] = "Archivo «{$orig}»: tipo no permitido ({$mime})."; continue; }
            $files[] = ['tmp' => $tmp, 'orig' => $orig, 'mime' => $mime, 'size' => $size];
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            for ($i = 0; $i < 5; $i++) {
                $cod = codigo_seguimiento();
                $c = $pdo->prepare("SELECT 1 FROM reports WHERE codigo_seguimiento=?");
                $c->execute([$cod]);
                if (!$c->fetchColumn()) break;
                $cod = null;
            }
            if (!$cod) throw new RuntimeException('No se pudo generar código único');
            $now = gmdate('Y-m-d H:i:s');
            $isIdent = ($modo === 'identificada' && $rep) ? 1 : 0;
            $repId   = $isIdent ? (int)$rep['id'] : null;
            $st = $pdo->prepare("INSERT INTO reports (codigo_seguimiento, categoria, titulo_enc, descripcion_enc, relacion, estado, reporter_id, identificada, created_at, updated_at)
                                 VALUES (?, ?, ?, ?, ?, 'recibida', ?, ?, ?, ?)");
            $st->execute([$cod, $categoria, enc($titulo), enc($descripcion), $relacion, $repId, $isIdent, $now, $now]);
            $rid = (int)$pdo->lastInsertId();

            foreach ($files as $f) {
                $ext = ALLOWED_MIME[$f['mime']] ?? 'bin';
                $diskName = bin2hex(random_bytes(16)) . '.' . $ext . '.enc';
                enc_file($f['tmp'], APP_ROOT . '/uploads/' . $diskName);
                $st = $pdo->prepare("INSERT INTO report_attachments (report_id, filename_original_enc, filename_disk, mime, size_bytes, created_at) VALUES (?,?,?,?,?,?)");
                $st->execute([$rid, enc($f['orig']), $diskName, $f['mime'], $f['size'], $now]);
            }
            // Libro-registro (art. 26 Ley 2/2023) — retención 10 años, solo metadatos
            $archivable = gmdate('Y-m-d H:i:s', strtotime('+10 years'));
            $intPayload = $cod . '|' . $categoria . '|' . $now . '|' . ($relacion ?? '');
            $intHash = hmac_hash($intPayload);
            $st = $pdo->prepare("INSERT INTO libro_registro (report_id, codigo_seguimiento, categoria, canal, relacion, fecha_recepcion, archivable_desde, hash_integridad) VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$rid, $cod, $categoria, $isIdent ? 'web_ident' : 'web', $relacion, $now, $archivable, $intHash]);

            $pdo->commit();
            audit('report_created', null, $rid, ['categoria' => $categoria]);
            notify_new_report($cod, $CATEGORIAS[$categoria]);
            if ($isIdent && $rep) {
                notify_report_confirmation_reporter((string)$rep['email'], $cod);
            }
            $codigo = $cod;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Error al registrar: ' . $e->getMessage();
        }
    }
}

render_header(t($codigo ? 'report.ok.title' : 'report.title'));
?>
<main class="mid">
<?php if ($codigo !== null): ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('report.ok.title')]) ?>

    <div class="card card-accent">
        <div class="alert ok"><div><?= t('report.ok.alert') ?></div></div>

        <h1 style="margin-top:22px"><?= h(t('report.ok.title')) ?></h1>
        <p class="muted"><?= t('report.ok.lead') ?></p>

        <div class="code-box" id="codigo" style="margin-top:20px"><?= h($codigo) ?></div>

        <div class="btn-row">
            <button type="button" class="btn dark" data-copy="#codigo" style="background:#111;border-color:#111;color:#fff"><?= h(t('report.ok.copy')) ?></button>
            <?php if ($rep): ?>
                <a class="btn secondary" href="/mis-denuncias.php"><?= h(t('rep.panel.title')) ?></a>
            <?php else: ?>
                <a class="btn secondary" href="/seguimiento.php"><?= h(t('report.ok.go')) ?></a>
            <?php endif; ?>
        </div>

        <div class="section-title"><?= h(t('report.ok.next')) ?></div>
        <ul class="clean">
            <li><span>1</span><span><?= h(t('report.ok.s1')) ?></span></li>
            <li><span>2</span><span><?= h(t('report.ok.s2')) ?></span></li>
            <li><span>3</span><span><?= h(t('report.ok.s3')) ?></span></li>
        </ul>
    </div>
<?php elseif ($modo === ''): ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('report.bc')]) ?>

    <div class="page-head">
        <h1><?= h(t('choose.title')) ?></h1>
        <p><?= h(t('choose.lead')) ?></p>
    </div>

    <div class="grid-2" style="gap:24px;align-items:stretch">
        <div class="card" style="display:flex;flex-direction:column">
            <div class="small muted" style="text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px"><?= h(t('choose.badge.anon')) ?></div>
            <h2 style="margin:0 0 10px;font-size:1.4rem"><?= h(t('choose.anon.title')) ?></h2>
            <p style="margin:0 0 16px"><?= h(t('choose.anon.desc')) ?></p>
            <ul class="clean" style="flex:1">
                <li><span><?= ic('shield', 18) ?></span><span><?= h(t('choose.anon.f1')) ?></span></li>
                <li><span><?= ic('key', 18) ?></span><span><?= h(t('choose.anon.f2')) ?></span></li>
                <li><span><?= ic('scale', 18) ?></span><span><?= h(t('choose.anon.f3')) ?></span></li>
            </ul>
            <div class="btn-row" style="margin-top:auto"><a class="btn lg dark block" href="/denunciar.php?modo=anonima"><?= h(t('choose.anon.btn')) ?></a></div>
        </div>

        <div class="card" style="display:flex;flex-direction:column">
            <div class="small muted" style="text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px"><?= h(t('choose.badge.id')) ?></div>
            <h2 style="margin:0 0 10px;font-size:1.4rem"><?= h(t('choose.id.title')) ?></h2>
            <p style="margin:0 0 16px"><?= h(t('choose.id.desc')) ?></p>
            <ul class="clean" style="flex:1">
                <li><span><?= ic('list', 18) ?></span><span><?= h(t('choose.id.f1')) ?></span></li>
                <li><span><?= ic('check-circle', 18) ?></span><span><?= h(t('choose.id.f2')) ?></span></li>
                <li><span><?= ic('scale', 18) ?></span><span><?= h(t('choose.id.f3')) ?></span></li>
            </ul>
            <div class="btn-row" style="margin-top:auto"><a class="btn lg dark block" href="/denunciar.php?modo=identificada"><?= h(t('choose.id.btn')) ?></a></div>
        </div>
    </div>
<?php else: ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('report.bc') => '/denunciar.php', $modo === 'identificada' ? t('choose.id.title') : t('choose.anon.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('report.title')) ?></h1>
        <p><?= h(t('report.lead')) ?></p>
    </div>

    <?php if ($modo === 'identificada' && $rep): ?>
    <div class="alert info" style="margin-bottom:18px"><div><strong><?= h(t('choose.id.title')) ?>.</strong> <?= h(t('rep.panel.hi')) ?> <strong><?= h($rep['nombre_enc'] ? (string)dec((string)$rep['nombre_enc']) : (string)$rep['email']) ?></strong>. <a href="/denunciar.php?modo=anonima">¿Prefieres anónima?</a></div></div>
    <?php elseif ($modo === 'anonima'): ?>
    <div class="alert info" style="margin-bottom:18px"><div><strong><?= h(t('choose.anon.title')) ?>.</strong> <?= h(t('choose.anon.f3')) ?>. <a href="/denunciar.php?modo=identificada">¿Prefieres identificarte?</a></div></div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="grid-sidebar" style="align-items:stretch;gap:28px">
    <form method="post" enctype="multipart/form-data" autocomplete="off" class="card" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="modo" value="<?= h($modo) ?>">

        <div class="grid-2">
            <div class="form-group">
                <label for="categoria"><?= h(t('report.f.category')) ?> <span class="required">*</span></label>
                <select id="categoria" name="categoria" required>
                    <option value=""><?= h(t('report.f.category.placeholder')) ?></option>
                    <?php foreach ($CATEGORIAS as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= (($_POST['categoria'] ?? '') === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="relacion"><?= h(t('report.f.relation')) ?> <small><?= h(t('common.optional')) ?></small></label>
                <select id="relacion" name="relacion">
                    <?php foreach ($RELACIONES as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= (($_POST['relacion'] ?? 'ns_nc') === $k) ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="titulo"><?= h(t('report.f.title')) ?> <span class="required">*</span></label>
            <input id="titulo" name="titulo" type="text" maxlength="200" required value="<?= h($_POST['titulo'] ?? '') ?>" placeholder="<?= h(t('report.f.title.placeholder')) ?>">
        </div>

        <div class="form-group">
            <label for="descripcion"><?= h(t('report.f.desc')) ?> <span class="required">*</span></label>
            <textarea id="descripcion" name="descripcion" required minlength="30" maxlength="20000" placeholder="<?= h(t('report.f.desc.placeholder')) ?>"><?= h($_POST['descripcion'] ?? '') ?></textarea>
            <p class="help"><?= h(t('report.f.desc.help')) ?></p>
        </div>

        <div class="form-group">
            <label for="adjuntos"><?= h(t('report.f.attach')) ?> <small><?= h(t('report.f.attach.hint')) ?></small></label>
            <input id="adjuntos" name="adjuntos[]" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.doc,.docx,.xls,.xlsx">
            <p class="help"><?= h(t('report.f.attach.help')) ?></p>
        </div>

        <hr>

        <label class="check-inline">
            <input type="checkbox" name="acepta" value="1" required>
            <span><?= t('report.f.accept') ?></span>
        </label>

        <div class="btn-row">
            <button class="btn lg dark" type="submit"><?= h(t('report.f.submit')) ?></button>
            <a class="btn ghost" href="/index.php"><?= h(t('common.cancel')) ?></a>
        </div>
    </form>

    <div class="card" style="margin:0;display:flex;flex-direction:column;justify-content:space-between">
        <h2 style="font-size:1.5rem;margin:0 0 20px"><?= h(t('report.how.title')) ?></h2>
        <ol style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:16px">
            <li style="display:flex;gap:14px;align-items:flex-start">
                <span style="flex-shrink:0;width:28px;height:28px;border-radius:50%;background:var(--n-200);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--fg)">1</span>
                <span style="padding-top:4px"><?= h(t('report.how.s1')) ?></span>
            </li>
            <li style="display:flex;gap:14px;align-items:flex-start">
                <span style="flex-shrink:0;width:28px;height:28px;border-radius:50%;background:var(--n-200);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--fg)">2</span>
                <span style="padding-top:4px"><?= h(t('report.how.s2')) ?></span>
            </li>
            <li style="display:flex;gap:14px;align-items:flex-start">
                <span style="flex-shrink:0;width:28px;height:28px;border-radius:50%;background:var(--n-200);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--fg)">3</span>
                <span style="padding-top:4px"><?= h(t('report.how.s3')) ?></span>
            </li>
        </ol>
        <img src="/assets/photo/foto5.png" alt="" style="margin-top:24px;width:100%;border-radius:10px;object-fit:cover;display:block">
    </div>
    </div>
<?php endif; ?>
</main>
<?php render_footer(); ?>
