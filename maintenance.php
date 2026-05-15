<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$cfg = central_cfg();
$msg    = (string)($cfg['central_status_msg'] ?? '');
$effective = central_effective_status();
$emp    = empresa();

$titles = [
    'unregistered' => ['Canal pendiente de configuración', 'El canal ético de <strong>' . h($emp) . '</strong> aún no ha completado el registro con el operador del servicio.'],
    'stale'        => ['Canal no disponible', 'No ha sido posible verificar la licencia del canal ético de <strong>' . h($emp) . '</strong> con el operador del servicio.'],
    'inactive'     => ['Servicio temporalmente no disponible', 'El canal ético de <strong>' . h($emp) . '</strong> está actualmente desactivado.'],
];
[$title, $body] = $titles[$effective] ?? $titles['inactive'];

http_response_code(503);
header('Retry-After: 3600');

render_header('Servicio no disponible', false, 'minimal');
?>
<main class="mid">
    <div class="card" style="text-align:center;padding:60px 40px;max-width:640px;margin:40px auto">
        <div style="width:64px;height:64px;border-radius:16px;background:var(--n-100);display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
            <?= ic('lock', 32) ?>
        </div>
        <h1 style="margin:0 0 8px"><?= h($title) ?></h1>
        <p class="muted" style="margin:0 0 20px"><?= $body ?></p>

        <?php if ($msg !== ''): ?>
            <div class="alert info" style="text-align:left;margin:0 auto 20px;max-width:480px"><div><?= nl2br(h($msg)) ?></div></div>
        <?php endif; ?>

        <p class="small muted" style="margin:0 0 20px">El administrador del canal puede entrar al panel para completar la activación o contactar con el soporte del operador.</p>
        <div class="btn-row" style="justify-content:center"><a class="btn dark" href="/admin/login.php">Acceso del administrador</a></div>
    </div>
</main>
<?php render_footer(); ?>
