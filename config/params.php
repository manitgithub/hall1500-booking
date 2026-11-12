<?php
return [
    'appName' => 'ระบบจองที่นั่ง ห้องประชุม 1500',
    'appUrl' => rtrim(env('APP_URL', 'http://localhost:8080'), '/'),
    'devLogin' => (bool)env('DEV_LOGIN', false),

    'line' => [
        'channelId' => env('LINE_CHANNEL_ID'),
        'channelSecret' => env('LINE_CHANNEL_SECRET'),
        'botPrompt' => env('LINE_BOT_PROMPT'),
        'messagingToken' => env('LINE_MESSAGING_TOKEN'),
    ],

    'payment' => [
        'promptpayId' => env('PROMPTPAY_ID'),
        'promptpayName' => env('PROMPTPAY_NAME', ''),
        'omisePublicKey' => env('OMISE_PUBLIC_KEY'),
        'omiseSecretKey' => env('OMISE_SECRET_KEY'),
        'slipMaxSize' => 5 * 1024 * 1024,
    ],

    'booking' => [
        'holdMinutes' => 15,          // เวลาล็อกที่นั่งระหว่างรอชำระเงิน
        'maxSeatsPerBooking' => 10,
    ],
];
