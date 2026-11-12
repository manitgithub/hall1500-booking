<?php
/** @var app\models\Event $event */
/** @var app\models\EventZonePrice[] $prices */

use app\models\Event;
use app\models\Hall;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = $event->isNewRecord ? 'เพิ่มการแสดง' : 'แก้ไข: ' . $event->title;
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$seatCount = (new \yii\db\Query())->select(['zone_code', 'c' => 'COUNT(*)'])->from('{{%seat}}')
    ->where(['is_active' => 1])->groupBy('zone_code')->indexBy('zone_code')->column();
?>
<h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
<?php $form = ActiveForm::begin([
    'options' => ['enctype' => 'multipart/form-data'],
    'fieldConfig' => [
        'options' => ['class' => 'mb-3'], 'labelOptions' => ['class' => 'form-label'],
        'inputOptions' => ['class' => 'form-control'], 'errorOptions' => ['class' => 'invalid-feedback d-block'],
    ],
]) ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <?= $form->field($event, 'title') ?>
            <div class="row">
                <div class="col-md-6"><?= $form->field($event, 'category')->dropDownList(Event::categories(), ['class' => 'form-select']) ?></div>
                <div class="col-md-6"><?= $form->field($event, 'organizer') ?></div>
            </div>
            <?= $form->field($event, 'description')->textarea(['rows' => 4]) ?>
            <div class="row">
                <div class="col-md-6"><?= $form->field($event, 'show_start')->input('datetime-local', ['value' => $dt($event->show_start)]) ?></div>
                <div class="col-md-6"><?= $form->field($event, 'show_end')->input('datetime-local', ['value' => $dt($event->show_end)]) ?></div>
                <div class="col-md-6"><?= $form->field($event, 'sale_start')->input('datetime-local', ['value' => $dt($event->sale_start)])->hint('ว่าง = เปิดขายทันทีเมื่อเผยแพร่') ?></div>
                <div class="col-md-6"><?= $form->field($event, 'sale_end')->input('datetime-local', ['value' => $dt($event->sale_end)])->hint('ว่าง = ขายถึงเวลาเริ่มแสดง') ?></div>
                <div class="col-md-6"><?= $form->field($event, 'max_seats_per_booking')->input('number') ?></div>
                <div class="col-md-6"><?= $form->field($event, 'hold_minutes')->input('number') ?></div>
            </div>
            <?= $form->field($event, 'posterFile')->fileInput(['accept' => 'image/*']) ?>
            <?php if ($event->posterUrl): ?><img src="<?= Html::encode($event->posterUrl) ?>" height="120" class="rounded mb-3" alt=""><?php endif ?>
            <?= $form->field($event, 'status')->dropDownList(Event::statusLabels(), ['class' => 'form-select'])
                ->hint('เลือก "เปิดขาย" แล้วบันทึก ระบบจะสร้างรายการที่นั่งตามราคาโซนให้อัตโนมัติ') ?>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h6">ราคาตามโซน</h2>
            <table class="table table-sm align-middle">
                <thead><tr><th>โซน</th><th class="text-end">ที่นั่ง</th><th style="width:130px">ราคา (บาท)</th><th>เปิดขาย</th></tr></thead>
                <?php foreach ($prices as $code => $zp): $z = Hall::zones()[$code]; ?>
                    <tr>
                        <td><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:<?= $z['color'] ?>"></span><?= Html::encode($z['label']) ?></td>
                        <td class="text-end"><?= (int)($seatCount[$code] ?? 0) ?></td>
                        <td>
                            <?= Html::textInput("ZonePrice[$code][price]", $zp->price, ['class' => 'form-control form-control-sm' . ($zp->hasErrors() ? ' is-invalid' : ''), 'type' => 'number', 'step' => '0.01', 'min' => 0]) ?>
                        </td>
                        <td class="text-center"><?= Html::checkbox("ZonePrice[$code][is_open]", (bool)$zp->is_open, ['class' => 'form-check-input']) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
            <div class="small text-muted">เปลี่ยนราคาภายหลังจะมีผลกับที่นั่งที่ยังว่างเท่านั้น</div>
        </div></div>
        <div class="d-grid mt-3"><button class="btn btn-primary btn-lg">บันทึก</button></div>
        <?php if (!$event->isNewRecord): ?>
            <div class="d-flex gap-2 mt-2">
                <a class="btn btn-outline-secondary flex-fill" href="<?= Url::to(['event-seats', 'id' => $event->id]) ?>">จัดการผังที่นั่ง</a>
                <a class="btn btn-outline-secondary flex-fill" href="<?= Url::to(['/event/view', 'id' => $event->id]) ?>">ดูหน้าจอง</a>
            </div>
        <?php endif ?>
    </div>
</div>
<?php ActiveForm::end() ?>
