<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Cliente del servidor central (canaleseticos.es).
 * Firma HMAC-SHA256, anti-replay con nonce + timestamp, cache local 1h.
 */

const CENTRAL_DEFAULT_URL   = 'https://canaleseticos.es';
const CENTRAL_API_BASE      = '/api/v1';
const CENTRAL_HTTP_TIMEOUT  = 3;      // duro: nunca más de 3 s bloqueando el request
const CENTRAL_CHECK_TTL     = 60;     // 1 min de caché del estado (refresco rápido tras cambios)
const CENTRAL_FAIL_BACKOFF  = 600;    // si falla, espera 10 min antes del siguiente intento
const CENTRAL_GRACE_SECONDS = 259200; // 3 días: si el central cae, se confía en el último estado
const CENTRAL_CLIENT_VER    = '1.0';

function central_url(): string {
    $u = (string)($GLOBALS['CONFIG']['central_url'] ?? CENTRAL_DEFAULT_URL);
    return rtrim($u, '/');
}

function central_log(string $msg, array $ctx = []): void {
    $dir = APP_ROOT . '/private';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $line = '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $msg;
    if ($ctx) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir . '/central_debug.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function central_cfg(): array {
    try {
        $r = db()->query("SELECT * FROM tenants_config WHERE id=1 LIMIT 1")->fetch();
        return $r ?: [];
    } catch (Throwable $e) { return []; }
}

function central_save(array $patch): void {
    if (!$patch) return;
    $cols = array_keys($patch);
    $sets = [];
    foreach ($cols as $c) $sets[] = "$c = ?";
    $sql = "UPDATE tenants_config SET " . implode(',', $sets) . " WHERE id=1";
    $st = db()->prepare($sql);
    $st->execute(array_values($patch));
}

/**
 * Firma una petición siguiendo el contrato del servidor central.
 * Devuelve un array de cabeceras listas para enviar.
 */
function central_sign(string $method, string $path, string $body, string $tenantUid, string $licenseKey): array {
    $ts    = (string)time();
    $nonce = bin2hex(random_bytes(16));
    $bodyHash = hash('sha256', $body);
    $signing  = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $bodyHash;
    $sig      = hash_hmac('sha256', $signing, $licenseKey);
    return [
        'X-Tenant-Uid: ' . $tenantUid,
        'X-Timestamp: ' . $ts,
        'X-Nonce: ' . $nonce,
        'X-Signature: ' . $sig,
    ];
}

/**
 * HTTP request. Devuelve ['ok'=>bool, 'status'=>int, 'data'=>array|null, 'err'=>string].
 */
function central_http(string $method, string $path, array $payload = [], array $extraHeaders = []): array {
    // Libera el lock de sesión mientras hacemos la llamada remota, para no bloquear
    // otras peticiones del mismo usuario (p.ej. un POST de login mientras un GET ronda).
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    $url = central_url() . $path;
    $body = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '';
    $headers = array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: CanalEtico-Client/' . CENTRAL_CLIENT_VER,
    ], $extraHeaders);

    central_log('http_start', [
        'method' => strtoupper($method),
        'path'   => $path,
        'body'   => $payload ?: null,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_CONNECTTIMEOUT => CENTRAL_HTTP_TIMEOUT,
        CURLOPT_TIMEOUT        => CENTRAL_HTTP_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        central_log('http_network_err', ['err' => $err]);
        return ['ok' => false, 'status' => 0, 'data' => null, 'err' => 'network: ' . $err];
    }
    $data = json_decode((string)$resp, true);
    $ok   = $code >= 200 && $code < 300 && is_array($data) && !empty($data['ok']);
    central_log('http_done', [
        'status'       => $code,
        'ok'           => $ok,
        'resp_preview' => substr((string)$resp, 0, 1000),
        'parsed_type'  => gettype($data),
    ]);
    return [
        'ok'     => $ok,
        'status' => $code,
        'data'   => is_array($data) ? $data : null,
        'err'    => $ok ? '' : (($data['err'] ?? '') ?: ('http_' . $code)),
    ];
}

/**
 * Registra la instalación en el servidor central.
 * Llamar una vez al finalizar install.php.
 */
