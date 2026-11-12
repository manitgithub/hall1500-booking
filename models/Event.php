<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * รอบการแสดง (ละครสั้น / คอนเสิร์ต ฯลฯ)
 * @property int $id
 * @property int $venue_id
 * @property string $title
 * @property string|null $category
 * @property string|null $description
 * @property string|null $poster
 * @property string|null $organizer
 * @property string $show_start
 * @property string|null $show_end
 * @property string|null $sale_start
 * @property string|null $sale_end
 * @property string $status
 * @property int $max_seats_per_booking
 * @property int $hold_minutes
 * @property string|null $promptpay_id   ว่าง = ใช้ค่ากลาง
 * @property string|null $promptpay_name
 *
 * @property EventZonePrice[] $zonePrices
 * @property Venue $venue
 */
class Event extends ActiveRecord
{
    use TimestampTrait;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    /** @var \yii\web\UploadedFile|null */
    public $posterFile;

    public static function tableName()
    {
        return '{{%event}}';
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'ร่าง',
            self::STATUS_PUBLISHED => 'เปิดขาย',
            self::STATUS_CLOSED => 'ปิดขาย',
            self::STATUS_CANCELLED => 'ยกเลิก',
        ];
    }

    public static function categories(): array
    {
        return ['concert' => 'คอนเสิร์ต', 'drama' => 'ละคร / ละครสั้น', 'seminar' => 'สัมมนา', 'other' => 'อื่น ๆ'];
    }

    public function rules()
    {
        return [
            [['title', 'show_start', 'status', 'venue_id'], 'required'],
            [['venue_id'], 'integer'],
            [['venue_id'], 'exist', 'targetClass' => Venue::class, 'targetAttribute' => 'id'],
            [['venue_id'], 'validateVenueChange'],
            [['title', 'organizer'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['category'], 'in', 'range' => array_keys(self::categories())],
            [['status'], 'in', 'range' => array_keys(self::statusLabels())],
            [['show_start', 'show_end', 'sale_start', 'sale_end'], 'filter', 'filter' => [$this, 'normalizeDate']],
            [['show_start', 'show_end', 'sale_start', 'sale_end'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
            [['sale_end'], 'compare', 'compareAttribute' => 'show_start', 'operator' => '<=', 'type' => 'datetime',
                'when' => fn() => $this->sale_end, 'message' => 'วันปิดขายต้องไม่เกินเวลาเริ่มแสดง'],
            [['max_seats_per_booking'], 'integer', 'min' => 1, 'max' => 50],
            [['hold_minutes'], 'integer', 'min' => 5, 'max' => 120],
            [['promptpay_id', 'promptpay_name'], 'trim'],
            [['promptpay_id'], 'filter', 'filter' => fn($v) => ($v = preg_replace('/\D/', '', (string)$v)) === '' ? null : $v],
            [['promptpay_id'], 'match', 'pattern' => '/^(0\d{9}|\d{13}|\d{15})$/',
                'message' => 'ต้องเป็นเบอร์มือถือ 10 หลัก, เลขประจำตัวผู้เสียภาษี 13 หลัก หรือ e-Wallet ID 15 หลัก'],
            [['promptpay_name'], 'string', 'max' => 255],
            [['promptpay_name'], 'default', 'value' => null],
            [['promptpay_name'], 'required', 'when' => fn() => (bool)$this->promptpay_id,
                'message' => 'ใส่ชื่อบัญชีของหมายเลขนี้ด้วย เพื่อให้ผู้โอนตรวจสอบได้'],
            [['posterFile'], 'image', 'extensions' => 'jpg, jpeg, png, webp', 'maxSize' => 3 * 1024 * 1024, 'skipOnEmpty' => true],
        ];
    }

    /** ห้ามย้ายสถานที่เมื่อมีการจองแล้ว (ที่นั่งเดิมผูกกับผังของสถานที่เดิม) */
    public function validateVenueChange($attr)
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attr, false)
            && Booking::find()->where(['event_id' => $this->id])->exists()) {
            $this->addError($attr, 'มีการจองแล้ว ไม่สามารถเปลี่ยนสถานที่ได้');
        }
    }

    /** หมายเลข PromptPay ที่ใช้รับเงินของงานนี้ (ไม่ได้ตั้ง = ค่ากลางของระบบ) */
    public function payPromptpayId(): string
    {
        return $this->promptpay_id ?: Setting::promptpayId();
    }

    public function payPromptpayName(): string
    {
        return $this->promptpay_id ? (string)$this->promptpay_name : Setting::promptpayName();
    }

    public function hasOwnPromptpay(): bool
    {
        return (bool)$this->promptpay_id;
    }

    public function getVenue()
    {
        return $this->hasOne(Venue::class, ['id' => 'venue_id']);
    }

    /** รับค่าจาก <input type="datetime-local"> */
    public function normalizeDate($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        $t = strtotime(str_replace('T', ' ', $v));
        return $t ? date('Y-m-d H:i:s', $t) : $v;
    }

    public function attributeLabels()
    {
        return [
            'title' => 'ชื่อการแสดง', 'venue_id' => 'สถานที่จัด', 'category' => 'ประเภท', 'description' => 'รายละเอียด',
            'poster' => 'โปสเตอร์', 'posterFile' => 'โปสเตอร์', 'organizer' => 'ผู้จัด',
            'show_start' => 'เริ่มแสดง', 'show_end' => 'สิ้นสุด',
            'sale_start' => 'เปิดขาย', 'sale_end' => 'ปิดขาย', 'status' => 'สถานะ',
            'promptpay_id' => 'หมายเลข PromptPay ของงานนี้', 'promptpay_name' => 'ชื่อบัญชี',
            'max_seats_per_booking' => 'จองได้สูงสุด (ที่/ครั้ง)', 'hold_minutes' => 'เวลาล็อกที่นั่ง (นาที)',
        ];
    }

    public function getZonePrices()
    {
        return $this->hasMany(EventZonePrice::class, ['event_id' => 'id'])->orderBy(['price' => SORT_DESC]);
    }

    public function isOnSale(): bool
    {
        $now = time();
        return $this->status === self::STATUS_PUBLISHED
            && (!$this->sale_start || strtotime($this->sale_start) <= $now)
            && (!$this->sale_end || strtotime($this->sale_end) >= $now)
            && strtotime($this->show_start) > $now;
    }

    public function saleStatusText(): string
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return self::statusLabels()[$this->status];
        }
        if ($this->sale_start && strtotime($this->sale_start) > time()) {
            return 'เปิดขาย ' . Yii::$app->formatter->asDatetime($this->sale_start);
        }
        return $this->isOnSale() ? 'เปิดจองอยู่' : 'ปิดการจอง';
    }

    public function getPosterUrl(): ?string
    {
        return $this->poster ? Yii::getAlias('@web/uploads/posters/' . $this->poster) : null;
    }

    public function priceRange(): string
    {
        $prices = array_map(fn($z) => (float)$z->price, $this->zonePrices);
        if (!$prices) {
            return '-';
        }
        $f = Yii::$app->formatter;
        return min($prices) === max($prices)
            ? $f->asDecimal(min($prices), 0) . ' บาท'
            : $f->asDecimal(min($prices), 0) . ' - ' . $f->asDecimal(max($prices), 0) . ' บาท';
    }

    /** สรุปจำนวนที่นั่งตามสถานะ */
    public function seatSummary(): array
    {
        $rows = (new \yii\db\Query())
            ->select(['status', 'cnt' => 'COUNT(*)'])
            ->from('{{%event_seat}}')
            ->where(['event_id' => $this->id])
            ->groupBy('status')
            ->all();
        $out = ['available' => 0, 'held' => 0, 'sold' => 0, 'blocked' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['cnt'];
        }
        $out['total'] = array_sum($out);
        return $out;
    }

    public function hasSeatInventory(): bool
    {
        return (new \yii\db\Query())->from('{{%event_seat}}')->where(['event_id' => $this->id])->exists();
    }

    /**
     * สร้าง/อัปเดตรายการที่นั่งของรอบการแสดงตามราคาโซน
     * - เพิ่มที่นั่งของโซนที่มีราคาและเปิดขาย
     * - อัปเดตราคาเฉพาะที่นั่งที่ยังว่าง
     * - ลบที่นั่งที่ยังว่างของโซนที่ปิดขาย
     */
    public function syncSeatInventory(): int
    {
        $db = static::getDb();
        $tx = $db->beginTransaction();
        try {
            $db->createCommand("
                INSERT INTO {{%event_seat}} (event_id, seat_id, status, price)
                SELECT :e, s.id, 'available', p.price
                FROM {{%seat}} s
                JOIN {{%event_zone_price}} p ON p.zone_code = s.zone_code AND p.event_id = :e AND p.is_open = 1
                WHERE s.is_active = 1 AND s.venue_id = :v
                ON DUPLICATE KEY UPDATE {{%event_seat}}.price = IF({{%event_seat}}.status = 'available', VALUES(price), {{%event_seat}}.price)
            ", [':e' => $this->id, ':v' => $this->venue_id])->execute();

            $db->createCommand("
                DELETE es FROM {{%event_seat}} es
                JOIN {{%seat}} s ON s.id = es.seat_id
                LEFT JOIN {{%event_zone_price}} p ON p.zone_code = s.zone_code AND p.event_id = es.event_id AND p.is_open = 1
                WHERE es.event_id = :e AND es.status IN ('available','blocked')
                  AND (p.zone_code IS NULL OR s.is_active = 0 OR s.venue_id <> :v)
            ", [':e' => $this->id, ':v' => $this->venue_id])->execute();
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return (int)(new \yii\db\Query())->from('{{%event_seat}}')->where(['event_id' => $this->id])->count();
    }

    /** ข้อมูลผังที่นั่งสำหรับ seatmap.js */
    public function seatMapData(?int $forUserId = null): array
    {
        Yii::$app->booking->releaseExpired($this->id);
        $rows = (new \yii\db\Query())
            ->select(['s.id', 's.floor', 's.section', 's.row_label', 's.seat_number', 's.label', 's.zone_code',
                's.pos_x', 's.pos_y', 's.is_wheelchair', 'es.status', 'es.price', 'b.user_id'])
            ->from(['s' => '{{%seat}}'])
            ->leftJoin(['es' => '{{%event_seat}}'], 'es.seat_id = s.id AND es.event_id = :e', [':e' => $this->id])
            ->leftJoin(['b' => '{{%booking}}'], 'b.id = es.booking_id')
            ->where(['s.is_active' => 1, 's.venue_id' => $this->venue_id])
            ->orderBy(['s.floor' => SORT_ASC, 's.row_index' => SORT_ASC, 's.pos_x' => SORT_ASC])
            ->all();

        $seats = [];
        foreach ($rows as $r) {
            $status = $r['status'] ?? 'unavailable';
            if ($status === 'held' && $forUserId && (int)$r['user_id'] === $forUserId) {
                $status = 'mine';
            }
            $seats[] = [
                'id' => (int)$r['id'],
                'f' => (int)$r['floor'],
                'r' => $r['row_label'],
                'n' => (int)$r['seat_number'],
                'l' => $r['label'],
                'z' => $r['zone_code'],
                'x' => (float)$r['pos_x'],
                'y' => (float)$r['pos_y'],
                'w' => (bool)$r['is_wheelchair'],
                's' => $status,
                'p' => $r['price'] !== null ? (float)$r['price'] : null,
            ];
        }
        $zones = [];
        foreach (Hall::zones() as $code => $z) {
            $zones[$code] = ['label' => $z['label'], 'color' => $z['color']];
        }
        return ['seats' => $seats, 'zones' => $zones, 'floors' => $this->venue->floorLabels(), 'seatSize' => $this->venue->seatSize()];
    }
}
