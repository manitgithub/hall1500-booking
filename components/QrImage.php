<?php

namespace app\components;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Yii;
use yii\web\Response;

class QrImage
{
    public static function png(string $data, int $size = 320): string
    {
        $qr = QrCode::create($data)
            ->setSize($size)
            ->setMargin(12)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium);
        return (new PngWriter())->write($qr)->getString();
    }

    public static function send(string $data, int $size = 320): Response
    {
        $res = Yii::$app->response;
        $res->format = Response::FORMAT_RAW;
        $res->headers->set('Content-Type', 'image/png');
        // ไม่ cache: ถ้าแอดมินเปลี่ยนหมายเลข PromptPay ผู้ใช้ต้องได้ QR ใหม่ทันที
        $res->headers->set('Cache-Control', 'private, no-store');
        $res->content = self::png($data, $size);
        return $res;
    }
}
