<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * ที่นั่งทางกายภาพ
 * @property int $id
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

    /** ป้ายแสดงผลแบบเต็ม เช่น "ชั้นล่าง F12" */
    public function getFullLabel(): string
    {
        return Hall::floorLabel($this->floor) . ' ' . $this->label;
    }
}
