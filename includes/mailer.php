<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Email del responsable de cumplimiento (almacenado en DB, no en config.php). */
function compliance_email(): ?string {
    try {
        $v = db()->query("SELECT email_compliance FROM tenants_config WHERE id=1")->fetchColumn();
        return ($v && $v !== '') ? (string)$v : null;
    } catch (Throwable $e) { return null; }
}

/** Email del denunciante identificado para una denuncia concreta. */
function reporter_email_for_report(array $report): ?string {
    if (empty($report['identificada']) || empty($report['reporter_id'])) return null;
    try {
        $st = db()->prepare("SELECT email, activo FROM reporters WHERE id=? LIMIT 1");
        $st->execute([(int)$report['reporter_id']]);
        $row = $st->fetch();
        if (!$row || !$row['activo']) return null;
        return (string)$row['email'];
    } catch (Throwable $e) { return null; }
}

/** Dirección "From" por defecto: canaldenuncias@dominio-base */
function smtp_default_from(): string {
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $parts  = explode('.', $host);
    $domain = count($parts) >= 3 ? implode('.', array_slice($parts, 1)) : $host;
    return 'canaldenuncias@' . $domain;
}

// ---------------------------------------------------------------------------
// SMTP configuration (tenants_config DB)
// ---------------------------------------------------------------------------

function smtp_config(): ?array {
    try {
        $row = db()->query(
            "SELECT smtp_host, smtp_port, smtp_user, smtp_pass_enc, smtp_from, smtp_secure
             FROM tenants_config WHERE id=1"
        )->fetch();
        if (!$row || empty($row['smtp_host'])) return null;
        return [
            'host'   => (string)$row['smtp_host'],
            'port'   => (int)($row['smtp_port'] ?? 587),
            'user'   => (string)($row['smtp_user'] ?? ''),
            'pass'   => $row['smtp_pass_enc'] ? (string)dec((string)$row['smtp_pass_enc']) : '',
            'from'   => (string)($row['smtp_from'] ?: smtp_default_from()),
            'secure' => (string)($row['smtp_secure'] ?? 'tls'),
        ];
    } catch (Throwable $e) { return null; }
}

// ---------------------------------------------------------------------------
// Low-level SMTP client (socket, STARTTLS / implicit-SSL / plain)
// ---------------------------------------------------------------------------

