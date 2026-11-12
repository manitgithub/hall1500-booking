<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * ฟอร์มตั้งค่าระบบ: ชื่อระบบ และการรับชำระเงิน PromptPay (หลังบ้าน)
 */
class PaymentSettingsForm extends Model
{
    public $site_name;
    public $organizer_terms;
    public $stats_min_events;
    public $promptpay_id;
    public $promptpay_name;

    public function init()
    {
        parent::init();
        $this->site_name = Setting::siteName();
        $this->organizer_terms = Setting::organizerTerms();
        $this->stats_min_events = Setting::statsMinEvents();
        $this->promptpay_id = Setting::promptpayId();
        $this->promptpay_name = Setting::promptpayName();
    }

    public function rules()
    {
        return [
            [['site_name', 'promptpay_id', 'promptpay_name'], 'trim'],
            [['site_name', 'promptpay_id', 'promptpay_name'], 'required'],
            [['site_name'], 'string', 'max' => 100],
            [['organizer_terms'], 'required'],
            [['stats_min_events'], 'required'],
            [['stats_min_events'], 'integer', 'min' => 0, 'max' => 100000],
            [['organizer_terms'], 'string', 'max' => 20000],
            [['promptpay_id'], 'filter', 'filter' => fn($v) => preg_replace('/\D/', '', (string)$v)],
            [['promptpay_id'], 'match', 'pattern' => '/^(0\d{9}|\d{13}|\d{15})$/',
                'message' => 'ต้องเป็นเบอร์มือถือ 10 หลัก, เลขประจำตัวผู้เสียภาษี/บัตรประชาชน 13 หลัก หรือ e-Wallet ID 15 หลัก'],
            [['promptpay_name'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'site_name' => 'ชื่อระบบ / หน่วยงาน',
            'organizer_terms' => 'เงื่อนไขการใช้งานสำหรับผู้จัดงาน',
            'stats_min_events' => 'แสดงสถิติการใช้งานในหน้าแรก เมื่อมีงานอย่างน้อย',
            'promptpay_id' => 'หมายเลข PromptPay',
            'promptpay_name' => 'ชื่อบัญชี (แสดงให้ผู้โอนเห็น)',
        ];
    }

    public function save(?int $userId): bool
    {
        if (!$this->validate()) {
            return false;
        }
        Setting::set(Setting::SITE_NAME, $this->site_name, $userId);
        Setting::set(Setting::STATS_MIN_EVENTS, (string)(int)$this->stats_min_events, $userId);
        Yii::$app->cache->delete(SiteStats::CACHE_KEY);
        Setting::set(Setting::ORGANIZER_TERMS, str_replace("\r\n", "\n", trim($this->organizer_terms)), $userId);
        Setting::set(Setting::PROMPTPAY_ID, $this->promptpay_id, $userId);
        Setting::set(Setting::PROMPTPAY_NAME, $this->promptpay_name, $userId);
        return true;
    }
}
