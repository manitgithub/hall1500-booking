<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $booking_id
 * @property int $seat_id
 * @property float $price
 * @property Seat $seat
 */
class BookingSeat extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%booking_seat}}';
    }

    public function getSeat()
    {
        return $this->hasOne(Seat::class, ['id' => 'seat_id']);
    }
}
