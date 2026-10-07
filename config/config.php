<?php
// CIRITAS configuration defaults. Override with config/local.php or environment settings.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'CHANGE_ME',
        'user' => 'CHANGE_ME',
        'password' => 'CHANGE_ME',
        'charset' => 'latin1',
    ],
    'site' => [
        'base_url' => '',
        'uploads_path' => __DIR__ . '/../Uploads',
        'uploads_url' => '/Uploads/',
        'photos_path' => __DIR__ . '/../photos',
        'photos_url' => '/photos/',
    ],
    'admin' => [
        'username' => getenv('CIRITAS_ADMIN_USER') ?: '',
        'password_hash' => getenv('CIRITAS_ADMIN_PASSWORD_HASH') ?: '',
    ],
];
