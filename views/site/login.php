<?php
/** @var bool $lineReady */
/** @var bool $devLogin */

use yii\helpers\Url;

$this->title = 'เข้าสู่ระบบ';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <i class="bi bi-ticket-perforated fs-1 text-primary"></i>
                <h1 class="h4 mt-2">เข้าสู่ระบบเพื่อจองที่นั่ง</h1>
                <p class="text-muted">ใช้บัญชี LINE ของคุณ ไม่ต้องสมัครสมาชิกเพิ่ม<br>เราจะแจ้งผลการชำระเงินและส่งตั๋วผ่าน LINE</p>
                <?php if ($lineReady): ?>
                    <a href="<?= Url::to(['/auth/line']) ?>" class="btn btn-line btn-lg w-100">
                        <i class="bi bi-line me-2"></i>เข้าสู่ระบบด้วย LINE
                    </a>
                <?php else: ?>
                    <div class="alert alert-warning text-start small mb-0">
                        ยังไม่ได้ตั้งค่า LINE Login — ใส่ <code>LINE_CHANNEL_ID</code> และ <code>LINE_CHANNEL_SECRET</code> ในไฟล์ <code>.env</code>
                    </div>
                <?php endif ?>
                <?php if ($devLogin): ?>
                    <hr>
                    <div class="small text-muted mb-2">โหมดทดสอบ (DEV_LOGIN=true)</div>
                    <div class="btn-group">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['/auth/dev-login', 'role' => 'user']) ?>">ผู้ใช้</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['/auth/dev-login', 'role' => 'organizer']) ?>">ผู้จัดงาน</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['/auth/dev-login', 'role' => 'staff']) ?>">เจ้าหน้าที่</a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['/auth/dev-login', 'role' => 'admin']) ?>">แอดมิน</a>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
