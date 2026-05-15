<?php
declare(strict_types=1);

function client_ip_hash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hmac_hash('ip:' . $ip);
}
function client_ua_hash(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hmac_hash('ua:' . $ua);
}

function audit(string $action, ?int $userId = null, ?int $reportId = null, array $meta = []): void {
    try {
        $pdo = db();
        $prev = $pdo->query("SELECT row_hash FROM audit_logs ORDER BY id DESC LIMIT 1")->fetchColumn();
        $prev = $prev ?: str_repeat('0', 64);
        $ts = gmdate('Y-m-d H:i:s');
        $ipH = client_ip_hash();
        $uaH = client_ua_hash();
        $metaJ = json_encode($meta, JSON_UNESCAPED_UNICODE);
        $payload = $prev . '|' . $ts . '|' . ($userId ?? '') . '|' . $action . '|' . ($reportId ?? '') . '|' . $ipH . '|' . $uaH . '|' . $metaJ;
        $row = hash('sha256', $payload);
        $st = $pdo->prepare("INSERT INTO audit_logs (user_id, action, report_id, ip_hash, ua_hash, meta, created_at, prev_hash, row_hash) VALUES (?,?,?,?,?,?,?,?,?)");
        $st->execute([$userId, $action, $reportId, $ipH, $uaH, $metaJ, $ts, $prev, $row]);
    } catch (Throwable $e) {
        // nunca romper flujo por auditoría
        error_log('audit error: ' . $e->getMessage());
    }
}
