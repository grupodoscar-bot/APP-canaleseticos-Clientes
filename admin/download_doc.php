<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$me = require_auth();
require_admin($me);

$kind = $_GET['kind'] ?? '';
if (!in_array($kind, ['dpia','rat'], true)) { http_response_code(400); exit('Tipo inválido'); }

$rel = (string)(db()->query("SELECT {$kind}_path FROM tenants_config WHERE id=1")->fetchColumn() ?: '');
if (!$rel) { http_response_code(404); exit('No existe'); }

$abs = APP_ROOT . '/' . ltrim($rel, '/');
if (!is_file($abs)) { http_response_code(410); exit('Fichero no disponible'); }

try { $data = dec_file($abs); } catch (Throwable $e) { http_response_code(500); exit('Error al descifrar'); }

audit('doc_downloaded', (int)$me['id'], null, ['kind' => $kind]);

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($data));
header('Content-Disposition: attachment; filename="' . strtoupper($kind) . '.pdf"');
header('X-Content-Type-Options: nosniff');
echo $data;
