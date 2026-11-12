<?php

namespace app\components;

use app\models\Booking;
use Yii;
use yii\base\Component;
use yii\base\Exception;

/**
 * Omise Payment Gateway (ไม่บังคับ)
 * - PromptPay: สร้าง charge ด้วย source type=promptpay แล้วแสดง QR ให้สแกน
 * - Card: รับ token จาก Omise.js แล้วสร้าง charge
 * ผลการชำระยืนยันผ่าน webhook (charge.complete) -> /payment/omise-webhook
 */
class OmiseGateway extends Component
{
    const API = 'https://api.omise.co';

    public $publicKey;
    public $secretKey;

    public function isEnabled(): bool
    {
        return !empty($this->publicKey) && !empty($this->secretKey);
    }

    public function createPromptPayCharge(Booking $b): array
    {
        return $this->createCharge($b, ['source[type]' => 'promptpay']);
    }

    public function createCardCharge(Booking $b, string $cardToken, string $returnUri): array
    {
        return $this->createCharge($b, ['card' => $cardToken, 'return_uri' => $returnUri]);
    }

    public function retrieveCharge(string $chargeId): array
    {
        if (!preg_match('/^chrg_[a-z0-9_]+$/i', $chargeId)) {
            throw new Exception('charge id ไม่ถูกต้อง');
        }
        [$status, $json, $raw] = Http::request('GET', self::API . '/charges/' . $chargeId, [
            'basicAuth' => $this->secretKey . ':',
        ]);
        if ($status !== 200 || !$json) {
            throw new Exception("Omise retrieve failed: $raw");
        }
        return $json;
    }

    private function createCharge(Booking $b, array $extra): array
    {
        $form = array_merge([
            'amount' => (int)round($b->total_amount * 100),   // หน่วยสตางค์
            'currency' => 'thb',
            'description' => "Booking {$b->code}",
            'metadata[booking_code]' => $b->code,
        ], $extra);
        [$status, $json, $raw] = Http::request('POST', self::API . '/charges', [
            'basicAuth' => $this->secretKey . ':',
            'form' => $form,
        ]);
        Yii::info("Omise create charge {$b->code}: $status", 'payment');
        if ($status >= 300 || !$json || ($json['object'] ?? '') === 'error') {
            throw new Exception('Omise: ' . ($json['message'] ?? $raw));
        }
        return $json;
    }
}
