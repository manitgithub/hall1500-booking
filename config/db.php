<?php
return [
    'class' => 'yii\db\Connection',
    'dsn' => env('DB_DSN', 'mysql:host=127.0.0.1;dbname=hall1500'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
    'enableSchemaCache' => YII_ENV_PROD,
    'schemaCacheDuration' => 3600,
    'on afterOpen' => function ($event) {
        // ให้เวลาใน MySQL ตรงกับเวลาไทย
        $event->sender->createCommand("SET time_zone = '+07:00'")->execute();
    },
];
