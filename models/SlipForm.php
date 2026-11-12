<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

class SlipForm extends Model
{
    /** @var UploadedFile */
    public $slip;
    public $transferred_at;

    public function rules()
    {
        return [
            [['slip', 'transferred_at'], 'required'],
            [['slip'], 'image', 'extensions' => 'jpg, jpeg, png, webp',
                'maxSize' => Yii::$app->params['payment']['slipMaxSize']],
            [['transferred_at'], 'filter', 'filter' => fn($v) => ($t = strtotime(str_replace('T', ' ', $v))) ? date('Y-m-d H:i:s', $t) : $v],
            [['transferred_at'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
        ];
    }

    public function attributeLabels()
    {
        return ['slip' => 'สลิปการโอนเงิน', 'transferred_at' => 'วันเวลาที่โอน'];
    }
}
