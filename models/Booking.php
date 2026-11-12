<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $code
 * @property int $event_id
 * @property int $user_id
 * @property string $status
 * @property float $total_amount
 * @property int $seat_count
 * @property string|null $expires_at
 * @property string|null $paid_at
 * @property string|null $ticket_token
 * @property string|null $checked_in_at
 * @property int|null $checked_in_by
 * @property string|null $note
 * @property string $created_at
 *
 * @property Event $event
 * @property User $user
 * @property Seat[] $seats
 * @property BookingSeat[] $bookingSeats
 * @property Payment[] $payments
 */
class Booking extends ActiveRecord
{
    use TimestampTrait;

    const STATUS_PENDING = 'pending';      // ล็อกที่นั่งแล้ว รอชำระ
    const STATUS_VERIFYING = 'verifying';  // แนบสลิปแล้ว รอเจ้าหน้าที่ตรวจ
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REFUNDED = 'refunded';

    public static function tableName()
    {
        return '{{%booking}}';
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'รอชำระเงิน',
            self::STATUS_VERIFYING => 'รอตรวจสอบสลิป',
            self::STATUS_PAID => 'ชำระแล้ว',
            self::STATUS_CANCELLED => 'ยกเลิก',
            self::STATUS_EXPIRED => 'หมดเวลา',
            self::STATUS_REFUNDED => 'คืนเงินแล้ว',
        ];
    }

    public static function statusBadge(string $status): string
    {
        $cls = [
            self::STATUS_PENDING => 'warning text-dark',
            self::STATUS_VERIFYING => 'info text-dark',
            self::STATUS_PAID => 'success',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_EXPIRED => 'secondary',
            self::STATUS_REFUNDED => 'dark',
        ][$status] ?? 'light';
        return '<span class="badge bg-' . $cls . '">' . (self::statusLabels()[$status] ?? $status) . '</span>';
    }

    public static function generateCode(): string
    {
        // BK + ปีเดือน + 6 ตัวอักษร (ตัดตัวที่สับสนง่ายออก)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $rand = '';
            for ($i = 0; $i < 6; $i++) {
                $rand .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = 'BK' . date('ym') . '-' . $rand;
        } while (static::find()->where(['code' => $code])->exists());
        return $code;
    }

    public function getEvent()
    {
        return $this->hasOne(Event::class, ['id' => 'event_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getBookingSeats()
    {
        return $this->hasMany(BookingSeat::class, ['booking_id' => 'id']);
    }

    public function getSeats()
    {
        return $this->hasMany(Seat::class, ['id' => 'seat_id'])
            ->via('bookingSeats')
            ->orderBy(['floor' => SORT_ASC, 'row_index' => SORT_ASC, 'seat_number' => SORT_ASC]);
    }

    public function getPayments()
    {
        return $this->hasMany(Payment::class, ['booking_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function getLatestPayment()
    {
        return $this->hasOne(Payment::class, ['booking_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->secondsLeft() > 0;
    }

    public function secondsLeft(): int
    {
        return $this->expires_at ? max(0, strtotime($this->expires_at) - time()) : 0;
    }

    public function seatLabels(): string
    {
        return implode(', ', array_map(fn(Seat $s) => $s->label, $this->seats));
    }

    /** ข้อความใน QR ของตั๋ว */
    public function ticketQrData(): string
    {
        return 'HALL1500|' . $this->code . '|' . $this->ticket_token;
    }

    public function canView(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || $user->isStaff());
    }
}
