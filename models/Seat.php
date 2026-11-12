<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * ที่นั่งทางกายภาพ
 * @property int $id
 * @property int $venue_id
 * @property int $floor
 * @property string $section
 * @property string $row_label
 * @property int $row_index
 * @property int $seat_number
 * @property string $label
 * @property string $zone_code
 * @property float $pos_x
 * @property float $pos_y
 * @property bool $is_wheelchair
 * @property bool $is_active
 */
class Seat extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%seat}}';
    }

    public function getVenue()
    {
        return $this->hasOne(Venue::class, ['id' => 'venue_id']);
    }

    /** ป้ายแสดงผลแบบเต็ม เช่น "ด้านหน้า (แถว A–O) F12" หรือ "F12" ถ้าสถานที่มีชั้นเดียว */
    public function getFullLabel(): string
    {
        return trim($this->venue->floorLabel($this->floor) . ' ' . $this->label);
    }
}
