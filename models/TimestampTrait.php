<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;

trait TimestampTrait
{
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => fn() => date('Y-m-d H:i:s'),
            ],
        ];
    }
}
