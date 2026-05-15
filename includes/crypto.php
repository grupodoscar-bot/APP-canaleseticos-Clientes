<?php
declare(strict_types=1);

function app_key(): string {
    $k = base64_decode($GLOBALS['CONFIG']['app_key'] ?? '', true);
    if ($k === false || strlen($k) !== 32) throw new RuntimeException('app_key inválida');
    return $k;
}
function hmac_key(): string {
    $k = base64_decode($GLOBALS['CONFIG']['hmac_key'] ?? '', true);
    if ($k === false || strlen($k) !== 32) throw new RuntimeException('hmac_key inválida');
    return $k;
}

// AES-256-GCM → devuelve base64(iv || tag || ct)
function enc(?string $plain): ?string {
    if ($plain === null) return null;
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('enc fail');
    return base64_encode($iv . $tag . $ct);
}
function dec(?string $payload): ?string {
    if ($payload === null || $payload === '') return null;
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 28) return null;
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
    $pt = openssl_decrypt($ct, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $pt === false ? null : $pt;
}

// Cifrado de archivos en disco (streaming por bloques)
function enc_file(string $srcTmp, string $destPath): void {
    $iv = random_bytes(12); $tag = '';
    $data = file_get_contents($srcTmp);
    if ($data === false) throw new RuntimeException('read fail');
    $ct = openssl_encrypt($data, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('enc file');
    file_put_contents($destPath, $iv . $tag . $ct);
    @chmod($destPath, 0640);
}
function dec_file(string $path): string {
    $raw = file_get_contents($path);
    if ($raw === false || strlen($raw) < 28) throw new RuntimeException('read enc');
    $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
    $pt = openssl_decrypt($ct, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($pt === false) throw new RuntimeException('dec file');
    return $pt;
}

// Código de seguimiento XXXX-XXXX-XXXX (Crockford base32 sin chars ambiguos)
function codigo_seguimiento(): string {
    $alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        if ($i === 4 || $i === 8) $out .= '-';
        $out .= $alpha[random_int(0, strlen($alpha) - 1)];
    }
    return $out;
}

function hmac_hash(string $data): string {
    return hash_hmac('sha256', $data, hmac_key());
}
