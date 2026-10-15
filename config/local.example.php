<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'password' => 'YOUR_DATABASE_PASSWORD',
        'charset' => 'latin1',
    ],
    'site' => [
        'uploads_path' => '/absolute/path/to/Uploads',
        'photos_path' => '/absolute/path/to/photos',
    ],
    'mail' => [
        'enabled' => true,
        'transport' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls', // tls, ssl or none
        'username' => 'site@example.com',
        'password' => 'YOUR_SMTP_PASSWORD',
        'timeout' => 15,
        'from' => 'site@example.com',
        'from_name' => 'ЦИРИТАС',
        'order_to' => 'sales@example.com',
        'support_to' => 'support@ciritas.ru',
    ],
];
