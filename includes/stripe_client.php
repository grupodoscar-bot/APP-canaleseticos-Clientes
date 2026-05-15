<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Cliente Stripe ligero (solo cURL, sin Composer).
 * Usa la clave restringida almacenada en config.php: stripe_secret_key.
 */

const STRIPE_API = 'https://api.stripe.com/v1';
const STRIPE_HTTP_TIMEOUT = 15;

// Clave por defecto (test). Se usa si el config.php de la instalación no la define.
// En producción se sobreescribirá con la live key via config.php.
const STRIPE_DEFAULT_KEY = 'sk_test_51I6umeGzm8CG4SCCm2cEemjf9kOBrdnG8WtxLLW2GbpNkUeUPo5jgEeYFmDJVuY44AFXyvM27Gha3jAIHMR1afGY00HWueYP5Q';

function stripe_key(): string {
    $k = (string)($GLOBALS['CONFIG']['stripe_secret_key'] ?? '');
    return $k !== '' ? $k : STRIPE_DEFAULT_KEY;
}

function stripe_enabled(): bool {
    return stripe_key() !== '';
}

function stripe_log(string $msg, array $ctx = []): void {
    $dir = APP_ROOT . '/private';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $line = '[' . gmdate('Y-m-d H:i:s') . 'Z] ' . $msg;
    if ($ctx) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir . '/stripe_debug.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function stripe_http(string $method, string $path, array $params = []): array {
    $key = stripe_key();
    if ($key === '') return ['ok' => false, 'status' => 0, 'data' => null, 'err' => 'no_key'];

    $url = STRIPE_API . $path;
    $body = $params ? http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';

    stripe_log('http_start', ['method' => $method, 'path' => $path, 'key_prefix' => substr($key, 0, 8)]);

    // Liberar sesión para no bloquear
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();

    $ch = curl_init();
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2024-06-20',
        ],
        CURLOPT_CONNECTTIMEOUT => STRIPE_HTTP_TIMEOUT,
        CURLOPT_TIMEOUT        => STRIPE_HTTP_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'], true)) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    } elseif ($body !== '') {
        $opts[CURLOPT_URL] = $url . (str_contains($url, '?') ? '&' : '?') . $body;
    }
    curl_setopt_array($ch, $opts);

    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        stripe_log('http_network_err', ['err' => $err]);
        return ['ok' => false, 'status' => 0, 'data' => null, 'err' => 'network: ' . $err];
    }
    $data = json_decode((string)$resp, true);
    $ok = $code >= 200 && $code < 300;
    stripe_log('http_done', [
        'status' => $code,
        'ok' => $ok,
        'err' => $ok ? '' : (string)($data['error']['message'] ?? ('http_' . $code)),
        'type' => $data['error']['type'] ?? null,
        'resp_preview' => substr((string)$resp, 0, 400),
    ]);
    return [
        'ok' => $ok,
        'status' => $code,
        'data' => is_array($data) ? $data : null,
        'err' => $ok ? '' : (string)($data['error']['message'] ?? ('http_' . $code)),
    ];
}

/**
 * Crea una Checkout Session para la licencia del canal.
 * Devuelve ['ok'=>bool, 'url'=>string, 'session_id'=>string, 'err'=>string]
 */
function stripe_create_license_session(string $tenantUid, string $empresa, string $adminEmail, string $successUrl, string $cancelUrl, int $amountCents = 12000, string $currency = 'eur', ?string $couponCode = null, int $originalAmountCents = 0): array {
    $productName = 'Licencia Canal Ético · ' . $empresa;
    $productDesc = 'Activación del canal ético · Ley 2/2023';
    if ($couponCode !== null && $couponCode !== '') {
        $productDesc .= ' · Cupón ' . strtoupper($couponCode);
    }
    $params = [
        'mode'                         => 'payment',
        'success_url'                  => $successUrl,
        'cancel_url'                   => $cancelUrl,
        'customer_email'               => $adminEmail,
        'client_reference_id'          => $tenantUid,
        'metadata[tenant_uid]'         => $tenantUid,
        'metadata[empresa]'            => $empresa,
        'metadata[product]'            => 'canal_etico_licencia',
        'line_items[0][quantity]'      => 1,
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDesc,
    ];
    if ($couponCode !== null && $couponCode !== '') {
        $params['metadata[coupon_code]']           = strtoupper($couponCode);
        $params['metadata[original_amount_cents]'] = (string)($originalAmountCents > 0 ? $originalAmountCents : $amountCents);
    }
    $r = stripe_http('POST', '/checkout/sessions', $params);
    if (!$r['ok']) return ['ok' => false, 'url' => '', 'session_id' => '', 'err' => $r['err']];
    return [
        'ok'         => true,
        'url'        => (string)($r['data']['url'] ?? ''),
        'session_id' => (string)($r['data']['id'] ?? ''),
        'err'        => '',
    ];
}

/**
 * Recupera una Checkout Session para verificar el pago.
 */
function stripe_retrieve_session(string $sessionId): array {
    return stripe_http('GET', '/checkout/sessions/' . urlencode($sessionId));
}
