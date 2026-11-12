<?php
/** @var yii\web\View $this */
/** @var app\models\OrganizerApplication $model */
/** @var app\models\OrganizerApplication|null $previous */
/** @var string $terms */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'สมัครเป็นผู้จัดงาน';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="h4 mb-1"><?= $this->title ?></h1>
        <p class="text-muted">
            ผู้จัดงานสร้างการแสดง ตั้งราคา รับเงินเข้าบัญชี PromptPay ของตัวเอง และตรวจสลิปของงานตัวเองได้
            หลังผู้ดูแลระบบตรวจเอกสารและอนุมัติ
        </p>
        <?php if ($previous && $previous->status === 'rejected'): ?>
            <div class="alert alert-danger">
                ใบสมัครก่อนหน้าไม่ผ่านการอนุมัติ<?= $previous->review_note ? ': ' . Html::encode($previous->review_note) : '' ?>
                — แก้ไขแล้วส่งใหม่ได้
            </div>
        <?php endif ?>

        <?php $form = ActiveForm::begin([
            'options' => ['enctype' => 'multipart/form-data'],
            'fieldConfig' => [
                'options' => ['class' => 'mb-3'], 'labelOptions' => ['class' => 'form-label'],
                'inputOptions' => ['class' => 'form-control'], 'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]) ?>
        <?= Html::hiddenInput('terms_hash', hash('sha256', $terms)) ?>
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">1. ข้อมูลผู้จัดงาน</h2>
            <?= $form->field($model, 'full_name') ?>
            <?= $form->field($model, 'organization') ?>
            <div class="row">
                <div class="col-md-6"><?= $form->field($model, 'phone')->input('tel', ['inputmode' => 'numeric']) ?></div>
                <div class="col-md-6"><?= $form->field($model, 'email')->input('email') ?></div>
            </div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">2. สำเนาบัตรประชาชน</h2>
            <?= $form->field($model, 'idCardUpload')->fileInput(['accept' => 'image/jpeg,image/png,image/webp,application/pdf', 'class' => 'form-control'])
                ->hint('ไฟล์ JPG, PNG, WEBP หรือ PDF ไม่เกิน 5 MB · แนะนำให้เขียนกำกับบนสำเนาว่า "ใช้สมัครผู้จัดงานเท่านั้น" พร้อมลงชื่อ<br>'
                    . 'ไฟล์ไม่ถูกเผยแพร่ เปิดดูได้เฉพาะผู้ดูแลระบบ') ?>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">3. เงื่อนไขการใช้งาน</h2>
            <div class="terms-box border rounded p-3 mb-3 small bg-light" id="terms-box" tabindex="0"><?= nl2br(Html::encode($terms)) ?></div>
            <div class="small text-muted mb-2" id="terms-hint"><i class="bi bi-arrow-down-circle"></i> เลื่อนอ่านให้จบเพื่อยอมรับเงื่อนไข</div>
            <?= $form->field($model, 'acceptTerms', ['options' => ['class' => 'mb-2']])
                ->checkbox(['class' => 'form-check-input', 'id' => 'accept-terms', 'disabled' => true]) ?>
            <?= $form->field($model, 'pdpa_consent')->checkbox(['class' => 'form-check-input']) ?>
        </div></div>

        <button class="btn btn-primary btn-lg w-100" id="submit-btn">ส่งใบสมัคร</button>
        <?php ActiveForm::end() ?>
    </div>
</div>
<?php $this->registerCss('.terms-box{max-height:320px;overflow:auto;white-space:normal}'); ?>
<?php $this->registerJs(<<<'JS'
(function () {
  const box = document.getElementById('terms-box'), cb = document.getElementById('accept-terms'), hint = document.getElementById('terms-hint');
  function check() {
    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 8) {
      cb.disabled = false;
      hint.innerHTML = '<i class="bi bi-check-circle text-success"></i> อ่านเงื่อนไขครบแล้ว';
      box.removeEventListener('scroll', check);
    }
  }
  box.addEventListener('scroll', check);
  check();
})();
JS); ?>
