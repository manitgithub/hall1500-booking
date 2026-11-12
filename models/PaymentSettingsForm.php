<?php

namespace app\models;

use yii\base\Model;

/**
 * ฟอร์มตั้งค่าการรับชำระเงิน PromptPay (หลังบ้าน)
 */
class PaymentSettingsForm extends Model
{
    public $promptpay_id;
    public $promptpay_name;

    public function init()
    {
        parent::init();
        $this->promptpay_id = Setting::promptpayId();
        $this->promptpay_name = Setting::promptpayName();
    }

    public function rules()
    {
        return [
            [['promptpay_id', 'promptpay_name'], 'trim'],
            [['promptpay_id', 'promptpay_name'], 'required'],
            [['promptpay_id'], 'filter', 'filter' => fn($v) => preg_replace('/\D/', '', (string)$v)],
            [['promptpay_id'], 'match', 'pattern' => '/^(0\d{9}|\d{13}|\d{15})$/',
                'message' => 'ต้องเป็นเบอร์มือถือ 10 หลัก, เลขประจำตัวผู้เสียภาษี/บัตรประชาชน 13 หลัก หรือ e-Wallet ID 15 หลัก'],
            [['promptpay_name'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'promptpay_id' => 'หมายเลข PromptPay',
            'promptpay_name' => 'ชื่อบัญชี (แสดงให้ผู้โอนเห็น)',
        ];
    }

    public function save(?int $userId): bool
    {
        if (!$this->validate()) {
            return false;
        }
        Setting::set(Setting::PROMPTPAY_ID, $this->promptpay_id, $userId);
        Setting::set(Setting::PROMPTPAY_NAME, $this->promptpay_name, $userId);
        return true;
    }
}
