<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$me = require_auth();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT a.*, r.codigo_seguimiento FROM report_attachments a JOIN reports r ON r.id=a.report_id WHERE a.id=? LIMIT 1");
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('No encontrado'); }

$path = APP_ROOT . '/uploads/' . basename($a['filename_disk']);
if (!is_file($path)) { http_response_code(410); exit('Archivo no disponible'); }

try {
    $data = dec_file($path);
} catch (Throwable $e) {
    http_response_code(500); exit('Error al descifrar');
}

$name = (string)(dec($a['filename_original_enc']) ?? 'archivo');
// Sanitizar nombre
$name = preg_replace('/[^A-Za-z0-9._\- ]+/', '_', $name) ?: 'archivo';

audit('attachment_download', (int)$me['id'], (int)$a['report_id'], ['att_id' => $id]);

header('Content-Type: ' . $a['mime']);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
echo $data;
