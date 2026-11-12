<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $method
 * @property float $amount
 * @property string $status
 * @property string|null $gateway_ref
 * @property string|null $slip_path
 * @property string|null $transferred_at
 * @property string|null $raw_response
 * @property int|null $verified_by
 * @property string|null $verified_at
 * @property string|null $note
 * @property string $created_at
 *
 * @property Booking $booking
 * @property User|null $verifier
 */
class Payment extends ActiveRecord
{
    use TimestampTrait;

    const METHOD_SLIP = 'promptpay_slip';
    const METHOD_OMISE_PROMPTPAY = 'omise_promptpay';
    const METHOD_OMISE_CARD = 'omise_card';
    const METHOD_CASH = 'cash';

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_REJECTED = 'rejected';

    public static function tableName()
    {
        return '{{%payment}}';
    }

    public static function methodLabels(): array
    {
        return [
            self::METHOD_SLIP => 'โอน PromptPay + แนบสลิป',
            self::METHOD_OMISE_PROMPTPAY => 'PromptPay (Omise อัตโนมัติ)',
            self::METHOD_OMISE_CARD => 'บัตรเครดิต (Omise)',
            self::METHOD_CASH => 'เงินสด (หน้างาน)',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'รอดำเนินการ',
            self::STATUS_SUCCESS => 'สำเร็จ',
            self::STATUS_FAILED => 'ไม่สำเร็จ',
            self::STATUS_REJECTED => 'สลิปไม่ผ่าน',
        ];
    }

    public function getBooking()
    {
        return $this->hasOne(Booking::class, ['id' => 'booking_id']);
    }

    public function getVerifier()
    {
        return $this->hasOne(User::class, ['id' => 'verified_by']);
    }

    /** โฟลเดอร์เก็บสลิป (นอก web root: มีชื่อและเลขบัญชีผู้โอน) */
    public static function slipDir(): string
    {
        $dir = Yii::getAlias('@app/storage/slips');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    public function slipFile(): ?string
    {
        return $this->slip_path ? self::slipDir() . '/' . basename($this->slip_path) : null;
    }

    /** ลิงก์ดูสลิปผ่าน PaymentController (ตรวจสิทธิ์ทุกครั้ง) */
    public function getSlipUrl(): ?string
    {
        return $this->slip_path ? \yii\helpers\Url::to(['/payment/slip-image', 'id' => $this->id]) : null;
    }

    /** ผู้จองเจ้าของสลิป, แอดมิน, หรือผู้จัดงานเจ้าของการแสดง */
    public function canViewSlip(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $b = $this->booking;
        return $user->isAdmin() || (int)$b->user_id === (int)$user->id
            || ($user->isOrganizer() && (int)$b->event->owner_id === (int)$user->id);
    }
}
