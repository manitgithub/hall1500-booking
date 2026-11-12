<?php
/** @var yii\web\View $this */
/** @var app\models\PaymentSettingsForm $model */

use app\components\PromptPay;
use app\components\QrImage;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'ตั้งค่าระบบ';
?>
<h1 class="h4 mb-3"><?= $this->title ?></h1>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <?php $form = ActiveForm::begin([
                'fieldConfig' => [
                    'options' => ['class' => 'mb-3'], 'labelOptions' => ['class' => 'form-label'],
                    'inputOptions' => ['class' => 'form-control'], 'errorOptions' => ['class' => 'invalid-feedback d-block'],
                ],
            ]) ?>
            <?= $form->field($model, 'site_name')->hint('แสดงที่แถบเมนู ชื่อแท็บเบราว์เซอร์ และท้ายหน้า เช่น "ระบบจองที่นั่ง หอประชุมเทศบาล"') ?>
            <h2 class="h6 mt-4">รับชำระเงิน PromptPay</h2>
            <div class="form-text mt-n2 mb-2">บัญชีกลาง ใช้กับงานของแอดมินที่ไม่ได้ตั้งบัญชีของงานไว้ (งานของผู้จัดงานต้องใช้บัญชีของผู้จัดเอง)</div>
            <?= $form->field($model, 'promptpay_id')->textInput(['inputmode' => 'numeric', 'autocomplete' => 'off'])
                ->hint('เบอร์มือถือ 10 หลัก หรือเลขประจำตัวผู้เสียภาษี 13 หลักของหน่วยงาน') ?>
            <?= $form->field($model, 'promptpay_name')->hint('ควรตรงกับชื่อที่แอปธนาคารแสดงตอนสแกน เพื่อให้ผู้โอนมั่นใจ') ?>
            <?php $stats = \app\models\SiteStats::get(); ?>
            <?= $form->field($model, 'stats_min_events', ['template' => "{label}\n<div class=\"input-group\" style=\"max-width:220px\">{input}<span class=\"input-group-text\">งาน</span></div>\n{hint}\n{error}"])
                ->input('number', ['min' => 0])
                ->hint('นับจากข้อมูลจริง ตอนนี้: ' . number_format($stats['events']) . ' งาน · ' . number_format($stats['organizers']) . ' ผู้จัดงาน · '
                    . number_format($stats['tickets']) . ' ที่นั่งที่ขายแล้ว · ' . number_format($stats['venues']) . ' สถานที่ — ใส่ 0 เพื่อไม่แสดง') ?>
            <h2 class="h6 mt-4">ผู้จัดงาน</h2>
            <?= $form->field($model, 'organizer_terms')->textarea(['rows' => 12, 'class' => 'form-control small'])
                ->hint('ผู้สมัครเป็นผู้จัดงานต้องอ่านและยอมรับข้อความนี้ ระบบเก็บสำเนาฉบับที่แต่ละคนยอมรับไว้ในใบสมัคร · '
                    . \yii\helpers\Html::a('ดูหน้าเงื่อนไข', ['/site/terms'], ['target' => '_blank'])) ?>
            <button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button>
            <?php ActiveForm::end() ?>
        </div></div>
        <p class="small text-muted mt-2">
            การเปลี่ยนหมายเลขมีผลกับ QR ที่สร้างหลังจากนี้ทันที ผู้ที่เปิดหน้าชำระเงินค้างไว้ควรรีเฟรชหน้าก่อนโอน
        </p>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="small text-muted mb-2">ตัวอย่าง QR (ไม่ระบุยอดเงิน)</div>
            <?php try {
                $png = QrImage::png(PromptPay::payload((string)$model->promptpay_id), 220);
                echo Html::img('data:image/png;base64,' . base64_encode($png), ['class' => 'img-fluid border rounded', 'alt' => 'PromptPay QR']);
            } catch (\InvalidArgumentException $e) {
                echo '<div class="text-muted py-5">ยังไม่ได้ตั้งหมายเลข PromptPay</div>';
            } ?>
            <div class="fw-bold mt-2"><?= Html::encode($model->promptpay_name) ?></div>
            <div class="text-muted"><?= Html::encode($model->promptpay_id) ?></div>
        </div></div>
    </div>
</div>
