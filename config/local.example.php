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
        'enabled' => false,
        'from' => 'site@example.com',
        'order_to' => 'sales@example.com',
    ],
];
