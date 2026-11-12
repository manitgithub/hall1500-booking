<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * สถานที่จัดงาน (หอประชุม / โรงละคร / ห้องประชุม) พร้อมผังที่นั่งของตัวเอง
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property string|null $description
 * @property string $layout      grid หรือชื่อไฟล์ผังใน config/venues/
 * @property string|null $settings JSON ของผังแบบ grid
 * @property bool $is_active
 */
class Venue extends ActiveRecord
{
    use TimestampTrait;

    const LAYOUT_GRID = 'grid';

    // ฟิลด์ของผังแบบ grid (เก็บใน settings)
    public $grid_rows = 15;
    public $grid_sections = '8,16,8';
    public $grid_zone_vip = 2;
    public $grid_zone_a = 5;
    public $grid_zone_b = 5;

    private ?array $layoutCache = null;

    public static function tableName()
    {
        return '{{%venue}}';
    }

    public static function options(bool $activeOnly = true): array
    {
        $q = static::find()->orderBy('name');
        if ($activeOnly) {
            $q->where(['is_active' => 1]);
        }
        return $q->select('name')->indexBy('id')->column();
    }

    public function rules()
    {
        return [
            [['name', 'layout'], 'required'],
            [['name', 'location'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['is_active'], 'boolean'],
            [['layout'], 'in', 'range' => array_keys($this->layoutOptions())],
            [['grid_rows'], 'integer', 'min' => 1, 'max' => 60, 'when' => fn() => $this->isGrid()],
            [['grid_sections'], 'match', 'pattern' => '/^\s*\d+(\s*,\s*\d+)*\s*$/', 'when' => fn() => $this->isGrid(),
                'message' => 'ใส่จำนวนที่นั่งต่อแถวของแต่ละบล็อก คั่นด้วยจุลภาค เช่น 8,16,8'],
            [['grid_sections'], 'validateSections', 'when' => fn() => $this->isGrid()],
            [['grid_zone_vip', 'grid_zone_a', 'grid_zone_b'], 'integer', 'min' => 0, 'max' => 60],
        ];
    }

    public function validateSections($attr)
    {
        $n = array_map('intval', explode(',', $this->$attr));
        if (min($n) < 1 || max($n) > 80 || count($n) > 8) {
            $this->addError($attr, 'แต่ละบล็อกต้องมี 1-80 ที่นั่ง และไม่เกิน 8 บล็อก');
        }
    }

    public function attributeLabels()
    {
        return [
            'name' => 'ชื่อสถานที่', 'location' => 'ที่ตั้ง / อาคาร', 'description' => 'รายละเอียด',
            'layout' => 'รูปแบบผังที่นั่ง', 'is_active' => 'เปิดใช้งาน',
            'grid_rows' => 'จำนวนแถว', 'grid_sections' => 'ที่นั่งต่อแถวของแต่ละบล็อก (ซ้าย → ขวา)',
            'grid_zone_vip' => 'แถว VIP', 'grid_zone_a' => 'แถวโซน A', 'grid_zone_b' => 'แถวโซน B',
        ];
    }

    public function layoutOptions(): array
    {
        $out = [self::LAYOUT_GRID => 'สร้างผังเอง (แถว × บล็อกที่นั่ง)'];
        foreach (Hall::layoutFiles() as $key => $file) {
            $out[$key] = "ผังละเอียดจากไฟล์ config/venues/$file";
        }
        return $out;
    }

    public function isGrid(): bool
    {
        return $this->layout === self::LAYOUT_GRID;
    }

    public function afterFind()
    {
        parent::afterFind();
        $s = json_decode((string)$this->settings, true) ?: [];
        $this->grid_rows = $s['rows'] ?? $this->grid_rows;
        $this->grid_sections = isset($s['sections']) ? implode(',', $s['sections']) : $this->grid_sections;
        $this->grid_zone_vip = $s['zoneRows']['VIP'] ?? $this->grid_zone_vip;
        $this->grid_zone_a = $s['zoneRows']['A'] ?? $this->grid_zone_a;
        $this->grid_zone_b = $s['zoneRows']['B'] ?? $this->grid_zone_b;
    }

    public function beforeSave($insert)
    {
        if ($this->isGrid()) {
            $this->settings = json_encode([
                'rows' => (int)$this->grid_rows,
                'sections' => array_map('intval', explode(',', $this->grid_sections)),
                'zoneRows' => ['VIP' => (int)$this->grid_zone_vip, 'A' => (int)$this->grid_zone_a, 'B' => (int)$this->grid_zone_b],
            ]);
        }
        $this->layoutCache = null;
        return parent::beforeSave($insert);
    }

    /** layout config สำหรับ Hall::buildSeats */
    public function layoutConfig(): array
    {
        if ($this->layoutCache !== null) {
            return $this->layoutCache;
        }
        if (!$this->isGrid()) {
            return $this->layoutCache = Hall::loadLayoutFile($this->layout);
        }
        return $this->layoutCache = [
            'type' => 'grid',
            'seatSize' => 18, 'rowGap' => 22, 'aisle' => 30, 'curve' => 0.0001,
            'rows' => (int)$this->grid_rows,
            'sections' => array_map('intval', explode(',', $this->grid_sections)),
            'zoneRows' => ['VIP' => (int)$this->grid_zone_vip, 'A' => (int)$this->grid_zone_a, 'B' => (int)$this->grid_zone_b],
            'restZone' => 'C',
            'floors' => [1 => ['label' => '']],
        ];
    }

    /** [ชั้น => ชื่อชั้น] */
    public function floorLabels(): array
    {
        $out = [];
        foreach ($this->layoutConfig()['floors'] as $no => $f) {
            $out[$no] = $f['label'] ?? '';
        }
        return $out;
    }

    public function floorLabel(int $floor): string
    {
        return $this->floorLabels()[$floor] ?? '';
    }

    public function seatSize(): float
    {
        return $this->layoutConfig()['seatSize'] ?? 18;
    }

    public function seatCount(): int
    {
        return (int)(new Query())->from('{{%seat}}')->where(['venue_id' => $this->id, 'is_active' => 1])->count();
    }

    public function hasSales(): bool
    {
        return (new Query())->from('{{%booking_seat}} bs')
            ->innerJoin('{{%seat}} s', 's.id = bs.seat_id')
            ->where(['s.venue_id' => $this->id])->exists();
    }

    public function getEvents()
    {
        return $this->hasMany(Event::class, ['venue_id' => 'id']);
    }

    /**
     * สร้าง/อัปเดตที่นั่งของสถานที่นี้จากผัง
     * - ยังไม่เคยขาย: ลบแล้วสร้างใหม่ทั้งหมด
     * - ขายแล้ว: อัปเดตที่นั่งเดิม (จับคู่ด้วยชั้น+ป้าย) เพิ่มที่นั่งใหม่ และปิดที่นั่งที่หายไป (is_active = 0)
     * @return int จำนวนที่นั่งที่ใช้งาน
     */
    public function generateSeats(): int
    {
        $seats = Hall::buildSeats($this->layoutConfig());
        foreach ($seats as &$s) {
            $s = ['venue_id' => $this->id] + $s;
        }
        unset($s);

        $db = static::getDb();
        $tx = $db->beginTransaction();
        try {
            if (!$this->hasSales()) {
                $ids = (new Query())->select('id')->from('{{%seat}}')->where(['venue_id' => $this->id])->column();
                if ($ids) {
                    $db->createCommand()->delete('{{%event_seat}}', ['seat_id' => $ids])->execute();
                    $db->createCommand()->delete('{{%seat}}', ['id' => $ids])->execute();
                }
                foreach (array_chunk($seats, 500) as $chunk) {
                    $db->createCommand()->batchInsert('{{%seat}}', array_keys($chunk[0]), array_map('array_values', $chunk))->execute();
                }
            } else {
                $existing = (new Query())->select(['id', 'k' => "CONCAT(floor, ':', label)"])->from('{{%seat}}')
                    ->where(['venue_id' => $this->id])->indexBy('k')->column();
                $seen = [];
                foreach ($seats as $s) {
                    $k = $s['floor'] . ':' . $s['label'];
                    if (isset($existing[$k])) {
                        $db->createCommand()->update('{{%seat}}', $s, ['id' => $existing[$k]])->execute();
                        $seen[] = $existing[$k];
                    } else {
                        $db->createCommand()->insert('{{%seat}}', $s)->execute();
                        $seen[] = (int)$db->getLastInsertID();
                    }
                }
                $db->createCommand()->update('{{%seat}}', ['is_active' => 0],
                    ['and', ['venue_id' => $this->id], ['not in', 'id', $seen ?: [0]]])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return $this->seatCount();
    }
}