function smtp_send_raw(string $to, string $subject_raw, string $body, array $cfg): bool {
    $host    = $cfg['host'];
    $port    = (int)$cfg['port'];
    $secure  = $cfg['secure']; // 'tls'=STARTTLS, 'ssl'=implicit SSL, 'none'=plain
    $timeout = 15;

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'allow_self_signed' => false,
    ]]);
    $uri  = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $sock = @stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) return false;
    stream_set_timeout($sock, $timeout);

    // Read one SMTP response (handles multiline like "250-xxx\r\n250 OK\r\n")
    $rd = function() use ($sock): string {
        $buf = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $buf .= $line;
            // Final line: code followed by a space (not '-')
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $buf;
    };
    $wr = function(string $cmd) use ($sock, $rd): string {
        fwrite($sock, $cmd . "\r\n");
        return $rd();
    };

    // Server greeting
    if (substr($rd(), 0, 3) !== '220') { fclose($sock); return false; }

    $me = $_SERVER['SERVER_NAME'] ?? (gethostname() ?: 'canal.local');
    $r  = $wr('EHLO ' . $me);
    if (substr($r, 0, 3) !== '250') {
        $r = $wr('HELO ' . $me);
        if (substr($r, 0, 3) !== '250') { fclose($sock); return false; }
    }

    // STARTTLS upgrade
    if ($secure === 'tls') {
        if (substr($wr('STARTTLS'), 0, 3) !== '220') { fclose($sock); return false; }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock); return false;
        }
        $r = $wr('EHLO ' . $me);
        if (substr($r, 0, 3) !== '250') { fclose($sock); return false; }
    }

    // AUTH LOGIN
    if ($cfg['user'] !== '') {
        if (substr($wr('AUTH LOGIN'), 0, 3) !== '334') { fclose($sock); return false; }
        if (substr($wr(base64_encode($cfg['user'])), 0, 3) !== '334') { fclose($sock); return false; }
        if (substr($wr(base64_encode($cfg['pass'])), 0, 3) !== '235') { fclose($sock); return false; }
    }

    // Envelope
    if (substr($wr('MAIL FROM:<' . $cfg['from'] . '>'), 0, 3) !== '250') { fclose($sock); return false; }
    $rcpt = $wr('RCPT TO:<' . $to . '>');
    if (substr($rcpt, 0, 3) !== '250' && substr($rcpt, 0, 3) !== '251') { fclose($sock); return false; }
    if (substr($wr('DATA'), 0, 3) !== '354') { fclose($sock); return false; }

    // Headers
    $emp    = empresa();
    $from   = $cfg['from'];
    $subEnc = '=?UTF-8?B?' . base64_encode($subject_raw) . '?=';
    $msgId  = '<' . bin2hex(random_bytes(8)) . '@' . $me . '>';

    // Normalize line endings + dot-stuffing
    $bodyNorm  = str_replace(["\r\n", "\r"], "\n", $body);
    $bodyLines = explode("\n", $bodyNorm);
    $bodySmtp  = implode("\r\n", array_map(
        fn($l) => ($l !== '' && $l[0] === '.') ? '.' . $l : $l,
        $bodyLines
    ));

    $msg = "From: {$emp} Canal Etico <{$from}>\r\n"
         . "To: <{$to}>\r\n"
         . "Subject: {$subEnc}\r\n"
         . "Message-ID: {$msgId}\r\n"
         . "Date: " . gmdate('r') . "\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: 8bit\r\n"
         . "X-Mailer: CanalEtico/1.0\r\n"
         . "\r\n"
         . $bodySmtp;

    fwrite($sock, $msg . "\r\n.\r\n");
    $done = $rd();
    $wr('QUIT');
    fclose($sock);
    return substr($done, 0, 3) === '250';
}

// ---------------------------------------------------------------------------
// Public send_mail: SMTP if configured, else php mail()
// ---------------------------------------------------------------------------

function send_mail(string $to, string $subject, string $body): bool {
    $cfg = smtp_config();
    if ($cfg) {
        return smtp_send_raw($to, $subject, $body, $cfg);
    }
    // Fallback: PHP mail() — funciona si el servidor tiene sendmail/postfix configurado
    $from       = smtp_default_from();
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers    = implode("\r\n", [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: CanalEtico',
    ]);
    return @mail($to, $subjectEnc, $body, $headers);
}

// ---------------------------------------------------------------------------
// Notificaciones → Administrador / Compliance
// ---------------------------------------------------------------------------

/** Nueva denuncia recibida (al responsable de cumplimiento). */
function notify_new_report(string $codigo, string $categoria): void {
    $to = compliance_email();
    if (!$to) return;
    $emp = empresa();
    $url = app_url('admin/login.php');
    $body = "Se ha recibido una nueva denuncia en el canal ético de {$emp}.\n\n"
          . "Código: {$codigo}\nCategoría: {$categoria}\n\n"
          . "Entra al panel para revisarla: {$url}\n\n"
          . "Recuerda: tienes 7 días para enviar el acuse de recibo (Ley 2/2023).";
    send_mail($to, "[Canal Ético] Nueva denuncia — {$codigo}", $body);
}

/** Nuevo mensaje del denunciante (al administrador). */
function notify_new_message_admin(string $codigo): void {
    $to = compliance_email();
    if (!$to) return;
    $url = app_url('admin/login.php');
    send_mail(
        $to,
        "[Canal Ético] Nuevo mensaje del denunciante — {$codigo}",
        "Hay un nuevo mensaje del denunciante en la denuncia {$codigo}.\n\nPanel: {$url}"
    );
}

