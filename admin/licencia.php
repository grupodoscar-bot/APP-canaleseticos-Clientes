<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/stripe_client.php';
$me = require_auth();
require_admin($me);

$errors = []; $ok = null;

// Al entrar a licencia, verificamos siempre el estado real con el central
// (salta el TTL de lazy check). Si el central devuelve tenant_unknown, la
// identidad local se limpia automáticamente, evitando mostrar "Pagar" sobre
// un registro obsoleto.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cfgBefore = central_cfg();
    if (!empty($cfgBefore['central_tenant_uid'])) {
        try { central_fetch_status(); } catch (Throwable $e) { /* silencioso */ }
    }
}

// Retorno de Stripe tras pago
if (($_GET['pago'] ?? '') === 'ok' && !empty($_GET['session_id'])) {
    $sid = (string)$_GET['session_id'];
    $rs = stripe_retrieve_session($sid);
    if (!empty($rs['ok']) && ($rs['data']['payment_status'] ?? '') === 'paid') {
        $amt = (int)($rs['data']['amount_total'] ?? 0);
        $cur = (string)($rs['data']['currency'] ?? 'eur');
        // Recuperar cupón desde la metadata de la sesión Stripe (autoritativo)
        $couponCode = (string)($rs['data']['metadata']['coupon_code'] ?? '');
        $originalAmt = (int)($rs['data']['metadata']['original_amount_cents'] ?? 0);
        // Guardamos siempre el pago localmente aunque la notificación al central falle.
        central_save([
            'last_paid_session_id' => $sid,
            'last_paid_amount'     => $amt,
            'last_paid_currency'   => $cur,
            'last_paid_at'         => gmdate('Y-m-d H:i:s'),
            'last_paid_coupon'     => $couponCode ?: null,
            'last_paid_original'   => $originalAmt ?: null,
        ]);
        $rn = central_notify_payment($sid, $amt, $cur, $couponCode ?: null, $originalAmt);
        if (!empty($rn['ok'])) {
            $ok = 'Pago completado correctamente. Tu canal se ha activado.'
                . ($couponCode !== '' ? ' (Cupón ' . $couponCode . ' aplicado)' : '');
            // Limpiamos el pendiente una vez notificado y la sesión de cupón.
            central_save(['last_paid_session_id' => null]);
            unset($_SESSION['coupon']);
            audit('stripe_payment_ok', (int)$me['id'], null, ['session' => $sid, 'amount' => $amt, 'coupon' => $couponCode ?: null]);
        } else {
            $errors[] = 'El pago en Stripe se ha completado correctamente, pero el servidor central no pudo confirmarlo (error: ' . h((string)($rn['err'] ?? 'desconocido')) . '). Usa el botón "Reintentar notificación" más abajo para volver a intentarlo. El cobro ya está registrado en Stripe, no se te cobrará de nuevo.';
            audit('stripe_payment_central_fail', (int)$me['id'], null, ['session' => $sid, 'err' => (string)($rn['err'] ?? '')]);
        }
    } else {
        $errors[] = 'No se pudo verificar el pago en Stripe.' . (!empty($rs['err']) ? ' (' . h((string)$rs['err']) . ')' : '');
    }
} elseif (($_GET['pago'] ?? '') === 'cancel') {
    $errors[] = 'Pago cancelado. El canal sigue desactivado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'central_refresh') {
        $r = central_fetch_status();
        if (!empty($r['ok'])) {
            $ok = 'Estado del servidor central actualizado: ' . ($r['data']['status'] ?? '—');
        } elseif (($r['err'] ?? '') === 'tenant_unknown' || (int)($r['status'] ?? 0) === 404) {
            // El central eliminó el tenant. La identidad local ya se ha limpiado.
            // Registramos automáticamente para que el admin pueda continuar.
            $reReg = central_register($_SERVER['HTTP_HOST'] ?? 'localhost', (string)empresa(), (string)($GLOBALS['CONFIG']['email_compliance'] ?? ''));
            if (!empty($reReg['ok'])) {
                $ok = 'La instalación anterior había sido eliminada del servidor central. Se ha creado un nuevo registro. Estado: ' . ($reReg['data']['status'] ?? 'inactive') . '.';
            } else {
                $reCode = (string)($reReg['err'] ?? 'desconocido');
                if ($reCode === 'rate_limited' || (int)($reReg['status'] ?? 0) === 429) {
                    $errors[] = 'La instalación anterior había sido eliminada del servidor central. Para volver a registrarla hay que esperar unos minutos (el operador limita los intentos de registro por seguridad). Vuelve a intentarlo más tarde.';
                } else {
                    $errors[] = 'La instalación anterior había sido eliminada del servidor central. Intentamos registrarla de nuevo pero falló: ' . $reCode;
                }
            }
        } else {
            $errors[] = 'No se pudo contactar con el servidor central: ' . (string)($r['err'] ?? 'desconocido');
        }
    } elseif ($act === 'central_register') {
        $r = central_register($_SERVER['HTTP_HOST'] ?? 'localhost', (string)empresa(), (string)($GLOBALS['CONFIG']['email_compliance'] ?? ''));
        if (!empty($r['ok'])) {
            $ok = 'Registrado correctamente en el servidor central.';
        } else {
            $errCode = (string)($r['err'] ?? 'desconocido');
            if ($errCode === 'conflict') {
                $errors[] = 'Este dominio ' . h($_SERVER['HTTP_HOST'] ?? '') . ' ya figura registrado en el servidor central por una instalación anterior. Contacta con el operador del servicio para que libere el registro, o elimínalo desde el panel del operador y vuelve a intentarlo.';
            } elseif ($errCode === 'rate_limited' || (int)($r['status'] ?? 0) === 429) {
                $errors[] = 'Has realizado demasiados intentos de registro recientemente. Espera unos minutos y vuelve a intentarlo. Si crees que es un error, contacta con el operador del servicio.';
            } else {
                $errors[] = 'No se pudo registrar: ' . $errCode;
            }
        }
    } elseif ($act === 'retry_payment_notify') {
        $cfg2 = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
        $pendSid = (string)($cfg2['last_paid_session_id'] ?? '');
        if ($pendSid === '') {
            $errors[] = 'No hay ningún pago pendiente de notificar.';
        } else {
            $pendAmt     = (int)($cfg2['last_paid_amount'] ?? 0);
            $pendCur     = (string)($cfg2['last_paid_currency'] ?? 'eur');
            $pendCoupon  = (string)($cfg2['last_paid_coupon'] ?? '');
            $pendOrig    = (int)($cfg2['last_paid_original'] ?? 0);
            $rn = central_notify_payment($pendSid, $pendAmt, $pendCur, $pendCoupon ?: null, $pendOrig);
            if (!empty($rn['ok'])) {
                $ok = 'Pago notificado correctamente. Tu canal se ha activado.';
                central_save(['last_paid_session_id' => null]);
                audit('stripe_payment_retry_ok', (int)$me['id'], null, ['session' => $pendSid]);
            } else {
                $errors[] = 'El servidor central sigue sin aceptar la notificación: ' . (string)($rn['err'] ?? 'desconocido') . '. Si persiste, contacta con el operador para que active manualmente el canal (el pago ya está registrado en Stripe).';
                audit('stripe_payment_retry_fail', (int)$me['id'], null, ['session' => $pendSid, 'err' => (string)($rn['err'] ?? '')]);
            }
        }
    } elseif ($act === 'apply_coupon') {
        $code = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
        if ($code === '') {
            $errors[] = 'Introduce un código de cupón.';
        } else {
            $basePrice = (int)($GLOBALS['CONFIG']['stripe_price_eur'] ?? 12000);
            $rv = central_validate_coupon($code, $basePrice, 'eur');
            if (!empty($rv['ok'])) {
                $d = $rv['data'];
                $_SESSION['coupon'] = [
                    'code'               => (string)($d['code'] ?? $code),
                    'type'               => (string)($d['type'] ?? 'percent'),
                    'value'              => (int)($d['value'] ?? 0),
                    'discount_cents'     => (int)($d['discount_cents'] ?? 0),
                    'final_amount_cents' => (int)($d['final_amount_cents'] ?? $basePrice),
                    'original_amount_cents' => $basePrice,
                    'currency'           => (string)($d['currency'] ?? 'eur'),
                    'description'        => (string)($d['description'] ?? ''),
                    'validated_at'       => time(),
                ];
                $discEur = number_format($_SESSION['coupon']['discount_cents'] / 100, 2, ',', '.');
                $ok = 'Cupón «' . h($_SESSION['coupon']['code']) . '» aplicado. Descuento: ' . $discEur . ' €.';
                audit('coupon_applied', (int)$me['id'], null, ['code' => $_SESSION['coupon']['code']]);
            } else {
                unset($_SESSION['coupon']);
                $errCode = (string)($rv['err'] ?? 'desconocido');
                $friendly = [
                    'coupon_not_found'             => 'El cupón no existe o no está activo.',
                    'coupon_expired'               => 'El cupón ha caducado.',
                    'coupon_not_started'           => 'El cupón aún no está disponible.',
                    'coupon_max_uses_reached'      => 'El cupón ha alcanzado su límite de usos.',
                    'coupon_already_used_by_tenant'=> 'Ya has usado este cupón anteriormente.',
                    'coupon_min_amount_not_met'    => 'El importe no cumple el mínimo requerido por el cupón.',
                    'coupon_wrong_currency'        => 'El cupón no es válido para esta moneda.',
                    'coupon_inactive'              => 'El cupón está desactivado.',
                    'empty_code'                   => 'Introduce un código de cupón.',
                ][$errCode] ?? ('No se pudo validar el cupón: ' . $errCode);
                $errors[] = $friendly;
            }
        }
    } elseif ($act === 'remove_coupon') {
        unset($_SESSION['coupon']);
        $ok = 'Cupón eliminado.';
    } elseif ($act === 'pay_stripe') {
        if (!stripe_enabled()) {
            $errors[] = 'Stripe no está configurado en este servidor.';
        } else {
            try {
                $cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
                $uid = (string)($cfg['central_tenant_uid'] ?? '');
                if ($uid === '') { throw new RuntimeException('Instalación no registrada en el servidor central.'); }
                $email = (string)($GLOBALS['CONFIG']['email_compliance'] ?? '');
                if ($email === '' && !empty($me['email'])) $email = (string)$me['email'];
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $email = ''; }
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
                $okUrl = $base . '/admin/licencia.php?pago=ok&session_id={CHECKOUT_SESSION_ID}';
                $cxUrl = $base . '/admin/licencia.php?pago=cancel';
                $basePrice = (int)($GLOBALS['CONFIG']['stripe_price_eur'] ?? 12000);

                // Auto-apply: si llega coupon_code en POST pero no está en sesión, validarlo ahora
                $postedCoupon = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
                if ($postedCoupon !== '' && empty($_SESSION['coupon']['code'])) {
                    $rvAuto = central_validate_coupon($postedCoupon, $basePrice, 'eur');
                    if (!empty($rvAuto['ok'])) {
                        $d = $rvAuto['data'];
                        $_SESSION['coupon'] = [
                            'code'                  => (string)($d['code'] ?? $postedCoupon),
                            'type'                  => (string)($d['type'] ?? 'percent'),
                            'value'                 => (int)($d['value'] ?? 0),
                            'discount_cents'        => (int)($d['discount_cents'] ?? 0),
                            'final_amount_cents'    => (int)($d['final_amount_cents'] ?? $basePrice),
                            'original_amount_cents' => $basePrice,
                            'currency'              => (string)($d['currency'] ?? 'eur'),
                            'description'           => (string)($d['description'] ?? ''),
                            'validated_at'          => time(),
                        ];
                        audit('coupon_auto_applied', (int)$me['id'], null, ['code' => $postedCoupon]);
                    } else {
                        throw new RuntimeException('Cupón «' . $postedCoupon . '» no válido (' . (string)($rvAuto['err'] ?? 'desconocido') . '). Quítalo o corrígelo antes de pagar.');
                    }
                }

                // Revalidar cupón justo antes del pago (puede haber expirado)
                $finalPrice   = $basePrice;
                $couponCode   = null;
                $originalAmt  = 0;
                if (!empty($_SESSION['coupon']['code'])) {
                    $rv = central_validate_coupon((string)$_SESSION['coupon']['code'], $basePrice, 'eur');
                    if (!empty($rv['ok'])) {
                        $couponCode  = (string)$rv['data']['code'];
                        $finalPrice  = (int)$rv['data']['final_amount_cents'];
                        $originalAmt = $basePrice;
                        // refrescar sesión con valores actuales del central
                        $_SESSION['coupon'] = array_merge($_SESSION['coupon'], [
                            'discount_cents'        => (int)$rv['data']['discount_cents'],
                            'final_amount_cents'    => $finalPrice,
                            'original_amount_cents' => $basePrice,
                            'validated_at'          => time(),
                        ]);
                    } else {
                        unset($_SESSION['coupon']);
                        throw new RuntimeException('El cupón ya no es válido: ' . (string)($rv['err'] ?? 'desconocido'));
                    }
                }

                $s = stripe_create_license_session($uid, (string)empresa(), $email, $okUrl, $cxUrl, $finalPrice, 'eur', $couponCode, $originalAmt);
                if (!empty($s['ok']) && $s['url'] !== '') {
                    audit('stripe_checkout_created', (int)$me['id'], null, [
                        'session' => $s['session_id'],
                        'amount'  => $finalPrice,
                        'coupon'  => $couponCode,
                    ]);
                    header('Location: ' . $s['url']);
                    exit;
                }
                $errors[] = 'Stripe rechazó la creación del pago: ' . (string)($s['err'] ?? 'desconocido');
            } catch (Throwable $e) {
                $errors[] = 'Error al iniciar el pago: ' . $e->getMessage();
                audit('stripe_checkout_error', (int)$me['id'], null, ['err' => $e->getMessage()]);
            }
        }
    }
}

