<?php

declare(strict_types=1);

return [
    // HR: Slanje je početno isključeno dok administrator ne unese SMTP podatke.
    // EN: Delivery is disabled until an administrator supplies SMTP settings.
    'enabled' => false,
    'smtp' => [
        'host' => '',
        'port' => 587,
        'encryption' => 'starttls',
        'username' => '',
        'password' => '',
        'connect_timeout' => 15,
        'io_timeout' => 30,
        'verify_peer' => true,
        'allow_self_signed' => false,
    ],
    'sender' => [
        'email' => '',
        'name' => 'HeartPhrame',
    ],
    'application_base_url' => '',
    'notifications_enabled' => true,
    'worker' => [
        'max_attempts' => 5,
        'retry_delay_seconds' => 60,
    ],
    'menu' => [
        'auto_register_settings' => true,
    ],
];
