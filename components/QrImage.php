<?php

namespace app\components;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Yii;
use yii\web\Response;

class QrImage
{
    public static function send(string $data, int $size = 320): Response
    {
        $qr = QrCode::create($data)
            ->setSize($size)
            ->setMargin(12)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium);
        $png = (new PngWriter())->write($qr)->getString();

        $res = Yii::$app->response;
        $res->format = Response::FORMAT_RAW;
        $res->headers->set('Content-Type', 'image/png');
        $res->headers->set('Cache-Control', 'private, max-age=300');
        $res->content = $png;
        return $res;
    }
}
