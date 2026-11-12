<?php
/** @var app\models\Event $event */
/** @var app\models\EventZonePrice[] $prices */
/** @var array $venueSeatCounts [venue_id => [zone => count]] */

use app\models\Event;
use app\models\Hall;
use app\models\Setting;
use app\models\Venue;
use yii\helpers\Json;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = $event->isNewRecord ? 'เพิ่มการแสดง' : 'แก้ไข: ' . $event->title;
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$seatCount = $venueSeatCounts[$event->venue_id] ?? [];
$isAdmin = Yii::$app->user->identity->isAdmin();
$ownAccount = (bool)$event->owner_id; // งานของผู้จัดงาน: ต้องใช้บัญชีของผู้จัดเอง
$venueOptions = Venue::options();
if ($event->venue_id && !isset($venueOptions[$event->venue_id]) && $event->venue) {
    $venueOptions[$event->venue_id] = $event->venue->name . ' (ปิดใช้งาน)';
}
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
            <?= $form->field($event, 'venue_id')->dropDownList($venueOptions, ['class' => 'form-select', 'id' => 'venue-id'])
                ->hint('ผังที่นั่งและจำนวนที่นั่งตามสถานที่ที่เลือก'
                    . ($isAdmin ? ' · <a href="' . \yii\helpers\Url::to(['venues']) . '">จัดการสถานที่</a>' : '')) ?>
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
            <?= $form->field($event, 'posterFile')->fileInput(['accept' => 'image/jpeg,image/png,image/webp', 'id' => 'poster-file'])
                ->hint('ขนาดแนะนำ <b>1200 × 1600 px</b> (แนวตั้ง 3:4) · อย่างน้อย 600 × 800 px · ไฟล์ JPG, PNG หรือ WEBP ไม่เกิน 3 MB<br>'
                    . 'หน้าแรกแสดงโปสเตอร์ในกรอบ 3:4 ถ้าสัดส่วนต่างจากนี้ ขอบบน-ล่างหรือซ้าย-ขวาจะถูกตัด'
                    . '<span id="poster-dim" class="d-block mt-1"></span>') ?>
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
                        <td class="text-end" data-zone-count="<?= Html::encode($code) ?>"><?= (int)($seatCount[$code] ?? 0) ?></td>
                        <td>
                            <?= Html::textInput("ZonePrice[$code][price]", $zp->price, ['class' => 'form-control form-control-sm' . ($zp->hasErrors() ? ' is-invalid' : ''), 'type' => 'number', 'step' => '0.01', 'min' => 0]) ?>
                        </td>
                        <td class="text-center"><?= Html::checkbox("ZonePrice[$code][is_open]", (bool)$zp->is_open, ['class' => 'form-check-input']) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
            <div class="small text-muted">เปลี่ยนราคาภายหลังจะมีผลกับที่นั่งที่ยังว่างเท่านั้น</div>
        </div></div>
        <div class="card border-0 shadow-sm mt-3"><div class="card-body">
            <h2 class="h6"><i class="bi bi-qr-code"></i> บัญชีรับเงินของงานนี้</h2>
            <?php if ($ownAccount): ?>
                <div class="alert alert-info small py-2">เงินค่าบัตรของงานนี้โอนเข้าบัญชีของผู้จัดงานโดยตรง ผู้จัดงานตรวจสลิปและรับผิดชอบการเงินเอง</div>
            <?php endif ?>
            <?= $form->field($event, 'promptpay_id')->textInput([
                'inputmode' => 'numeric', 'autocomplete' => 'off',
                'placeholder' => $ownAccount ? 'เบอร์มือถือ หรือเลขผู้เสียภาษีของผู้จัดงาน' : 'ค่ากลาง: ' . Setting::promptpayId(),
            ])->hint($ownAccount ? 'จำเป็น' : 'ว่าง = ใช้บัญชีกลางจาก <a href="' . \yii\helpers\Url::to(['settings']) . '">ตั้งค่าระบบ</a>') ?>
            <?= $form->field($event, 'promptpay_name')->textInput(['placeholder' => $ownAccount ? 'ชื่อบัญชีตามที่แอปธนาคารแสดง' : 'ค่ากลาง: ' . Setting::promptpayName()])
                ->hint('ชื่อที่ผู้โอนเห็นในหน้าชำระเงิน ควรตรงกับชื่อที่แอปธนาคารแสดง') ?>
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
<?php $this->registerJs(<<<'JS'
document.getElementById('poster-file')?.addEventListener('change', function () {
    const out = document.getElementById('poster-dim');
    out.textContent = '';
    out.className = 'd-block mt-1';
    const f = this.files[0];
    if (!f) return;
    const img = new Image();
    img.onload = function () {
        const w = img.naturalWidth, h = img.naturalHeight, ratio = w / h;
        let msg = 'ไฟล์ที่เลือก: ' + w + ' × ' + h + ' px', cls = 'text-success';
        if (w < 600 || h < 800) {
            msg += ' — เล็กกว่าที่แนะนำ อาจไม่คมชัด'; cls = 'text-danger';
        } else if (Math.abs(ratio - 0.75) > 0.03) {
            msg += ' — สัดส่วนไม่ใช่ 3:4 บางส่วนจะถูกตัด'; cls = 'text-warning';
        }
        if (f.size > 3 * 1024 * 1024) {
            msg += ' — ไฟล์ใหญ่เกิน 3 MB'; cls = 'text-danger';
        }
        out.textContent = msg;
        out.classList.add(cls, 'fw-semibold');
        URL.revokeObjectURL(img.src);
    };
    img.src = URL.createObjectURL(f);
});
JS); ?>
<?php $this->registerJs('const venueSeatCounts = ' . Json::htmlEncode((object)$venueSeatCounts) . ";\n" . <<<'JS'
document.getElementById('venue-id')?.addEventListener('change', function () {
    const counts = venueSeatCounts[this.value] || {};
    document.querySelectorAll('[data-zone-count]').forEach(td => { td.textContent = counts[td.dataset.zoneCount] || 0; });
});
JS); ?>
