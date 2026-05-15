<?php
declare(strict_types=1);
// Ejecutar vía CLI (cron Plesk) o desde admin. Anonimiza denuncias >3 meses salvo las marcadas en_investigacion.
require_once __DIR__ . '/bootstrap.php';

function run_retention(): array {
    $pdo = db();
    $cutoff = gmdate('Y-m-d H:i:s', strtotime('-3 months'));
    $st = $pdo->prepare("SELECT id, codigo_seguimiento FROM reports
        WHERE anonimizada=0 AND en_investigacion=0
        AND (estado IN ('resuelta','desestimada') OR created_at < ?)
        AND created_at < ?");
    $st->execute([$cutoff, $cutoff]);
    $rows = $st->fetchAll();
    $count = 0;
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        // Borrar adjuntos físicamente
        $ats = $pdo->prepare("SELECT filename_disk FROM report_attachments WHERE report_id=?");
        $ats->execute([$id]);
        foreach ($ats->fetchAll() as $a) {
            $p = APP_ROOT . '/uploads/' . basename($a['filename_disk']);
            if (is_file($p)) @unlink($p);
        }
        $pdo->prepare("DELETE FROM report_attachments WHERE report_id=?")->execute([$id]);
        // Sustituir campos cifrados por marcador
        $null = enc('[ANONIMIZADA]');
        $pdo->prepare("UPDATE reports SET titulo_enc=?, descripcion_enc=?, anonimizada=1, updated_at=? WHERE id=?")
            ->execute([$null, $null, gmdate('Y-m-d H:i:s'), $id]);
        $pdo->prepare("UPDATE communications SET body_enc=? WHERE report_id=?")
            ->execute([$null, $id]);
        audit('retention_anonymize', null, $id, ['codigo' => $r['codigo_seguimiento']]);
        $count++;
    }
    return ['anonimizadas' => $count, 'cutoff' => $cutoff];
}

if (PHP_SAPI === 'cli') {
    $r = run_retention();
    echo json_encode($r) . PHP_EOL;
}