$cfg = db()->query("SELECT * FROM tenants_config WHERE id=1")->fetch() ?: [];
$centralUrl = central_url();
$centralStatus = (string)($cfg['central_status'] ?? '');
$centralMsg    = (string)($cfg['central_status_msg'] ?? '');
$centralUid    = (string)($cfg['central_tenant_uid'] ?? '');
$centralLastOk = (string)($cfg['central_last_ok'] ?? '');
$centralLastCheck = (string)($cfg['central_last_check'] ?? '');
$centralRegAt  = (string)($cfg['central_registered_at'] ?? '');
$pendingPaidSid = (string)($cfg['last_paid_session_id'] ?? '');
$pendingPaidAmt = (int)($cfg['last_paid_amount'] ?? 0);
$pendingPaidCur = (string)($cfg['last_paid_currency'] ?? 'eur');
$pendingPaidAt  = (string)($cfg['last_paid_at'] ?? '');
$statusBadge = $centralStatus === 'active' ? 'resuelta' : 'overdue';
$statusLabel = [
    'active'       => 'Activa',
    'inactive'     => 'Desactivada',
    'unregistered' => 'Sin registrar',
    'stale'        => 'Sin contacto reciente',
][$centralStatus] ?? ($centralStatus !== '' ? $centralStatus : '—');