// ---------------------------------------------------------------------------
// Notificaciones → Denunciante identificado
// ---------------------------------------------------------------------------

/** Acuse de recibo enviado por el admin (al denunciante). */
function notify_acuse_reporter(array $report): void {
    $to = reporter_email_for_report($report);
    if (!$to) return;
    $emp = empresa();
    $cod = (string)$report['codigo_seguimiento'];
    $url = app_url('seguimiento.php?codigo=' . $cod);
    $body = "Hola,\n\n"
          . "El responsable de cumplimiento de {$emp} ha confirmado la recepción "
          . "de tu denuncia y ha iniciado su tramitación.\n\n"
          . "Código de seguimiento: {$cod}\n"
          . "Consulta el estado en: {$url}\n\n"
          . "Recibirás actualizaciones por este medio. El plazo máximo de resolución "
          . "es de 3 meses (prorrogable) según la Ley 2/2023.\n\n"
          . "Saludos,\n{$emp} — Canal Ético";
    send_mail($to, "[Canal Ético] Acuse de recibo — {$cod}", $body);
}

/** Cambio de estado de la denuncia (al denunciante). */
function notify_status_change_reporter(array $report, string $newStatus): void {
    $to = reporter_email_for_report($report);
    if (!$to) return;
    $emp = empresa();
    $cod = (string)$report['codigo_seguimiento'];
    $url = app_url('seguimiento.php?codigo=' . $cod);
    $labels = [
        'recibida'    => 'Recibida',
        'en_curso'    => 'En curso',
        'resuelta'    => 'Resuelta',
        'desestimada' => 'Desestimada',
        'bloqueada'   => 'Bloqueada',
    ];
    $label = $labels[$newStatus] ?? $newStatus;
    $body = "Hola,\n\n"
          . "El estado de tu denuncia en {$emp} ha sido actualizado.\n\n"
          . "Código de seguimiento: {$cod}\n"
          . "Nuevo estado: {$label}\n\n"
          . "Accede al portal para más información: {$url}\n\n"
          . "Saludos,\n{$emp} — Canal Ético";
    send_mail($to, "[Canal Ético] Estado actualizado a «{$label}» — {$cod}", $body);
}

/** El administrador ha enviado un mensaje (al denunciante). */
function notify_new_message_reporter(array $report): void {
    $to = reporter_email_for_report($report);
    if (!$to) return;
    $emp = empresa();
    $cod = (string)$report['codigo_seguimiento'];
    $url = app_url('seguimiento.php?codigo=' . $cod);
    // NO incluimos el contenido del mensaje por confidencialidad
    $body = "Hola,\n\n"
          . "El responsable de cumplimiento de {$emp} te ha enviado un mensaje "
          . "en relación a tu denuncia.\n\n"
          . "Código de seguimiento: {$cod}\n\n"
          . "Accede al portal para leerlo: {$url}\n\n"
          . "Por seguridad, los detalles solo están disponibles en el portal.\n\n"
          . "Saludos,\n{$emp} — Canal Ético";
    send_mail($to, "[Canal Ético] Tienes un nuevo mensaje — {$cod}", $body);
}

/** Confirmación de recepción al denunciante identificado tras enviar su denuncia. */
function notify_report_confirmation_reporter(string $to, string $codigo): void {
    $emp = empresa();
    $url = app_url('seguimiento.php?codigo=' . $codigo);
    $body = "Hola,\n\n"
          . "Tu denuncia en {$emp} ha sido registrada correctamente.\n\n"
          . "Código de seguimiento: {$codigo}\n"
          . "Seguimiento en: {$url}\n\n"
          . "El responsable de cumplimiento te enviará un acuse de recibo "
          . "en un plazo máximo de 7 días (Ley 2/2023).\n\n"
          . "Saludos,\n{$emp} — Canal Ético";
    send_mail($to, "[Canal Ético] Tu denuncia ha sido recibida — {$codigo}", $body);
}
