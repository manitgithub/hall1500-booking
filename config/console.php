<?php
$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

return [
    'id' => 'hall1500-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'Asia/Bangkok',
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@web' => '@app/web',
        '@webroot' => '@app/web',
    ],
    'components' => [
        'cache' => ['class' => 'yii\caching\FileCache'],
        'log' => ['targets' => [['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']]]],
        'db' => $db,
        'booking' => ['class' => 'app\components\BookingService'],
        'lineMessaging' => [
            'class' => 'app\components\LineMessaging',
            'accessToken' => $params['line']['messagingToken'],
        ],
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => '@app/migrations',
        ],
    ],
    'params' => $params,
];
