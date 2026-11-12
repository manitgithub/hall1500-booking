<?php
/** @var yii\data\ActiveDataProvider $provider */
/** @var string $status */

use app\models\Booking;
use app\models\Payment;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'ตรวจสลิปการโอน';
$f = Yii::$app->formatter;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= $this->title ?></h1>
    <div class="btn-group">
        <?php foreach (['pending' => 'รอตรวจ', 'success' => 'อนุมัติแล้ว', 'rejected' => 'ไม่ผ่าน', '' => 'ทั้งหมด'] as $k => $label): ?>
            <a href="<?= Url::to(['payments', 'status' => $k]) ?>" class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $label ?></a>
        <?php endforeach ?>
    </div>
</div>
<?php if (!$provider->getTotalCount()): ?><div class="text-center text-muted py-5">ไม่มีรายการ</div><?php endif ?>
<div class="row g-3">
<?php foreach ($provider->getModels() as $p): /** @var Payment $p */ $b = $p->booking; ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm">
            <a href="<?= Html::encode($p->slipUrl) ?>" target="_blank"><img src="<?= Html::encode($p->slipUrl) ?>" class="card-img-top" style="height:320px;object-fit:contain;background:#eee" alt="slip"></a>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><b><?= $b->code ?></b><?= Booking::statusBadge($b->status) ?></div>
                <div><?= Html::encode($b->user->display_name) ?></div>
                <div class="text-muted"><?= Html::encode($b->event->title) ?></div>
                <div>ที่นั่ง: <?= Html::encode($b->seatLabels()) ?></div>
                <div class="mt-1">ยอดที่ต้องได้รับ <b class="fs-6 text-primary"><?= $f->asDecimal($p->amount, 2) ?></b> บาท</div>
                <div>เวลาโอน (ผู้ใช้แจ้ง): <?= $f->asDatetime($p->transferred_at) ?></div>
                <div class="text-muted">ส่งเมื่อ <?= $f->asDatetime($p->created_at) ?></div>
                <?php if ($p->status !== Payment::STATUS_PENDING): ?>
                    <div class="mt-2"><span class="badge bg-<?= $p->status === 'success' ? 'success' : 'danger' ?>"><?= Payment::statusLabels()[$p->status] ?></span>
                        <?= $p->verifier ? 'โดย ' . Html::encode($p->verifier->display_name) : '' ?> <?= Html::encode($p->note) ?></div>
                <?php endif ?>
            </div>
            <?php if ($p->status === Payment::STATUS_PENDING): ?>
                <div class="card-footer bg-white d-flex gap-2">
                    <?= Html::beginForm(['payment-approve', 'id' => $p->id], 'post', ['class' => 'flex-fill']) ?>
                    <button class="btn btn-success w-100" onclick="return confirm('ยืนยันว่าได้รับเงินถูกต้อง?')">อนุมัติ</button>
                    <?= Html::endForm() ?>
                    <?= Html::beginForm(['payment-reject', 'id' => $p->id], 'post', ['class' => 'flex-fill', 'onsubmit' => "var r=prompt('เหตุผลที่ไม่ผ่าน','ยอดเงินไม่ตรง');if(r===null)return false;this.reason.value=r;"]) ?>
                    <?= Html::hiddenInput('reason') ?>
                    <button class="btn btn-outline-danger w-100">ไม่ผ่าน</button>
                    <?= Html::endForm() ?>
                </div>
            <?php endif ?>
        </div>
    </div>
<?php endforeach ?>
</div>
<div class="mt-3"><?= LinkPager::widget(['pagination' => $provider->pagination]) ?></div>
