<?php
declare(strict_types=1);
// Migraciones idempotentes — añaden columnas/tablas si faltan.

function column_exists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->query("PRAGMA table_info({$table})");
    foreach ($st->fetchAll() as $r) {
        if (($r['name'] ?? '') === $col) return true;
    }
    return false;
}

function run_migrations(): void {
    $pdo = db();

    // tenants_config: nuevas columnas
    $extra = [
        'telefono_canal'      => 'TEXT NULL',
        'presencial_email'    => 'TEXT NULL',
        'dpo_nombre'          => 'TEXT NULL',
        'dpo_email'           => 'TEXT NULL',
        'dpia_path'           => 'TEXT NULL',
        'dpia_fecha'          => 'TEXT NULL',
        'rat_path'            => 'TEXT NULL',
        'rat_fecha'           => 'TEXT NULL',
        'idioma_default'      => "TEXT NOT NULL DEFAULT 'es'",
        'accesibilidad_extra' => 'TEXT NULL',
        // Central server (canaleseticos.es)
        'central_tenant_uid'  => 'TEXT NULL',
        'central_license_key' => 'TEXT NULL',
        'central_status'      => "TEXT NULL",
        'central_status_msg'  => 'TEXT NULL',
        'central_last_check'  => 'TEXT NULL',
        'central_last_ok'     => 'TEXT NULL',
        'central_registered_at' => 'TEXT NULL',
        'last_paid_session_id'  => 'TEXT NULL',
        'last_paid_amount'      => 'INTEGER NULL',
        'last_paid_currency'    => 'TEXT NULL',
        'last_paid_at'          => 'TEXT NULL',
        'last_paid_coupon'      => 'TEXT NULL',
        'last_paid_original'    => 'INTEGER NULL',
    ];
    foreach ($extra as $col => $def) {
        if (!column_exists($pdo, 'tenants_config', $col)) {
            try { $pdo->exec("ALTER TABLE tenants_config ADD COLUMN {$col} {$def}"); } catch (Throwable $e) {}
        }
    }

    // Libro-registro (art. 26 Ley 2/2023) — retención 10 años, solo metadatos
    $pdo->exec("CREATE TABLE IF NOT EXISTS libro_registro (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        report_id INTEGER NULL,
        codigo_seguimiento TEXT NOT NULL,
        categoria TEXT NOT NULL,
        canal TEXT NOT NULL DEFAULT 'web',
        relacion TEXT NULL,
        fecha_recepcion TEXT NOT NULL,
        fecha_acuse TEXT NULL,
        fecha_resolucion TEXT NULL,
        estado_final TEXT NULL,
        resumen_actuaciones TEXT NULL,
        archivable_desde TEXT NOT NULL,
        hash_integridad TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lr_fecha ON libro_registro(fecha_recepcion)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lr_codigo ON libro_registro(codigo_seguimiento)");

    // Solicitudes de reunión presencial (Directiva UE art. 9.2)
    $pdo->exec("CREATE TABLE IF NOT EXISTS reunion_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        codigo_seguimiento TEXT NOT NULL UNIQUE,
        motivo_enc TEXT NOT NULL,
        contacto_enc TEXT NULL,
        preferencia_horario TEXT NULL,
        estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','agendada','celebrada','cancelada')),
        fecha_agenda TEXT NULL,
        notas_gestor_enc TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rr_estado ON reunion_requests(estado, created_at)");

    // Incidentes de seguridad (RGPD art. 33 — notificación 72h)
    $pdo->exec("CREATE TABLE IF NOT EXISTS security_incidents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        fecha_deteccion TEXT NOT NULL,
        tipo TEXT NOT NULL,
        descripcion TEXT NOT NULL,
        datos_afectados TEXT NULL,
        personas_afectadas INTEGER NULL,
        medidas_adoptadas TEXT NULL,
        notificado_aepd TINYINT NOT NULL DEFAULT 0,
        fecha_notificacion_aepd TEXT NULL,
        notificado_afectados TINYINT NOT NULL DEFAULT 0,
        cerrado TINYINT NOT NULL DEFAULT 0,
        user_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_si_fecha ON security_incidents(fecha_deteccion)");

    // Cuentas de denunciantes identificados
    $pdo->exec("CREATE TABLE IF NOT EXISTS reporters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        nombre_enc TEXT NULL,
        telefono_enc TEXT NULL,
        pass_hash TEXT NOT NULL,
        activo TINYINT NOT NULL DEFAULT 1,
        failed_logins INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT NULL,
        last_login TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reporters_email ON reporters(email)");

    // reports: añadir columnas para denuncias identificadas
    $reportsExtra = [
        'reporter_id'   => 'INTEGER NULL',
        'identificada'  => 'TINYINT NOT NULL DEFAULT 0',
    ];
    foreach ($reportsExtra as $col => $def) {
        if (!column_exists($pdo, 'reports', $col)) {
            try { $pdo->exec("ALTER TABLE reports ADD COLUMN {$col} {$def}"); } catch (Throwable $e) {}
        }
    }
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_reporter ON reports(reporter_id)");

    // tenants_config: SMTP config
    $smtpCols = [
        'smtp_host'     => 'TEXT NULL',
        'smtp_port'     => 'INTEGER NOT NULL DEFAULT 587',
        'smtp_user'     => 'TEXT NULL',
        'smtp_pass_enc' => 'TEXT NULL',
        'smtp_from'     => 'TEXT NULL',
        'smtp_secure'   => "TEXT NOT NULL DEFAULT 'tls'",
    ];
    foreach ($smtpCols as $col => $def) {
        if (!column_exists($pdo, 'tenants_config', $col)) {
            try { $pdo->exec("ALTER TABLE tenants_config ADD COLUMN {$col} {$def}"); } catch (Throwable $e) {}
        }
    }

    // users: columnas para recuperación de contraseña
    $usersExtra = [
        'reset_token_hash'    => 'TEXT NULL',
        'reset_token_expires' => 'TEXT NULL',
    ];
    foreach ($usersExtra as $col => $def) {
        if (!column_exists($pdo, 'users', $col)) {
            try { $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}"); } catch (Throwable $e) {}
        }
    }

    // Códigos de recuperación 2FA (un solo uso)
    $pdo->exec("CREATE TABLE IF NOT EXISTS totp_recovery_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        code_hash TEXT NOT NULL,
        created_at TEXT NOT NULL,
        used_at TEXT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_trc_user ON totp_recovery_codes(user_id)");
}

// Ejecutar migraciones una sola vez por carga si config.php existe
if (!empty($GLOBALS['CONFIG']) && empty($GLOBALS['__migrated'])) {
    try { run_migrations(); $GLOBALS['__migrated'] = true; } catch (Throwable $e) { error_log('migrations error: ' . $e->getMessage()); }
}
