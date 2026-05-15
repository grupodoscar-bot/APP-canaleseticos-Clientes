<?php
// Plantilla de configuración. install.php genera el config.php real.
// Modo SQLite (por defecto, sin configuración):
return [
    'db' => [
        'driver' => 'sqlite',
        'path'   => __DIR__ . '/private/canal.sqlite',
    ],
    // Alternativa MySQL:
    // 'db' => [
    //     'driver'  => 'mysql',
    //     'host'    => 'localhost',
    //     'name'    => 'canal_db',
    //     'user'    => 'canal_user',
    //     'pass'    => '',
    //     'charset' => 'utf8mb4',
    // ],
    'app_key'          => '', // base64 de 32 bytes aleatorios (AES-256-GCM)
    'hmac_key'         => '', // base64 de 32 bytes aleatorios
    'empresa'          => 'Empresa',
    'email_compliance' => 'compliance@example.com',
    'email_from'       => 'no-reply@example.com',
    'smtp'             => null,
    'installed_at'     => null,
    // Servidor central de licencias (generado por install.php)
    'central_url'      => 'https://canaleseticos.es',
    // Stripe (clave restringida rk_test_… / rk_live_…)
    'stripe_secret_key'=> '',
    'stripe_price_eur' => 12000, // céntimos
];
