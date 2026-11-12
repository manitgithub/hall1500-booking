<?php

namespace app\components;

use app\models\Booking;
use Yii;
use yii\base\Component;

/**
 * ส่งข้อความแจ้งเตือนผ่าน LINE Messaging API (ไม่บังคับ)
 * ต้องลิงก์ LINE Login channel กับ LINE Official Account (Messaging API channel)
 * ใน provider เดียวกัน และผู้ใช้ต้องเป็นเพื่อนกับ OA (ตั้ง LINE_BOT_PROMPT=aggressive)
 */
class LineMessaging extends Component
{
    public $accessToken;

    public function push(string $lineUserId, array $messages): bool
    {
        if (!$this->accessToken || !$lineUserId) {
            return false;
        }
        try {
            [$status, , $raw] = Http::request('POST', 'https://api.line.me/v2/bot/message/push', [
                'headers' => ['Authorization: Bearer ' . $this->accessToken],
                'json' => ['to' => $lineUserId, 'messages' => $messages],
            ]);
            if ($status !== 200) {
                Yii::warning("LINE push failed $status: $raw", 'line');
            }
            return $status === 200;
        } catch (\Throwable $e) {
            Yii::warning('LINE push error: ' . $e->getMessage(), 'line');
            return false;
        }
    }

    public function notifyPaid(Booking $b): void
    {
        $url = Yii::$app->params['appUrl'] . '/ticket/' . $b->code;
        $f = Yii::$app->formatter;
        $text = "✅ ชำระเงินสำเร็จ\n"
            . "{$b->event->title}\n"
            . '🗓 ' . $f->asDatetime($b->event->show_start) . "\n"
            . "💺 {$b->seatLabels()}\n"
            . "รหัสจอง: {$b->code}\n"
            . "ดูตั๋ว: $url";
        $this->push((string)$b->user->line_user_id, [['type' => 'text', 'text' => $text]]);
    }

    public function notifyRejected(Booking $b, string $reason): void
    {
        $url = Yii::$app->params['appUrl'] . '/booking/' . $b->code;
        $text = "⚠️ สลิปการโอนของรายการ {$b->code} ไม่ผ่านการตรวจสอบ\nเหตุผล: $reason\nกรุณาแนบสลิปใหม่ภายในเวลาที่กำหนด: $url";
        $this->push((string)$b->user->line_user_id, [['type' => 'text', 'text' => $text]]);
    }
}
