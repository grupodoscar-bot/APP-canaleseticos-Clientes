<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/retention.php';
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
        if ($empresa === '' || !filter_var($emailc, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Datos inválidos.';
        } else {
            db()->prepare("UPDATE tenants_config SET empresa=?, email_compliance=?, politica_extra=?, aviso_legal_extra=?, updated_at=? WHERE id=1")
                ->execute([$empresa, $emailc, $pol ?: null, $avis ?: null, gmdate('Y-m-d H:i:s')]);
            audit('config_updated', (int)$me['id']);
            $ok = 'Configuración guardada. Nota: el email y nombre mostrados se refrescan del config.php sólo al reinstalar — se actualizaron en BD.';
        }
    } elseif ($act === 'run_retention') {
        $res = run_retention();
        audit('retention_manual_run', (int)$me['id'], null, $res);
        $ok = 'Retención ejecutada. Denuncias anonimizadas: ' . (int)$res['anonimizadas'];
    }
}

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch();
render_header('Configuración', true);
?>
<main>
<div class="card">
    <h1>Configuración</h1>
    <?php if ($ok): ?><div class="alert ok"><?= h($ok) ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><?= h($e) ?></div><?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <label>Nombre de la empresa <input name="empresa" required value="<?= h((string)($cfg['empresa'] ?? '')) ?>"></label>
        <label>Email compliance <input type="email" name="email_compliance" required value="<?= h((string)($cfg['email_compliance'] ?? '')) ?>"></label>
        <label>Texto adicional en la política del canal (opcional)
            <textarea name="politica_extra" style="min-height:140px"><?= h((string)($cfg['politica_extra'] ?? '')) ?></textarea>
        </label>
        <label>Texto adicional en el aviso legal (opcional)
            <textarea name="aviso_legal_extra" style="min-height:140px"><?= h((string)($cfg['aviso_legal_extra'] ?? '')) ?></textarea>
        </label>
        <div class="btn-row"><button class="btn" type="submit">Guardar</button></div>
    </form>
</div>

<div class="card">
    <h2>Retención de datos</h2>
    <p>Se anonimizan automáticamente las denuncias con más de 3 meses (Ley 2/2023 + RGPD), salvo aquellas marcadas como <em>en investigación</em>. Puedes ejecutarlo manualmente ahora o configurar un cron diario en Plesk:</p>
    <pre style="background:#f0f3f9;padding:10px;border-radius:8px;overflow:auto">php <?= h(APP_ROOT) ?>/includes/retention.php</pre>
    <form method="post" data-confirm="Esta acción anonimizará denuncias antiguas. ¿Continuar?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run_retention">
        <div class="btn-row"><button class="btn danger" type="submit">Ejecutar retención ahora</button></div>
    </form>
</div>

<div class="card">
    <h2>Zona peligrosa</h2>
    <p>Reinicio total: borra base de datos, adjuntos, claves de cifrado y configuración. Uso típico: entornos de prueba o antes de migrar la instalación a otro cliente.</p>
    <div class="btn-row"><a class="btn danger" href="reset.php">Reiniciar sistema (borrar todo)</a></div>
</div>

<div class="card">
    <h2>Información del sistema</h2>
    <div class="kv">
        <div>Dominio:</div><div><?= h($_SERVER['HTTP_HOST'] ?? '') ?></div>
        <div>PHP:</div><div><?= h(PHP_VERSION) ?></div>
        <div>Instalado:</div><div><?= h((string)($GLOBALS['CONFIG']['installed_at'] ?? '')) ?> UTC</div>
        <div>Directorio uploads:</div><div><?= is_writable(APP_ROOT . '/uploads') ? 'Escribible ✔' : '<span class="badge overdue">No escribible</span>' ?></div>
    </div>
</div>
</main>
<?php render_footer(true); ?>
