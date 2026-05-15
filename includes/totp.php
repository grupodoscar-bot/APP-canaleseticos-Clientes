<?php
declare(strict_types=1);
// TOTP RFC 6238 autocontenido (SHA1, 6 dígitos, 30s)

function base32_encode(string $bin): string {
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($bin); $i++) $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $c) {
        if (strlen($c) < 5) $c = str_pad($c, 5, '0', STR_PAD_RIGHT);
        $out .= $alph[bindec($c)];
    }
    return $out;
}
function base32_decode(string $s): string {
    $alph = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(str_replace(['=',' '], '', $s));
    $bits = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $v = strpos($alph, $s[$i]);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $out .= chr(bindec($byte));
    }
    return $out;
}
function totp_secret_new(): string {
    return base32_encode(random_bytes(20));
}
function totp_code(string $secretB32, ?int $time = null, int $period = 30, int $digits = 6): string {
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, base32_decode($secretB32), true);
    $off = ord($hash[19]) & 0x0f;
    $code = (((ord($hash[$off]) & 0x7f) << 24)
           | ((ord($hash[$off+1]) & 0xff) << 16)
           | ((ord($hash[$off+2]) & 0xff) << 8)
           |  (ord($hash[$off+3]) & 0xff)) % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $t = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $t + $i*30), $code)) return true;
    }
    return false;
}
function totp_uri(string $secret, string $label, string $issuer): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
// QR vía API externa NO se usa (CSP + privacidad). Mostramos texto + URI + link a aplicación.
