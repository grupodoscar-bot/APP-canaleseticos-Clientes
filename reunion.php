<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/mailer.php';

$errors = [];
$codigo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!public_rate_limit_check('reunion', 3)) {
        $errors[] = 'Demasiadas solicitudes desde tu dirección IP. Espera una hora e inténtalo de nuevo.';
    } else {
        public_rate_limit_record('reunion');
    }
    $motivo    = trim((string)($_POST['motivo'] ?? ''));
    $contacto  = trim((string)($_POST['contacto'] ?? ''));
    $pref      = trim((string)($_POST['horario'] ?? ''));
    $acepta    = !empty($_POST['acepta']);

    if (mb_strlen($motivo) < 20) $errors[] = 'Explica brevemente el motivo (mínimo 20 caracteres).';
    if (mb_strlen($motivo) > 5000) $errors[] = 'El motivo es demasiado largo.';
    if (!$acepta) $errors[] = 'Debes aceptar la política del canal.';

    if (!$errors) {
        $pdo = db();
        try {
            for ($i = 0; $i < 5; $i++) {
                $cod = codigo_seguimiento();
                $c = $pdo->prepare("SELECT 1 FROM reunion_requests WHERE codigo_seguimiento=?");
                $c->execute([$cod]);
                if (!$c->fetchColumn()) break;
                $cod = null;
            }
            if (!$cod) throw new RuntimeException('No se pudo generar código único');

            $now = gmdate('Y-m-d H:i:s');
            $st = $pdo->prepare("INSERT INTO reunion_requests (codigo_seguimiento, motivo_enc, contacto_enc, preferencia_horario, estado, created_at, updated_at) VALUES (?,?,?,?, 'pendiente', ?, ?)");
            $st->execute([$cod, enc($motivo), $contacto !== '' ? enc($contacto) : null, $pref !== '' ? $pref : null, $now, $now]);

            audit('meeting_requested', null, null, ['cod' => $cod]);

            // Notificar al compliance
            $to = compliance_email();
            if ($to) {
                send_mail($to,
                    "[Canal Ético] Solicitud de reunión presencial — {$cod}",
                    "Se ha recibido una solicitud de reunión presencial o verbal (Directiva UE art. 9.2 / Ley 2/2023).\n\n"
                    . "Código: {$cod}\nHorario preferido: " . ($pref ?: 'no indicado') . "\n\n"
                    . "Tienes un plazo máximo de 7 días naturales para concertar la reunión.\n\n"
                    . "Panel: " . app_url('admin/reuniones.php'));
            }

            $codigo = $cod;
        } catch (Throwable $e) {
            $errors[] = 'Error al registrar: ' . $e->getMessage();
        }
    }
}

render_header(t('meet.title'));

$telefono = db()->query("SELECT telefono_canal FROM tenants_config WHERE id=1")->fetchColumn() ?: null;
?>
<main class="mid">
<?php if ($codigo !== null): ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('meet.bc')]) ?>
    <div class="card">
        <div class="alert ok"><div><?= t('meet.ok.alert') ?></div></div>
        <h1 style="margin-top:22px"><?= h(t('meet.ok.title')) ?></h1>
        <p class="muted"><?= h(t('meet.ok.lead')) ?></p>
        <div class="btn-row">
            <a class="btn dark" href="/index.php"><?= h(t('nav.home')) ?></a>
        </div>
    </div>
<?php else: ?>
    <?= breadcrumb([t('nav.home') => '/index.php', t('meet.bc')]) ?>

    <div class="page-head">
        <h1><?= h(t('meet.title')) ?></h1>
        <p><?= h(t('meet.lead')) ?></p>
    </div>

    <?php if ($telefono): ?>
        <div class="alert info">
            <div><?= t('meet.phone') ?> <a href="tel:<?= h((string)$telefono) ?>"><?= h((string)$telefono) ?></a>. <?= h(t('meet.phone_tip')) ?></div>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="action-card neutral" style="margin:0 0 20px;flex-direction:row;align-items:stretch;gap:0;padding:0;overflow:hidden">
        <div style="flex:1;padding:34px">
            <h2 style="font-size:1.1rem;margin:0 0 4px"><?= h(t('meet.how.title')) ?></h2>
            <ol class="steps" style="margin:0">
                <li><?= h(t('meet.how.s1')) ?></li>
                <li><?= h(t('meet.how.s2')) ?></li>
                <li><?= h(t('meet.how.s3')) ?></li>
            </ol>
        </div>
        <div style="width:380px;flex-shrink:0;position:relative">
            <img src="/assets/photo/foto1.png" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block">
        </div>
    </div>

    <form method="post" autocomplete="off" class="card">
        <?= csrf_field() ?>

        <div class="form-group">
            <label><?= h(t('meet.f.motivo')) ?> <span class="required">*</span></label>
            <textarea name="motivo" required minlength="20" maxlength="5000" placeholder="<?= h(t('meet.f.motivo.ph')) ?>"><?= h($_POST['motivo'] ?? '') ?></textarea>
            <p class="help"><?= h(t('meet.f.motivo.help')) ?></p>
        </div>

        <div class="form-group">
            <label><?= h(t('meet.f.horario')) ?> <small><?= h(t('common.optional')) ?></small></label>
            <input type="text" name="horario" value="<?= h($_POST['horario'] ?? '') ?>" placeholder="<?= h(t('meet.f.horario.ph')) ?>">
        </div>

        <div class="form-group">
            <label><?= h(t('meet.f.contacto')) ?> <small><?= h(t('meet.f.contacto.hint')) ?></small></label>
            <input type="text" name="contacto" value="<?= h($_POST['contacto'] ?? '') ?>" placeholder="<?= h(t('meet.f.contacto.ph')) ?>">
            <p class="help"><?= h(t('meet.f.contacto.help')) ?></p>
        </div>

        <hr>

        <label class="check-inline">
            <input type="checkbox" name="acepta" value="1" required>
            <span><?= t('meet.f.accept') ?></span>
        </label>

        <div class="btn-row">
            <button class="btn lg dark" type="submit"><?= h(t('meet.f.submit')) ?></button>
            <a class="btn ghost" href="/index.php"><?= h(t('common.cancel')) ?></a>
        </div>
    </form>
<?php endif; ?>
</main>
<?php render_footer(); ?>
