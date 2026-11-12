<?php
/** @var app\models\Event[] $events */
/** @var array|null $stats สถิติจริง (null = ยังไม่ถึงขั้นต่ำที่ตั้งไว้) */

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
    <?php if ($stats): ?>
        <div class="promo-stats row g-0 text-center">
            <?php foreach ([
                ['events', 'bi-calendar-event', 'งานที่จัดผ่านระบบ'],
                ['tickets', 'bi-ticket-perforated', 'ที่นั่งที่จองและชำระแล้ว'],
                ['organizers', 'bi-people', 'ผู้จัดงาน'],
                ['venues', 'bi-building', 'สถานที่'],
            ] as [$k, $icon, $label]): if (!$stats[$k]) continue; ?>
                <div class="col">
                    <div class="promo-stat-num"><i class="bi <?= $icon ?>"></i> <?= number_format($stats[$k]) ?></div>
                    <div class="small opacity-75"><?= $label ?></div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <div class="row g-0">
        <div class="col-lg-7 p-4 p-md-5 align-self-center">
            <span class="badge promo-badge mb-3"><i class="bi bi-gift"></i> ใช้งานฟรี</span>
            <h2 class="h3 fw-bold mb-2">อยากเปิดขายบัตรงานของคุณเองไหม?</h2>
            <p class="mb-4 opacity-75">
                คอนเสิร์ต ละคร สัมมนา หรืองานในหอประชุม ห้องประชุม และโรงละคร — สร้างงาน ตั้งราคาตามโซน
                แล้วให้ผู้ชมเลือกที่นั่งและจองออนไลน์ได้เลย สมัครเป็นผู้จัดงานได้ฟรี
            </p>
            <a href="<?= Url::to(['/organizer/apply']) ?>" class="btn btn-warning btn-lg fw-semibold me-2 mb-2">
                <i class="bi bi-calendar-plus"></i> สมัครเป็นผู้จัดงาน
            </a>
            <a href="https://manitgithub.github.io/" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg mb-2">
                <i class="bi bi-chat-dots"></i> ติดต่อผู้พัฒนา
            </a>
            <div class="small mt-2 opacity-75">พัฒนาโดย มานิต จิตต์ประไพย</div>
        </div>
        <div class="col-lg-5 p-4 p-md-5 promo-features d-flex align-items-center">
            <ul class="list-unstyled mb-0">
                <li><i class="bi bi-gift"></i> <span><b>ฟรี</b> ไม่มีค่าธรรมเนียมจากระบบ</span></li>
                <li><i class="bi bi-wallet2"></i> <span><b>เงินเข้าบัญชีคุณโดยตรง</b> ผ่าน QR PromptPay ไม่ผ่านตัวกลาง</span></li>
                <li><i class="bi bi-lightning-charge"></i> <span><b>ตั้งงานได้ในไม่กี่นาที</b> เลือกสถานที่ ตั้งราคาโซน เปิดขาย</span></li>
                <li><i class="bi bi-grid-3x3-gap"></i> <span><b>ผู้ชมเลือกที่นั่งเอง</b> บนผังจริง ใช้บนมือถือได้ เข้าระบบด้วย LINE</span></li>
                <li><i class="bi bi-qr-code-scan"></i> <span><b>ทีมงานสแกนตั๋วด้วยมือถือ</b> เชิญเข้าทีมด้วยลิงก์เดียว</span></li>
            </ul>
        </div>
    </div>
</section>

<?php
$phones = [
    ['seatmap', 'เลือกที่นั่งบนผังจริง', 'แตะเลือกที่นั่ง เห็นราคาและยอดรวมทันที'],
    ['payment', 'จ่ายผ่าน QR PromptPay', 'สแกนจ่ายตามยอด แล้วแนบสลิป'],
    ['ticket', 'ได้ E-ticket ทันที', 'QR เดียวเข้าได้ทั้งกลุ่ม'],
    ['checkin', 'สแกนเช็คอินหน้างาน', 'ทีมงานใช้มือถือของตัวเอง'],
];
$desktops = [
    ['event-form', 'ตั้งงานในหน้าเดียว', 'สถานที่ ราคาโซน วันขาย และบัญชีรับเงินของงาน'],
    ['venue', 'สร้างผังที่นั่งเอง', 'กำหนดแถว บล็อก และโซนราคา เห็นผังทันที'],
];
$img = fn($k) => Url::to("@web/img/screens/$k.jpg");
?>
<section class="showcase mt-5 pt-2" aria-labelledby="showcase-title">
    <div class="text-center mb-4">
        <h2 class="h3 fw-bold mb-1" id="showcase-title">ตัวอย่างหน้าจอ</h2>
        <div class="text-muted">หน้าจอจริงจากระบบ · ข้อมูลในภาพเป็นข้อมูลสมมติ</div>
    </div>

    <h3 class="h6 text-uppercase text-muted fw-semibold mb-3"><i class="bi bi-phone"></i> ฝั่งผู้ชม — ใช้บนมือถือ</h3>
    <div class="showcase-phones mb-5">
        <?php foreach ($phones as [$k, $title, $desc]): ?>
            <figure class="showcase-item">
                <button type="button" class="phone-frame" data-shot="<?= $img($k) ?>" data-title="<?= Html::encode($title) ?>" aria-label="ดูภาพใหญ่: <?= Html::encode($title) ?>">
                    <img src="<?= $img($k) ?>" alt="<?= Html::encode($title) ?>" loading="lazy" width="375" height="812">
                </button>
                <figcaption><b><?= Html::encode($title) ?></b><span><?= Html::encode($desc) ?></span></figcaption>
            </figure>
        <?php endforeach ?>
    </div>

    <h3 class="h6 text-uppercase text-muted fw-semibold mb-3"><i class="bi bi-laptop"></i> ฝั่งผู้จัดงาน — หลังบ้าน</h3>
    <div class="row g-4">
        <?php foreach ($desktops as [$k, $title, $desc]): ?>
            <div class="col-md-6">
                <figure class="showcase-item mb-0">
                    <button type="button" class="browser-frame" data-shot="<?= $img($k) ?>" data-title="<?= Html::encode($title) ?>" aria-label="ดูภาพใหญ่: <?= Html::encode($title) ?>">
                        <span class="browser-bar" aria-hidden="true"><i></i><i></i><i></i></span>
                        <img src="<?= $img($k) ?>" alt="<?= Html::encode($title) ?>" loading="lazy" width="800" height="499">
                    </button>
                    <figcaption><b><?= Html::encode($title) ?></b><span><?= Html::encode($desc) ?></span></figcaption>
                </figure>
            </div>
        <?php endforeach ?>
    </div>
</section>

<div class="modal fade" id="shotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="d-flex justify-content-between align-items-center text-white mb-2">
                <div class="fw-semibold" id="shotTitle"></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <img id="shotImg" src="" alt="" class="shot-full mx-auto rounded-3">
        </div>
    </div>
</div>
<?php $this->registerJs(<<<'JS'
(function () {
  const modalEl = document.getElementById('shotModal');
  if (!modalEl || !window.bootstrap) return;
  const modal = new bootstrap.Modal(modalEl);
  document.querySelectorAll('[data-shot]').forEach((b) => b.addEventListener('click', () => {
    const img = document.getElementById('shotImg');
    img.src = b.dataset.shot; img.alt = b.dataset.title;
    document.getElementById('shotTitle').textContent = b.dataset.title;
    modal.show();
  }));
})();
JS); ?>
