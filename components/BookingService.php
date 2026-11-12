<?php

namespace app\components;

use app\models\Booking;
use app\models\Event;
use app\models\Payment;
use app\models\SlipForm;
use app\models\User;
use Yii;
use yii\base\Component;
use yii\base\UserException;
use yii\db\Connection;
use yii\db\Query;

/**
 * ตรรกะหลักของการจองที่นั่ง
 *
 * หลักการกันจองซ้ำ (double booking):
 *   UPDATE event_seat SET status='held', booking_id=? WHERE ... AND status='available'
 *   แล้วตรวจจำนวนแถวที่ถูกอัปเดต ถ้าไม่ครบ = มีคนจองตัดหน้า -> rollback ทั้งรายการ
 *   InnoDB จะล็อกแถวระหว่าง UPDATE จึงไม่มีสองคนได้ที่นั่งเดียวกัน
 */
class BookingService extends Component
{
    private function db(): Connection
    {
        return Yii::$app->db;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * ล็อกที่นั่งให้ผู้ใช้ สร้าง booking สถานะ pending
     * @throws UserException
     */
    public function hold(Event $event, User $user, array $seatIds): Booking
    {
        $seatIds = array_values(array_unique(array_filter(array_map('intval', $seatIds))));
        if (!$event->isOnSale()) {
            throw new UserException('การแสดงนี้ยังไม่เปิดหรือปิดการจองแล้ว');
        }
        if (!$seatIds) {
            throw new UserException('กรุณาเลือกที่นั่งอย่างน้อย 1 ที่');
        }
        if (count($seatIds) > $event->max_seats_per_booking) {
            throw new UserException("เลือกได้สูงสุด {$event->max_seats_per_booking} ที่นั่งต่อการจอง");
        }

        $this->releaseExpired($event->id);
        $db = $this->db();
        $tx = $db->beginTransaction();
        try {
            // ยกเลิกรายการค้างชำระเดิมของผู้ใช้ในรอบนี้ (เลือกใหม่ = แทนที่ของเดิม)
            $old = Booking::find()
                ->where(['event_id' => $event->id, 'user_id' => $user->id, 'status' => Booking::STATUS_PENDING])
                ->all();
            foreach ($old as $o) {
                $this->releaseSeats($o->id);
                $o->updateAttributes(['status' => Booking::STATUS_CANCELLED, 'note' => 'เลือกที่นั่งใหม่']);
            }

            $expires = date('Y-m-d H:i:s', time() + $event->hold_minutes * 60);
            $booking = new Booking([
                'code' => Booking::generateCode(),
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => Booking::STATUS_PENDING,
                'expires_at' => $expires,
            ]);
            $booking->save(false);

            $affected = $db->createCommand()->update('{{%event_seat}}', [
                'status' => 'held',
                'booking_id' => $booking->id,
                'held_until' => $expires,
            ], [
                'and',
                ['event_id' => $event->id, 'seat_id' => $seatIds, 'status' => 'available'],
            ])->execute();

            if ($affected !== count($seatIds)) {
                throw new UserException('ที่นั่งบางที่เพิ่งถูกจองไปแล้ว กรุณาเลือกใหม่');
            }

            $db->createCommand("
                INSERT INTO {{%booking_seat}} (booking_id, seat_id, price)
                SELECT booking_id, seat_id, price FROM {{%event_seat}} WHERE booking_id = :b AND event_id = :e
            ", [':b' => $booking->id, ':e' => $event->id])->execute();

            $total = (new Query())->from('{{%booking_seat}}')->where(['booking_id' => $booking->id])->sum('price');
            $booking->updateAttributes(['total_amount' => $total, 'seat_count' => count($seatIds)]);

            $tx->commit();
            return $booking;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /** ปล่อยที่นั่งของรายการที่หมดเวลาชำระ */
    public function releaseExpired(?int $eventId = null): int
    {
        $q = Booking::find()->select('id')
            ->where(['status' => Booking::STATUS_PENDING])
            ->andWhere(['<', 'expires_at', $this->now()]);
        if ($eventId) {
            $q->andWhere(['event_id' => $eventId]);
        }
        $ids = $q->column();
        if (!$ids) {
            return 0;
        }
        $db = $this->db();
        $tx = $db->beginTransaction();
        try {
            // ตรวจสถานะซ้ำใน UPDATE เพื่อไม่ทับรายการที่เพิ่งจ่ายเงินพอดี
            $n = $db->createCommand()->update('{{%booking}}', ['status' => Booking::STATUS_EXPIRED, 'updated_at' => $this->now()],
                ['and', ['id' => $ids, 'status' => Booking::STATUS_PENDING], ['<', 'expires_at', $this->now()]])->execute();
            $db->createCommand("
                UPDATE {{%event_seat}} es JOIN {{%booking}} b ON b.id = es.booking_id
                SET es.status = 'available', es.booking_id = NULL, es.held_until = NULL
                WHERE es.status = 'held' AND b.status = 'expired' AND b.id IN (" . implode(',', array_map('intval', $ids)) . ")
            ")->execute();
            $tx->commit();
            return $n;
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    private function releaseSeats(int $bookingId): void
    {
        $this->db()->createCommand()->update('{{%event_seat}}',
            ['status' => 'available', 'booking_id' => null, 'held_until' => null],
            ['booking_id' => $bookingId, 'status' => ['held', 'sold']]
        )->execute();
    }

    /** ผู้ใช้ยกเลิกเองระหว่างรอชำระ */
    public function cancel(Booking $b): void
    {
        if ($b->status !== Booking::STATUS_PENDING) {
            throw new UserException('ยกเลิกได้เฉพาะรายการที่ยังไม่ชำระเงิน');
        }
        $tx = $this->db()->beginTransaction();
        try {
            $this->releaseSeats($b->id);
            $b->updateAttributes(['status' => Booking::STATUS_CANCELLED, 'expires_at' => null]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /** แอดมินยกเลิก/คืนเงินรายการ (รวมรายการที่ชำระแล้ว) แล้วปล่อยที่นั่ง */
    public function adminCancel(Booking $b, string $status, string $note = ''): void
    {
        $tx = $this->db()->beginTransaction();
        try {
            $this->releaseSeats($b->id);
            $b->updateAttributes(['status' => $status, 'note' => $note ?: $b->note, 'expires_at' => null]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /** ผู้ใช้แนบสลิปโอน PromptPay */
    public function submitSlip(Booking $b, SlipForm $form): Payment
    {
        if (!$b->isPayable()) {
            throw new UserException('รายการนี้หมดเวลาชำระเงินหรือชำระแล้ว');
        }
        $name = $b->code . '-' . Yii::$app->security->generateRandomString(10) . '.' . strtolower($form->slip->extension);
        $dir = Payment::slipDir();
        if (!$form->slip->saveAs("$dir/$name")) {
            throw new UserException('อัปโหลดสลิปไม่สำเร็จ');
        }

        $tx = $this->db()->beginTransaction();
        try {
            $p = new Payment([
                'booking_id' => $b->id,
                'method' => Payment::METHOD_SLIP,
                'amount' => $b->total_amount,
                'status' => Payment::STATUS_PENDING,
                'slip_path' => $name,
                'transferred_at' => $form->transferred_at,
            ]);
            $p->save(false);
            // หยุดนับเวลาหมดอายุระหว่างรอผู้จัดงานตรวจ
            $n = $this->db()->createCommand()->update('{{%booking}}',
                ['status' => Booking::STATUS_VERIFYING, 'updated_at' => $this->now()],
                ['id' => $b->id, 'status' => Booking::STATUS_PENDING])->execute();
            if ($n !== 1) {
                throw new UserException('รายการนี้หมดเวลาชำระเงินแล้ว');
            }
            $this->db()->createCommand()->update('{{%event_seat}}', ['held_until' => null], ['booking_id' => $b->id])->execute();
            $tx->commit();
            $b->refresh();
            return $p;
        } catch (\Throwable $e) {
            $tx->rollBack();
            @unlink("$dir/$name");
            throw $e;
        }
    }

    public function approvePayment(Payment $p, User $admin): bool
    {
        if ($p->status !== Payment::STATUS_PENDING) {
            throw new UserException('รายการชำระเงินนี้ถูกตรวจแล้ว');
        }
        $p->updateAttributes(['status' => Payment::STATUS_SUCCESS, 'verified_by' => $admin->id, 'verified_at' => $this->now()]);
        return $this->confirmPaid($p->booking);
    }

    public function rejectPayment(Payment $p, User $admin, string $reason): void
    {
        if ($p->status !== Payment::STATUS_PENDING) {
            throw new UserException('รายการชำระเงินนี้ถูกตรวจแล้ว');
        }
        $b = $p->booking;
        $tx = $this->db()->beginTransaction();
        try {
            $p->updateAttributes([
                'status' => Payment::STATUS_REJECTED, 'verified_by' => $admin->id,
                'verified_at' => $this->now(), 'note' => $reason,
            ]);
            if ($b->status === Booking::STATUS_VERIFYING) {
                // ให้เวลาผู้ใช้แนบสลิปใหม่อีกรอบ
                $expires = date('Y-m-d H:i:s', time() + $b->event->hold_minutes * 60);
                $b->updateAttributes(['status' => Booking::STATUS_PENDING, 'expires_at' => $expires]);
                $this->db()->createCommand()->update('{{%event_seat}}', ['held_until' => $expires], ['booking_id' => $b->id])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        Yii::$app->lineMessaging->notifyRejected($b, $reason);
    }

    /**
     * ยืนยันการชำระเงิน: ที่นั่ง -> sold, booking -> paid, ออกตั๋ว
     * กรณีเงินเข้าหลังหมดเวลา จะพยายามจองที่นั่งเดิมกลับคืน ถ้าไม่ได้จะบันทึกให้คืนเงิน
     * @return bool true = ออกตั๋วสำเร็จ
     */
    public function confirmPaid(Booking $b): bool
    {
        $db = $this->db();
        $tx = $db->beginTransaction();
        try {
            // ล็อกแถว booking กันการยืนยันซ้ำพร้อมกัน (เช่น webhook ยิงซ้ำ)
            $status = $db->createCommand('SELECT status FROM {{%booking}} WHERE id = :id FOR UPDATE', [':id' => $b->id])->queryScalar();
            if ($status === Booking::STATUS_PAID) {
                $tx->commit();
                return true;
            }

            if (in_array($status, [Booking::STATUS_PENDING, Booking::STATUS_VERIFYING], true)) {
                $n = $db->createCommand()->update('{{%event_seat}}', ['status' => 'sold', 'held_until' => null],
                    ['booking_id' => $b->id, 'status' => 'held'])->execute();
            } else {
                // expired / cancelled -> ลองจองที่นั่งเดิมกลับ
                $seatIds = (new Query())->select('seat_id')->from('{{%booking_seat}}')->where(['booking_id' => $b->id])->column();
                $n = $db->createCommand()->update('{{%event_seat}}',
                    ['status' => 'sold', 'booking_id' => $b->id, 'held_until' => null],
                    ['event_id' => $b->event_id, 'seat_id' => $seatIds, 'status' => 'available'])->execute();
            }

            if ($n !== (int)$b->seat_count) {
                $tx->rollBack();
                $b->updateAttributes(['note' => 'ได้รับเงินแล้วแต่ที่นั่งถูกปล่อยไปแล้ว — ต้องติดต่อคืนเงิน']);
                Yii::warning("Booking {$b->code}: paid after seats released, refund required", 'payment');
                return false;
            }

            $b->updateAttributes([
                'status' => Booking::STATUS_PAID,
                'paid_at' => $this->now(),
                'expires_at' => null,
                'ticket_token' => Yii::$app->security->generateRandomString(40),
            ]);
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        Yii::$app->lineMessaging->notifyPaid($b);
        return true;
    }

    /** แอดมินกันที่นั่ง (ห้องควบคุม/แขก VIP) หรือปลดกัน */
    public function setBlocked(Event $event, array $seatIds, bool $block): int
    {
        $seatIds = array_map('intval', $seatIds);
        return $this->db()->createCommand()->update('{{%event_seat}}',
            ['status' => $block ? 'blocked' : 'available'],
            ['event_id' => $event->id, 'seat_id' => $seatIds, 'status' => $block ? 'available' : 'blocked']
        )->execute();
    }

    /**
     * เช็คอินหน้างานจากข้อมูลใน QR
     * @return array{ok:bool,message:string,booking:?Booking}
     */
    public function checkIn(string $qrData, User $checker): array
    {
        $parts = explode('|', trim($qrData));
        if (count($parts) !== 3 || $parts[0] !== 'HALL1500') {
            return ['ok' => false, 'message' => 'QR ไม่ใช่ตั๋วของระบบนี้', 'booking' => null];
        }
        $b = Booking::findOne(['code' => $parts[1]]);
        if (!$b || !$b->ticket_token || !hash_equals($b->ticket_token, $parts[2])) {
            return ['ok' => false, 'message' => 'ไม่พบตั๋ว หรือตั๋วไม่ถูกต้อง', 'booking' => null];
        }
        // ผู้จัดงานเช็คอินได้เฉพาะงานของตัวเอง (ไม่เปิดเผยข้อมูลผู้ซื้อของงานอื่น)
        if (!$checker->managesEvent($b->event)) {
            return ['ok' => false, 'message' => 'ตั๋วนี้เป็นของงานอื่น: ' . $b->event->title, 'booking' => null];
        }
        if ($b->status !== Booking::STATUS_PAID) {
            return ['ok' => false, 'message' => 'ตั๋วนี้สถานะ: ' . Booking::statusLabels()[$b->status], 'booking' => $b];
        }
        if ($b->checked_in_at) {
            return ['ok' => false, 'message' => 'ตั๋วนี้เช็คอินแล้วเมื่อ ' . Yii::$app->formatter->asDatetime($b->checked_in_at), 'booking' => $b];
        }
        $n = $this->db()->createCommand()->update('{{%booking}}',
            ['checked_in_at' => $this->now(), 'checked_in_by' => $checker->id],
            ['id' => $b->id, 'checked_in_at' => null])->execute();
        $b->refresh();
        return $n
            ? ['ok' => true, 'message' => 'เช็คอินสำเร็จ', 'booking' => $b]
            : ['ok' => false, 'message' => 'ตั๋วนี้เพิ่งถูกเช็คอิน', 'booking' => $b];
    }
}
