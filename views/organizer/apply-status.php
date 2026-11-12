<?php
/** @var app\models\OrganizerApplication|null $app */
/** @var app\models\User $user */

use app\models\OrganizerApplication;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'สถานะผู้จัดงาน';
$f = Yii::$app->formatter;
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 text-center">
            <?php if ($user->canManageEvents()): ?>
                <i class="bi bi-patch-check-fill text-success fs-1"></i>
                <h1 class="h4 mt-2">คุณเป็น<?= $user->isAdmin() ? 'ผู้ดูแลระบบ' : 'ผู้จัดงาน' ?>แล้ว</h1>
                <p class="text-muted">สร้างและจัดการงานของคุณได้ที่หน้าจัดงาน</p>
                <a href="<?= Url::to(['/admin/index']) ?>" class="btn btn-primary">ไปหน้าจัดงาน</a>
            <?php elseif ($app && $app->status === OrganizerApplication::STATUS_PENDING): ?>
                <i class="bi bi-hourglass-split text-warning fs-1"></i>
                <h1 class="h4 mt-2">ใบสมัครรอตรวจสอบ</h1>
                <p class="text-muted mb-1">ส่งเมื่อ <?= $f->asDatetime($app->created_at) ?></p>
                <p class="text-muted">ผู้ดูแลระบบจะตรวจสอบเอกสาร เมื่ออนุมัติแล้วเมนู "จัดงาน" จะปรากฏให้ใช้งาน</p>
            <?php elseif ($app && $app->status === OrganizerApplication::STATUS_REVOKED): ?>
                <i class="bi bi-slash-circle text-secondary fs-1"></i>
                <h1 class="h4 mt-2">สิทธิ์ผู้จัดงานถูกระงับ</h1>
                <?php if ($app->review_note): ?><p class="text-muted"><?= Html::encode($app->review_note) ?></p><?php endif ?>
                <p class="text-muted">หากมีข้อสงสัย กรุณาติดต่อผู้ดูแลระบบ</p>
            <?php endif ?>
            <div class="mt-3 small"><a href="<?= Url::to(['/site/terms']) ?>">อ่านเงื่อนไขการใช้งานสำหรับผู้จัดงาน</a></div>
        </div></div>
    </div>
</div>
