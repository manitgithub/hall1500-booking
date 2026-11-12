<?php

namespace app\models;

use Yii;
use yii\db\Query;

/**
 * ค่าตั้งค่าระบบแบบ key/value ในตาราง setting
 * ค่าที่ยังไม่เคยตั้งในหลังบ้านจะใช้ค่าจาก .env (config/params.php)
 */
class Setting
{
    const PROMPTPAY_ID = 'promptpay_id';
    const PROMPTPAY_NAME = 'promptpay_name';
    const SITE_NAME = 'site_name';
    const ORGANIZER_TERMS = 'organizer_terms';

    private static ?array $cache = null;

    /** ค่าเริ่มต้นจาก .env */
    public static function defaults(): array
    {
        $pp = Yii::$app->params['payment'];
        return [
            self::SITE_NAME => (string)Yii::$app->params['appName'],
            self::ORGANIZER_TERMS => (string)file_get_contents(Yii::getAlias('@app/config/organizer-terms.txt')),
            self::PROMPTPAY_ID => (string)($pp['promptpayId'] ?? ''),
            self::PROMPTPAY_NAME => (string)($pp['promptpayName'] ?? ''),
        ];
    }

    public static function get(string $key): ?string
    {
        if (self::$cache === null) {
            self::$cache = (new Query())->select(['value', 'key'])->from('{{%setting}}')->indexBy('key')->column();
        }
        return self::$cache[$key] ?? self::defaults()[$key] ?? null;
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        Yii::$app->db->createCommand()->upsert('{{%setting}}', [
            'key' => $key,
            'value' => $value,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        self::$cache = null;
    }

    public static function promptpayId(): string
    {
        return preg_replace('/\D/', '', (string)self::get(self::PROMPTPAY_ID));
    }

    public static function siteName(): string
    {
        try {
            return (string)self::get(self::SITE_NAME);
        } catch (\Throwable $e) {
            return (string)Yii::$app->params['appName']; // ยังไม่ได้ migrate
        }
    }

    /** เงื่อนไขสำหรับผู้จัดงาน (แก้ได้ที่ หลังบ้าน → ตั้งค่าระบบ) */
    public static function organizerTerms(): string
    {
        return str_replace("\r\n", "\n", trim((string)self::get(self::ORGANIZER_TERMS)));
    }

    public static function promptpayName(): string
    {
        return (string)self::get(self::PROMPTPAY_NAME);
    }
}
