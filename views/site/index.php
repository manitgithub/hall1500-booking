<?php
/** @var app\models\Event[] $events */

use app\models\Event;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'การแสดงที่เปิดจอง';
$f = Yii::$app->formatter;
?>
<div class="d-flex align-items-end justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">การแสดงที่กำลังจะมาถึง</h1>
        <div class="text-muted">คอนเสิร์ต ละคร สัมมนา และกิจกรรม เลือกที่นั่งและจองออนไลน์ได้ทันที</div>
    </div>
</div>

<?php if (!$events): ?>
    <div class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>ยังไม่มีการแสดงที่เปิดจอง</div>
<?php endif ?>

<div class="row g-4">
    <?php foreach ($events as $e): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="<?= Url::to(['/event/view', 'id' => $e->id]) ?>" class="card h-100 shadow-sm text-decoration-none text-body event-card">
                <?php if ($e->posterUrl): ?>
                    <img src="<?= Html::encode($e->posterUrl) ?>" class="card-img-top" alt="<?= Html::encode($e->title) ?>">
                <?php else: ?>
                    <div class="card-img-top poster-ph"><i class="bi bi-<?= $e->category === 'drama' ? 'mask' : 'music-note-beamed' ?>"></i></div>
                <?php endif ?>
                <div class="card-body">
                    <div class="small text-muted mb-1"><?= Html::encode(Event::categories()[$e->category] ?? '') ?></div>
                    <h2 class="h6 card-title fw-bold"><?= Html::encode($e->title) ?></h2>
                    <div class="small"><i class="bi bi-calendar-event"></i> <?= $f->asDatetime($e->show_start) ?></div>
                    <div class="small"><i class="bi bi-geo-alt"></i> <?= Html::encode($e->venue->name) ?></div>
                    <div class="small"><i class="bi bi-cash"></i> <?= $e->priceRange() ?></div>
                </div>
                <div class="card-footer bg-white border-0 pb-3">
                    <span class="badge <?= $e->isOnSale() ? 'bg-success' : 'bg-secondary' ?>"><?= Html::encode($e->saleStatusText()) ?></span>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<section class="promo mt-5 rounded-4 shadow-sm overflow-hidden">
    <div class="row g-0">
        <div class="col-lg-7 p-4 p-md-5 align-self-center">
            <span class="badge promo-badge mb-3"><i class="bi bi-gift"></i> ใช้งานฟรี</span>
            <h2 class="h3 fw-bold mb-2">อยากมีระบบจองที่นั่งแบบนี้ให้สถานที่ของคุณ?</h2>
            <p class="mb-4 opacity-75">
                ระบบจองที่นั่งออนไลน์สำหรับหอประชุม โรงละคร ห้องประชุม และงานสัมมนา รองรับหลายสถานที่ สร้างผังที่นั่งได้เองในหน้าเว็บ
                เปิดให้หน่วยงานและสถานศึกษาใช้งานฟรี สนใจติดต่อผู้พัฒนาได้เลย
            </p>
            <a href="https://manitgithub.github.io/" target="_blank" rel="noopener" class="btn btn-warning btn-lg fw-semibold">
                <i class="bi bi-chat-dots"></i> ติดต่อผู้พัฒนา
            </a>
            <div class="small mt-2 opacity-75">มานิต จิตต์ประไพย</div>
        </div>
        <div class="col-lg-5 p-4 p-md-5 promo-features d-flex align-items-center">
            <ul class="list-unstyled mb-0">
                <li><i class="bi bi-line"></i> เข้าสู่ระบบด้วย LINE ไม่ต้องสมัครสมาชิก</li>
                <li><i class="bi bi-grid-3x3-gap"></i> ผังที่นั่งแบบโต้ตอบ แยกโซนราคา ใช้บนมือถือได้</li>
                <li><i class="bi bi-qr-code"></i> ชำระผ่าน QR PromptPay แนบสลิป หรือบัตรเครดิต</li>
                <li><i class="bi bi-ticket-perforated"></i> E-ticket พร้อม QR สแกนเช็คอินหน้างาน</li>
                <li><i class="bi bi-speedometer2"></i> หลังบ้านจัดการการแสดง ตรวจสลิป ส่งออกรายงาน</li>
            </ul>
        </div>
    </div>
</section>
