<?php
$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'hall1500',
    'name' => $params['appName'],
    // ชื่อระบบที่แอดมินตั้งไว้ในหลังบ้าน
    'on beforeRequest' => function () {
        Yii::$app->name = \app\models\Setting::siteName();
    },
    'basePath' => dirname(__DIR__),
    'language' => 'th-TH',
    'timeZone' => 'Asia/Bangkok',
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => env('COOKIE_VALIDATION_KEY', 'dev-only-key-change-me'),
            'parsers' => ['application/json' => 'yii\web\JsonParser'],
        ],
        'cache' => ['class' => 'yii\caching\FileCache'],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
            'loginUrl' => ['/site/login'],
        ],
        'errorHandler' => ['errorAction' => 'site/error'],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                ['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']],
                ['class' => 'yii\log\FileTarget', 'categories' => ['payment', 'line'], 'logFile' => '@runtime/logs/payment.log', 'levels' => ['error', 'warning', 'info']],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index',
                'event/<id:\d+>' => 'event/view',
                'booking/<code:[A-Z0-9-]+>' => 'booking/view',
                'ticket/<code:[A-Z0-9-]+>' => 'booking/ticket',
            ],
        ],
        'assetManager' => [
            // asset-packagist ไม่จำเป็น: ใช้ jQuery จาก CDN
            'bundles' => [
                'yii\web\JqueryAsset' => [
                    'sourcePath' => null,
                    'js' => ['https://code.jquery.com/jquery-3.7.1.min.js'],
                ],
            ],
        ],
        'formatter' => [
            'locale' => 'th-TH@calendar=buddhist',
            'calendar' => \IntlDateFormatter::TRADITIONAL, // แสดงปี พ.ศ.
            'defaultTimeZone' => 'Asia/Bangkok',
            'currencyCode' => 'THB',
            'datetimeFormat' => 'php:j M Y H:i',
            'dateFormat' => 'php:j M Y',
        ],
        'lineLogin' => [
            'class' => 'app\components\LineLogin',
            'channelId' => $params['line']['channelId'],
            'channelSecret' => $params['line']['channelSecret'],
            'botPrompt' => $params['line']['botPrompt'],
        ],
        'lineMessaging' => [
            'class' => 'app\components\LineMessaging',
            'accessToken' => $params['line']['messagingToken'],
        ],
        'booking' => ['class' => 'app\components\BookingService'],
        'omise' => [
            'class' => 'app\components\OmiseGateway',
            'publicKey' => $params['payment']['omisePublicKey'],
            'secretKey' => $params['payment']['omiseSecretKey'],
        ],
    ],
    'container' => [
        'definitions' => [
            'yii\widgets\LinkPager' => [
                'options' => ['class' => 'pagination justify-content-center'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link'],
                'disabledListItemSubTagOptions' => ['class' => 'page-link'],
            ],
            'yii\grid\GridView' => [
                'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
            ],
        ],
    ],
    'params' => $params,
];

return $config;