function central_register(string $host, string $empresa, string $adminEmail): array {
    $payload = [
        'host'        => $host,
        'empresa'     => $empresa,
        'admin_email' => $adminEmail,
        'version'     => CENTRAL_CLIENT_VER,
    ];
    $r = central_http('POST', CENTRAL_API_BASE . '/tenants/register', $payload);
    if (!$r['ok']) return $r;
    $d = $r['data'];
    if (empty($d['tenant_uid']) || empty($d['license_key'])) {
        return ['ok' => false, 'status' => $r['status'], 'data' => $d, 'err' => 'missing_fields'];
    }
    $now = gmdate('Y-m-d H:i:s');
    central_save([
        'central_tenant_uid'    => (string)$d['tenant_uid'],
        'central_license_key'   => (string)$d['license_key'],
        'central_status'        => (string)($d['status'] ?? 'inactive'),
        'central_status_msg'    => isset($d['message']) ? (string)$d['message'] : null,
        'central_last_check'    => $now,
        'central_last_ok'       => $now,
        'central_registered_at' => $now,
    ]);
    audit('central_registered', null, null, ['uid' => $d['tenant_uid'], 'status' => $d['status'] ?? '']);
    return $r;
}

/**
 * Consulta el estado. Firmada. Actualiza cache local.
 */
function central_fetch_status(): array {
    $cfg = central_cfg();
    $uid = (string)($cfg['central_tenant_uid'] ?? '');
    $key = (string)($cfg['central_license_key'] ?? '');
    if ($uid === '' || $key === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'err' => 'not_registered'];
    }
    $path = CENTRAL_API_BASE . '/tenants/status';
    $headers = central_sign('GET', $path, '', $uid, $key);
    $r = central_http('GET', $path, [], $headers);
    $now = gmdate('Y-m-d H:i:s');
    if ($r['ok']) {
        central_save([
            'central_status'     => (string)($r['data']['status'] ?? 'inactive'),
            'central_status_msg' => isset($r['data']['status_message']) ? (string)$r['data']['status_message'] : null,
            'central_last_check' => $now,
            'central_last_ok'    => $now,
        ]);
    } elseif (($r['err'] ?? '') === 'tenant_unknown' || $r['status'] === 404) {
        // El central ya no conoce este tenant (fue borrado o caducó).
        // Limpiamos la identidad local para permitir un re-registro limpio.
        central_save([
            'central_tenant_uid'    => null,
            'central_license_key'   => null,
            'central_status'        => null,
            'central_status_msg'    => null,
            'central_registered_at' => null,
            'central_last_check'    => $now,
            'central_last_ok'       => null,
        ]);
        audit('central_identity_cleared', null, null, ['reason' => 'tenant_unknown', 'old_uid' => $uid]);
    } else {
        // Guardamos que intentamos consultar, pero no borramos el último estado conocido
        central_save(['central_last_check' => $now]);
    }
    return $r;
}

/**
 * Hace un check perezoso (no más de 1 vez por CENTRAL_CHECK_TTL).
 * Seguro para llamar en cada request.
 */
function central_lazy_check(): void {
    $cfg = central_cfg();
    if (empty($cfg['central_tenant_uid'])) return; // aún no instalado
    $lastCheck = !empty($cfg['central_last_check']) ? strtotime((string)$cfg['central_last_check'] . ' UTC') : 0;
    $lastOk    = !empty($cfg['central_last_ok'])    ? strtotime((string)$cfg['central_last_ok']    . ' UTC') : 0;
    // Si la última llamada fue un OK reciente, no repetir durante TTL completo.
    // Si la última llamada falló, solo reintentar tras el backoff.
    $diff = time() - $lastCheck;
    if ($lastCheck === $lastOk && $diff < CENTRAL_CHECK_TTL) return;
    if ($lastCheck > $lastOk && $diff < CENTRAL_FAIL_BACKOFF) return;
    try { central_fetch_status(); } catch (Throwable $e) { /* silencioso */ }
}

/**
 * Devuelve el estado efectivo que debe aplicar el cliente.
 * - Si no hay registro, devuelve 'unregistered' (permite funcionar — fase de desarrollo)
 * - Si el central devolvió un estado en los últimos CENTRAL_GRACE_SECONDS, se respeta
 * - Si no se ha podido consultar en mucho tiempo, fail-open con warning
 */
