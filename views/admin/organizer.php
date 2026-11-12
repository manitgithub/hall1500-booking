<?php
/** @var app\models\OrganizerApplication $app */
/** @var app\models\Event[] $events */

use app\models\Event;
use app\models\OrganizerApplication as OA;
use app\models\Setting;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'ผู้จัดงาน: ' . $app->full_name;
$f = Yii::$app->formatter;
$cardUrl = Url::to(['organizer-id-card', 'id' => $app->id]);
$isPdf = $app->id_card_mime === 'application/pdf';
$termsChanged = $app->terms_hash !== hash('sha256', Setting::organizerTerms());
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['organizers']) ?>" class="btn btn-link">← ใบสมัครทั้งหมด</a>
</div>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between">
                <h2 class="h6">ข้อมูลผู้สมัคร</h2>
                <span class="badge bg-<?= OA::statusBadge($app->status) ?> align-self-start"><?= OA::statusLabels()[$app->status] ?></span>
            </div>
            <table class="table table-sm mb-0">
                <tr><th class="text-muted fw-normal" style="width:40%">ชื่อ-นามสกุล</th><td><?= Html::encode($app->full_name) ?></td></tr>
                <tr><th class="text-muted fw-normal">หน่วยงาน</th><td><?= Html::encode($app->organization ?: '-') ?></td></tr>
                <tr><th class="text-muted fw-normal">เบอร์โทร</th><td><?= Html::encode($app->phone) ?></td></tr>
                <tr><th class="text-muted fw-normal">อีเมล</th><td><?= Html::encode($app->email ?: '-') ?></td></tr>
                <tr><th class="text-muted fw-normal">บัญชี LINE</th><td><?= Html::encode($app->user->display_name) ?> (id <?= $app->user_id ?>)</td></tr>
                <tr><th class="text-muted fw-normal">ส่งเมื่อ</th><td><?= $f->asDatetime($app->created_at) ?></td></tr>
                <tr><th class="text-muted fw-normal">ยอมรับเงื่อนไข</th><td>
                    <?= $f->asDatetime($app->terms_accepted_at) ?> · IP <?= Html::encode($app->accepted_ip ?: '-') ?>
                    <details class="small mt-1"><summary>ฉบับที่ยอมรับ<?= $termsChanged ? ' (ต่างจากฉบับปัจจุบัน)' : '' ?></summary>
                        <div class="border rounded p-2 mt-1 bg-light" style="max-height:240px;overflow:auto"><?= nl2br(Html::encode($app->terms_text)) ?></div>
                    </details>
                </td></tr>
                <tr><th class="text-muted fw-normal">ยินยอมเก็บสำเนาบัตร</th><td><?= $app->pdpa_consent ? 'ยินยอม' : '-' ?></td></tr>
                <?php if ($app->reviewed_at): ?>
                    <tr><th class="text-muted fw-normal">ตรวจโดย</th><td><?= Html::encode($app->reviewer->display_name ?? '-') ?> · <?= $f->asDatetime($app->reviewed_at) ?>
                        <?php if ($app->review_note): ?><div class="small">หมายเหตุ: <?= Html::encode($app->review_note) ?></div><?php endif ?></td></tr>
                <?php endif ?>
            </table>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">ผลการตรวจสอบ</h2>
            <?php if ($app->status === OA::STATUS_PENDING): ?>
                <p class="small text-muted">ตรวจว่าชื่อในบัตรตรงกับที่กรอก บัตรยังไม่หมดอายุ และภาพชัดเจน</p>
                <?= Html::beginForm(['organizer-review', 'id' => $app->id]) ?>
                <textarea name="note" class="form-control mb-2" rows="2" placeholder="หมายเหตุ (จำเป็นเมื่อไม่อนุมัติ เช่น ภาพบัตรไม่ชัด)"></textarea>
                <div class="d-flex gap-2">
                    <button name="status" value="<?= OA::STATUS_APPROVED ?>" class="btn btn-success flex-fill">อนุมัติเป็นผู้จัดงาน</button>
                    <button name="status" value="<?= OA::STATUS_REJECTED ?>" class="btn btn-outline-danger flex-fill">ไม่อนุมัติ</button>
                </div>
                <?= Html::endForm() ?>
            <?php elseif ($app->status === OA::STATUS_APPROVED): ?>
                <?= Html::beginForm(['organizer-review', 'id' => $app->id], 'post', ['onsubmit' => "return confirm('ระงับสิทธิ์และปิดการขายงานที่เปิดอยู่ของผู้จัดคนนี้?')"]) ?>
                <textarea name="note" class="form-control mb-2" rows="2" placeholder="เหตุผลที่ระงับสิทธิ์"></textarea>
                <button name="status" value="<?= OA::STATUS_REVOKED ?>" class="btn btn-outline-danger w-100">ระงับสิทธิ์ผู้จัดงาน</button>
                <div class="form-text">งานที่กำลังเปิดขายของผู้จัดคนนี้จะถูกปิดการขาย ตั๋วที่ขายไปแล้วยังใช้ได้</div>
                <?= Html::endForm() ?>
            <?php elseif ($app->status === OA::STATUS_REVOKED): ?>
                <?= Html::beginForm(['organizer-review', 'id' => $app->id]) ?>
                <input type="hidden" name="note" value="คืนสิทธิ์">
                <button name="status" value="<?= OA::STATUS_APPROVED ?>" class="btn btn-outline-success w-100">คืนสิทธิ์ผู้จัดงาน</button>
                <?= Html::endForm() ?>
            <?php else: ?>
                <p class="text-muted small mb-0">ไม่อนุมัติแล้ว ผู้สมัครแก้ไขและส่งใบสมัครใหม่ได้</p>
            <?php endif ?>
        </div></div>

        <?php if ($events): ?>
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6">งานของผู้จัดคนนี้</h2>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($events as $e): ?>
                        <li class="mb-1"><a href="<?= Url::to(['event-update', 'id' => $e->id]) ?>"><?= Html::encode($e->title) ?></a>
                            · <?= $f->asDatetime($e->show_start) ?> · <?= Event::statusLabels()[$e->status] ?></li>
                    <?php endforeach ?>
                </ul>
            </div></div>
        <?php endif ?>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">สำเนาบัตรประชาชน</h2>
                <a href="<?= $cardUrl ?>" target="_blank" rel="noopener" class="small">เปิดในแท็บใหม่</a>
            </div>
            <?php if ($isPdf): ?>
                <iframe src="<?= $cardUrl ?>" class="w-100 border rounded" style="height:480px" title="สำเนาบัตรประชาชน"></iframe>
            <?php else: ?>
                <img src="<?= $cardUrl ?>" class="img-fluid border rounded" alt="สำเนาบัตรประชาชน" referrerpolicy="no-referrer">
            <?php endif ?>
            <div class="form-text">ข้อมูลส่วนบุคคล — เปิดดูได้เฉพาะแอดมิน ห้ามดาวน์โหลดหรือส่งต่อโดยไม่จำเป็น</div>
        </div></div>
    </div>
</div>
