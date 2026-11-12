<?php

namespace app\models;

use Yii;
use yii\db\Query;

/**
 * สถิติการใช้งานจริงสำหรับหน้าแรก (นับจากฐานข้อมูล ไม่มีการปรับแต่งตัวเลข)
 */
class SiteStats
{
    const CACHE_KEY = 'site-stats';
    const CACHE_TTL = 600;

    /**
     * @return array{events:int, organizers:int, tickets:int, venues:int}
     */
    public static function get(): array
    {
        return Yii::$app->cache->getOrSet(self::CACHE_KEY, fn() => [
            // งานที่เปิดขายหรือจบแล้ว (ไม่นับร่างและที่ยกเลิก)
            'events' => (int)Event::find()->where(['status' => [Event::STATUS_PUBLISHED, Event::STATUS_CLOSED]])->count(),
            'organizers' => (int)User::find()->where(['role' => User::ROLE_ORGANIZER])->count(),
            // ที่นั่งที่ชำระเงินแล้ว (ไม่นับที่ยกเลิก/คืนเงิน)
            'tickets' => (int)(new Query())->from('{{%booking}}')->where(['status' => Booking::STATUS_PAID])->sum('seat_count'),
            'venues' => (int)Venue::find()->where(['is_active' => 1])->count(),
        ], self::CACHE_TTL);
    }

    /** แสดงสถิติเมื่อมีงานถึงขั้นต่ำที่แอดมินตั้งไว้ */
    public static function shouldShow(array $stats): bool
    {
        $min = Setting::statsMinEvents();
        return $min > 0 && $stats['events'] >= $min;
    }
}