render_header('Licencia', true);
?>
<main class="mid" style="max-width:90%">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', 'Licencia']) ?>

    <div class="page-head">
        <h1>Licencia</h1>
        <p>Estado de la conexión con el servidor de licencias <code><?= h($centralUrl) ?></code>. Desde este servidor el operador activa, suspende o desactiva tu canal.</p>
    </div>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err" style="border:2px solid #b91c1c"><div><?= $e ?></div></div><?php endforeach; ?>

    <?php
    // Panel de debug — últimas N líneas de logs (activar con ?debug=1)
    if (isset($_GET['debug'])):
        foreach ([
            'Log Stripe'  => APP_ROOT . '/private/stripe_debug.log',
            'Log Central' => APP_ROOT . '/private/central_debug.log',
        ] as $label => $logPath):
            if (!is_file($logPath)) continue;
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
            $lines = array_slice((array)$lines, -30);
    ?>
    <div class="card" style="background:#111;color:#0f0;font-family:var(--font-mono);font-size:.78rem;line-height:1.5;white-space:pre-wrap;word-break:break-word;overflow:auto;max-height:400px;margin-bottom:14px">
        <strong style="color:#fff"><?= h($label) ?> (últimas 30 líneas — <code style="color:#aaa"><?= h($logPath) ?></code>)</strong>
        <?php foreach ($lines as $l): ?><br><?= h($l) ?><?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>

    <?php if ($centralUid === ''): ?>
        <div class="card">
            <div class="alert warn"><div>Esta instalación <strong>no está registrada</strong> en el servidor central. Regístrala para activar el control remoto.</div></div>
            <form method="post" style="margin-top:14px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="central_register">
                <div class="btn-row"><button class="btn dark lg" type="submit">Registrar instalación</button></div>
            </form>
        </div>
    <?php else: ?>

        <?php if ($pendingPaidSid !== '' && $centralStatus !== 'active'):
            $pendEur = number_format($pendingPaidAmt/100, 2, ',', '.');
        ?>
        <div class="card" style="border:2px solid #b45309;background:#fff7e6;margin-bottom:24px">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px">
                <div style="width:44px;height:44px;border-radius:12px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= ic('clock', 22) ?></div>
                <div>
                    <h2 style="margin:0;font-size:1.25rem;color:#6e4308">Pago realizado pendiente de confirmación</h2>
                    <div class="small" style="color:#8a5a1a">Tu pago de <strong><?= $pendEur ?> <?= strtoupper(h($pendingPaidCur)) ?></strong> el <?= h($pendingPaidAt) ?> UTC se completó correctamente en Stripe, pero el servidor central aún no lo ha confirmado.</div>
                </div>
            </div>

            <p class="small" style="margin:12px 0;color:#6e4308">
                <strong>Sesión Stripe:</strong> <code style="background:rgba(0,0,0,.06)"><?= h($pendingPaidSid) ?></code>
            </p>

            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="retry_payment_notify">
                <div class="btn-row"><button class="btn dark lg" type="submit"><?= ic('send', 18) ?> Reintentar notificación al servidor central</button></div>
            </form>

            <p class="small" style="margin:12px 0 0;color:#8a5a1a">Si sigue fallando, contacta con el operador del servicio: el pago ya está registrado en Stripe y pueden activar el canal manualmente aportando el ID de sesión.</p>
        </div>
        <?php endif; ?>

        <?php if ($centralStatus === 'inactive' && $pendingPaidSid === '' && stripe_enabled()):
            $basePriceCents = (int)($GLOBALS['CONFIG']['stripe_price_eur'] ?? 12000);
            $priceEur = number_format($basePriceCents/100, 2, ',', '.');
            $coupon = $_SESSION['coupon'] ?? null;
            $finalCents = $coupon ? (int)$coupon['final_amount_cents'] : $basePriceCents;
            $finalEur = number_format($finalCents/100, 2, ',', '.');
        ?>
        <div class="card" style="border:2px solid #111;background:linear-gradient(135deg,#fafafa,#fff);margin-bottom:24px">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px">
                <div style="width:44px;height:44px;border-radius:12px;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= ic('lock', 22) ?></div>
                <div>
                    <h2 style="margin:0;font-size:1.3rem">Activa tu canal</h2>
                    <div class="small muted">Tu canal está desactivado. Completa el pago para reactivar el servicio inmediatamente.</div>
                </div>
            </div>

            <div style="display:flex;align-items:baseline;gap:8px;margin:18px 0 8px">
                <?php if ($coupon): ?>
                    <span style="font-size:1.4rem;text-decoration:line-through;color:#9ca3af;font-weight:500"><?= $priceEur ?> €</span>
                    <span style="font-size:2.4rem;font-weight:800;letter-spacing:-.02em;color:#059669"><?= $finalEur ?> €</span>
                <?php else: ?>
                    <span style="font-size:2.4rem;font-weight:800;letter-spacing:-.02em"><?= $priceEur ?> €</span>
                <?php endif; ?>
                <span class="muted">/ licencia</span>
            </div>

            <?php if ($coupon):
                $discEur = number_format($coupon['discount_cents']/100, 2, ',', '.');
            ?>
            <div class="alert ok" style="margin:0 0 14px"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                <span>
                    <strong>Cupón «<?= h($coupon['code']) ?>» aplicado.</strong>
                    Descuento: <?= $discEur ?> €
                    <?php if (!empty($coupon['description'])): ?>· <?= h($coupon['description']) ?><?php endif; ?>
                </span>
                <form method="post" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_coupon">
                    <button class="btn sm ghost" type="submit" style="color:#b91c1c">Quitar</button>
                </form>
            </div></div>
            <?php endif; ?>

            <ul class="clean" style="margin:0 0 18px">
                <li><span><?= ic('check-circle', 16) ?></span><span>Activación inmediata tras el pago</span></li>
                <li><span><?= ic('lock', 16) ?></span><span>Pago seguro procesado por Stripe</span></li>
                <li><span><?= ic('shield', 16) ?></span><span>Cumplimiento Ley 2/2023 y Directiva UE 2019/1937</span></li>
            </ul>

            <?php
                $postedCode = trim((string)($_POST['coupon_code'] ?? ''));
                $detailsOpen = !$coupon && ($postedCode !== '' || !empty($errors));
            ?>
            <form method="post">
                <?= csrf_field() ?>

                <?php if (!$coupon): ?>
                <details <?= $detailsOpen ? 'open' : '' ?> style="margin:0 0 14px">
                    <summary class="small muted" style="cursor:pointer;user-select:none">¿Tienes un cupón de descuento?</summary>
                    <div style="margin-top:10px">
                        <input type="text" name="coupon_code" maxlength="40"
                               value="<?= h(strtoupper($postedCode)) ?>"
                               placeholder="Código del cupón"
                               style="width:100%;text-transform:uppercase;font-family:var(--font-mono);letter-spacing:1px"
                               autocomplete="off">
                    </div>
                    <p class="small muted" style="margin:8px 0 0">El descuento se aplicará automáticamente al pulsar "Pagar".</p>
                </details>
                <?php endif; ?>

                <div class="btn-row">
                    <button class="btn dark lg block" type="submit" name="action" value="pay_stripe"><?= ic('send', 18) ?> Pagar <?= $finalEur ?> € y activar licencia</button>
                </div>
            </form>
            <p class="small muted" style="margin:12px 0 0;text-align:center">Serás redirigido a la página segura de Stripe para completar el pago.</p>
        </div>
        <?php endif; ?>

        <div class="grid-sidebar">
            <div>
                <div class="card">
                    <div class="space-between">
                        <div>
                            <div class="small muted" style="margin-bottom:4px">Estado de la licencia</div>
                            <div style="font-size:1.5rem;font-weight:700"><?= h($statusLabel) ?></div>
                        </div>
                        <span class="badge dot <?= h($statusBadge) ?>"><?= h($centralStatus !== '' ? $centralStatus : '—') ?></span>
                    </div>

                    <?php if ($centralMsg !== ''): ?>
                        <div class="alert info" style="margin-top:14px"><div><strong>Mensaje del operador:</strong><br><?= nl2br(h($centralMsg)) ?></div></div>
                    <?php endif; ?>

                    <hr>

                    <div class="kv">
                        <div>Dominio</div><div><code><?= h($_SERVER['HTTP_HOST'] ?? '') ?></code></div>
                        <div>Empresa</div><div><?= h(empresa()) ?></div>
                        <div>UID de cliente</div><div><code style="font-size:.85rem"><?= h($centralUid) ?></code></div>
                        <div>Servidor</div><div><code><?= h($centralUrl) ?></code></div>
                        <div>Registrado</div><div class="small muted"><?= h($centralRegAt) ?> UTC</div>
                        <div>Última confirmación</div><div class="small muted"><?= h($centralLastOk) ?: '—' ?></div>
                        <div>Última consulta</div><div class="small muted"><?= h($centralLastCheck) ?: '—' ?></div>
                    </div>

                    <hr>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="central_refresh">
                        <div class="btn-row"><button class="btn dark" type="submit">Refrescar estado ahora</button></div>
                    </form>
                </div>
            </div>

            <aside>
                <div class="card">
                    <h3 style="margin-top:0">¿Qué significa cada estado?</h3>
                    <ul class="clean" style="font-size:.92rem">
                        <li><span class="badge dot resuelta">active</span><span>El canal está operativo y los usuarios pueden presentar denuncias.</span></li>
                        <li><span class="badge dot overdue">inactive</span><span>El operador ha desactivado el canal. Los usuarios ven una página de mantenimiento.</span></li>
                    </ul>
                    <hr>
                    <p class="small muted" style="margin:0">Si el estado es incorrecto o necesitas soporte, contacta con el operador del servicio.</p>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</main>
<?php render_footer(true); ?>