function central_effective_status(): string {
    $cfg = central_cfg();
    $s = (string)($cfg['central_status'] ?? '');
    if ($s === '' && empty($cfg['central_tenant_uid'])) return 'unregistered';
    $lastOk = !empty($cfg['central_last_ok']) ? strtotime((string)$cfg['central_last_ok'] . ' UTC') : 0;
    if ($lastOk && (time() - $lastOk) > CENTRAL_GRACE_SECONDS) {
        // Demasiado tiempo sin poder confirmar con el central — modo degradado
        return 'stale';
    }
    return $s !== '' ? $s : 'inactive';
}

/**
 * Notifica al servidor central que el tenant ha completado un pago Stripe.
 * El servidor central debe verificar la sesión Stripe independientemente
 * y cambiar el estado del tenant a 'active'. Si se proporciona coupon_code,
 * el central debe registrar el uso en la tabla coupon_uses.
 */
function central_notify_payment(string $sessionId, int $amountCents = 0, string $currency = 'eur', ?string $couponCode = null, int $originalAmountCents = 0): array {
    $cfg = central_cfg();
    $uid = (string)($cfg['central_tenant_uid'] ?? '');
    $key = (string)($cfg['central_license_key'] ?? '');
    if ($uid === '' || $key === '') return ['ok' => false, 'err' => 'not_registered'];
    $path = CENTRAL_API_BASE . '/tenants/payment';
    $payload = [
        'stripe_session_id' => $sessionId,
        'amount_cents'      => $amountCents,
        'currency'          => $currency,
    ];
    if ($couponCode !== null && $couponCode !== '') {
        $payload['coupon_code']           = strtoupper($couponCode);
        $payload['original_amount_cents'] = $originalAmountCents > 0 ? $originalAmountCents : $amountCents;
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $headers = central_sign('POST', $path, (string)$body, $uid, $key);
    $r = central_http('POST', $path, $payload, $headers);
    if ($r['ok']) {
        $now = gmdate('Y-m-d H:i:s');
        central_save([
            'central_status'     => (string)($r['data']['status'] ?? 'inactive'),
            'central_status_msg' => isset($r['data']['status_message']) ? (string)$r['data']['status_message'] : null,
            'central_last_check' => $now,
            'central_last_ok'    => $now,
        ]);
        audit('central_payment_notified', null, null, [
            'session' => $sessionId,
            'status'  => $r['data']['status'] ?? '',
            'coupon'  => $couponCode ?: null,
        ]);
    }
    return $r;
}

/**
 * Valida un cupón de descuento contra el servidor central.
 * No consume el cupón — solo comprueba que es aplicable.
 * El consumo real se hace al notificar el pago (central_notify_payment con coupon_code).
 *
 * Devuelve:
 *   ['ok'=>true, 'data'=>['code','type','value','discount_cents','final_amount_cents','currency','description']]
 *   ['ok'=>false, 'err'=>'coupon_not_found|coupon_expired|coupon_max_uses_reached|coupon_already_used_by_tenant|...']
 */
function central_validate_coupon(string $code, int $originalAmountCents, string $currency = 'eur'): array {
    $cfg = central_cfg();
    $uid = (string)($cfg['central_tenant_uid'] ?? '');
    $key = (string)($cfg['central_license_key'] ?? '');
    if ($uid === '' || $key === '') return ['ok' => false, 'err' => 'not_registered'];
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'err' => 'empty_code'];
    $path = CENTRAL_API_BASE . '/coupons/validate';
    $payload = [
        'code'                  => $code,
        'original_amount_cents' => $originalAmountCents,
        'currency'              => $currency,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $headers = central_sign('POST', $path, (string)$body, $uid, $key);
    $r = central_http('POST', $path, $payload, $headers);
    return $r;
}

/**
 * ¿El tenant está bloqueado (maintenance.php)?
 * Todo lo que no sea 'active' se considera bloqueado. Esto incluye:
 * - 'unregistered' (instalación fresca que no pudo registrarse)
 * - 'inactive' (operador desactivó o tenant nunca activado)
 * - 'stale' (sin poder confirmar con el central durante > grace period)
 */
function central_is_blocked(): bool {
    return central_effective_status() !== 'active';
}
