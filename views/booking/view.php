<?php
/** @var yii\web\View $this */
/** @var app\models\Booking $b */
/** @var app\models\SlipForm $slipForm */
/** @var bool $omiseEnabled */

use app\models\Booking;
use app\models\Payment;
use app\models\Setting;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'รายการจอง ' . $b->code;
$f = Yii::$app->formatter;
$pp = ['promptpayId' => Setting::promptpayId(), 'promptpayName' => Setting::promptpayName()];
$last = $b->latestPayment;
?>
<div class="row g-4">
    <div class="col-lg-5 order-lg-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted">รหัสจอง</div>
                        <div class="fw-bold fs-5"><?= $b->code ?></div>
                    </div>
                    <?= Booking::statusBadge($b->status) ?>
                </div>
                <hr>
                <div class="fw-bold"><?= Html::encode($b->event->title) ?></div>
                <div class="small text-muted mb-2"><?= $f->asDatetime($b->event->show_start) ?> · ห้องประชุม 1500</div>
                <table class="table table-sm small mb-2">
                    <?php foreach ($b->bookingSeats as $bs): ?>
                        <tr><td><?= Html::encode($bs->seat->fullLabel) ?> <span class="text-muted">(<?= Html::encode(\app\models\Hall::zoneLabel($bs->seat->zone_code)) ?>)</span></td>
                            <td class="text-end"><?= $f->asDecimal($bs->price, 2) ?></td></tr>
                    <?php endforeach ?>
                    <tr class="fw-bold"><td>รวม <?= $b->seat_count ?> ที่นั่ง</td><td class="text-end"><?= $f->asDecimal($b->total_amount, 2) ?> บาท</td></tr>
                </table>
                <?php if ($b->note): ?><div class="alert alert-warning small mb-0"><?= Html::encode($b->note) ?></div><?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7 order-lg-1">
        <?php if ($b->isPayable()): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split"></i> ที่นั่งถูกล็อกไว้ให้คุณ กรุณาชำระภายใน</span>
                <span class="countdown fs-5" id="countdown" data-left="<?= $b->secondsLeft() ?>">--:--</span>
            </div>
            <?php if ($last && $last->status === Payment::STATUS_REJECTED): ?>
                <div class="alert alert-danger">สลิปก่อนหน้าไม่ผ่านการตรวจสอบ: <?= Html::encode($last->note) ?> — กรุณาแนบใหม่</div>
            <?php endif ?>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5"><i class="bi bi-qr-code"></i> ชำระผ่าน PromptPay</h2>
                    <?php if ($pp['promptpayId']): ?>
                        <div class="row align-items-center">
                            <div class="col-sm-6 text-center">
                                <img src="<?= Url::to(['/payment/promptpay-qr', 'code' => $b->code]) ?>" class="img-fluid border rounded" alt="PromptPay QR" style="max-width:260px">
                                <div class="small text-muted mt-1">สแกนด้วยแอปธนาคาร</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="small text-muted">ชื่อบัญชี</div>
                                <div class="fw-bold mb-2"><?= Html::encode($pp['promptpayName']) ?></div>
                                <div class="small text-muted">ยอดที่ต้องโอน</div>
                                <div class="fw-bold fs-4 text-primary mb-3"><?= $f->asDecimal($b->total_amount, 2) ?> บาท</div>
                                <?php $form = ActiveForm::begin([
                                    'action' => ['/payment/slip', 'code' => $b->code],
                                    'options' => ['enctype' => 'multipart/form-data'],
                                    'fieldConfig' => [
                                        'options' => ['class' => 'mb-2'], 'labelOptions' => ['class' => 'form-label small'],
                                        'inputOptions' => ['class' => 'form-control form-control-sm'],
                                        'errorOptions' => ['class' => 'invalid-feedback d-block'],
                                    ],
                                ]) ?>
                                <?= $form->field($slipForm, 'slip')->fileInput(['accept' => 'image/*', 'class' => 'form-control form-control-sm']) ?>
                                <?= $form->field($slipForm, 'transferred_at')->input('datetime-local') ?>
                                <button class="btn btn-success w-100">แนบสลิปเพื่อยืนยัน</button>
                                <?php ActiveForm::end() ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">ยังไม่ได้ตั้งค่า PROMPTPAY_ID</div>
                    <?php endif ?>
                </div>
            </div>

            <?php if ($omiseEnabled): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h5"><i class="bi bi-lightning-charge"></i> ชำระแบบยืนยันอัตโนมัติ</h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <?= Html::beginForm(['/payment/omise-promptpay', 'code' => $b->code]) ?>
                            <button class="btn btn-outline-primary"><i class="bi bi-qr-code-scan"></i> PromptPay (ตัดยอดอัตโนมัติ)</button>
                            <?= Html::endForm() ?>
                            <?= Html::beginForm(['/payment/omise-card', 'code' => $b->code], 'post', ['id' => 'cardForm']) ?>
                            <?= Html::hiddenInput('omiseToken', '', ['id' => 'omiseToken']) ?>
                            <button type="button" class="btn btn-outline-primary" id="cardBtn"><i class="bi bi-credit-card"></i> บัตรเครดิต/เดบิต</button>
                            <?= Html::endForm() ?>
                        </div>
                    </div>
                </div>
                <?php
                $this->registerJsFile('https://cdn.omise.co/omise.js');
                $pk = Html::encode(Yii::$app->omise->publicKey);
                $amt = (int)round($b->total_amount * 100);
                $this->registerJs(<<<JS
OmiseCard.configure({ publicKey: '$pk', currency: 'THB', frameLabel: 'Hall 1500', submitLabel: 'ชำระเงิน', locale: 'th' });
document.getElementById('cardBtn').addEventListener('click', function () {
  OmiseCard.open({ amount: $amt, defaultPaymentMethod: 'credit_card', onCreateTokenSuccess: function (t) {
    document.getElementById('omiseToken').value = t; document.getElementById('cardForm').submit();
  }});
});
JS);
                ?>
            <?php endif ?>

            <?= Html::beginForm(['/booking/cancel', 'code' => $b->code], 'post', ['onsubmit' => "return confirm('ยกเลิกการจองและปล่อยที่นั่ง?')"]) ?>
            <button class="btn btn-link text-danger p-0">ยกเลิกการจองนี้</button>
            <?= Html::endForm() ?>

        <?php elseif ($b->status === Booking::STATUS_VERIFYING): ?>
            <div class="card border-0 shadow-sm text-center p-4">
                <i class="bi bi-hourglass-top fs-1 text-info"></i>
                <h2 class="h5 mt-2">ได้รับสลิปแล้ว รอเจ้าหน้าที่ตรวจสอบ</h2>
                <p class="text-muted mb-0">ที่นั่งของคุณถูกล็อกไว้ระหว่างตรวจสอบ เมื่ออนุมัติแล้วจะแจ้งทาง LINE และแสดงตั๋วในหน้า "ตั๋วของฉัน"</p>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm text-center p-4">
                <i class="bi bi-x-circle fs-1 text-secondary"></i>
                <h2 class="h5 mt-2">รายการนี้<?= Booking::statusLabels()[$b->status] ?></h2>
                <a href="<?= Url::to(['/event/view', 'id' => $b->event_id]) ?>" class="btn btn-primary mt-2">เลือกที่นั่งใหม่</a>
            </div>
        <?php endif ?>
    </div>
</div>
<?php
$this->registerJs(<<<JS
(function () {
  const el = document.getElementById('countdown');
  if (!el) return;
  let left = parseInt(el.dataset.left, 10);
  const tick = () => {
    if (left <= 0) { location.reload(); return; }
    el.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
    left--;
  };
  tick(); setInterval(tick, 1000);
})();
JS);
