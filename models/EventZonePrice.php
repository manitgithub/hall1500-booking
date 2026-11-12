<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $event_id
 * @property string $zone_code
 * @property float $price
 * @property bool $is_open
 */
class EventZonePrice extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%event_zone_price}}';
    }

    public function rules()
    {
        return [
            [['price'], 'required'],
            [['price'], 'number', 'min' => 0],
            [['is_open'], 'boolean'],
        ];
    }

    public function getZoneLabel(): string
    {
        return Hall::zoneLabel($this->zone_code);
    }
}
