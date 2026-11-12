<?php
/** @var yii\data\ActiveDataProvider $provider */

use app\models\Booking;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'ตั๋วของฉัน';
$f = Yii::$app->formatter;
?>
<h1 class="h4 mb-3">ตั๋วของฉัน</h1>
<?php if (!$provider->getTotalCount()): ?>
    <div class="text-center text-muted py-5">ยังไม่มีรายการจอง <a href="<?= Url::home() ?>">ดูการแสดง</a></div>
<?php endif ?>
<div class="list-group shadow-sm">
    <?php foreach ($provider->getModels() as $b): /** @var Booking $b */ ?>
        <a class="list-group-item list-group-item-action py-3"
           href="<?= Url::to([$b->status === Booking::STATUS_PAID ? '/booking/ticket' : '/booking/view', 'code' => $b->code]) ?>">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="fw-bold"><?= Html::encode($b->event->title) ?></div>
                    <div class="small text-muted"><?= $f->asDatetime($b->event->show_start) ?> · <?= $b->code ?></div>
                    <div class="small mt-1"><i class="bi bi-person-square"></i> <?= Html::encode($b->seatLabels()) ?></div>
                </div>
                <div class="text-end">
                    <?= Booking::statusBadge($b->status) ?>
                    <div class="small mt-1"><?= $f->asDecimal($b->total_amount, 2) ?> บาท</div>
                </div>
            </div>
        </a>
    <?php endforeach ?>
</div>
<div class="mt-3"><?= LinkPager::widget(['pagination' => $provider->pagination]) ?></div>
