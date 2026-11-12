<?php
/** @var yii\web\View $this */
/** @var app\models\Event $event */
/** @var app\models\Booking|null $pending */

use app\models\Booking;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = $event->title;
$f = Yii::$app->formatter;
$this->registerJsFile('@web/js/seatmap.js?v=1');
$canBook = $event->isOnSale();
?>
<div class="row g-4 mb-3">
    <div class="col-md-8">
        <h1 class="h3 mb-1"><?= Html::encode($event->title) ?></h1>
        <div class="text-muted">
            <i class="bi bi-calendar-event"></i> <?= $f->asDatetime($event->show_start) ?>
            <?php if ($event->show_end): ?> – <?= $f->asTime($event->show_end, 'php:H:i') ?><?php endif ?>
            &nbsp;·&nbsp; <i class="bi bi-geo-alt"></i> <?= Html::encode($event->venue->name) ?><?php if ($event->venue->location): ?> <span class="small">(<?= Html::encode($event->venue->location) ?>)</span><?php endif ?>
            <?php if ($event->organizer): ?>&nbsp;·&nbsp; <?= Html::encode($event->organizer) ?><?php endif ?>
        </div>
        <?php if ($event->owner_id): ?>
            <div class="small text-muted mt-1"><i class="bi bi-info-circle"></i>
                งานนี้จัดโดย <?= Html::encode($event->organizer ?: 'ผู้จัดงาน') ?> ซึ่งเป็นผู้รับผิดชอบการจัดงาน การรับเงิน และการคืนเงินทั้งหมด
                ระบบจองเป็นเพียงเครื่องมือ ไม่ได้เป็นผู้จัดงาน
            </div>
        <?php endif ?>
        <?php if ($event->description): ?>
            <p class="mt-2 mb-0"><?= nl2br(Html::encode($event->description)) ?></p>
        <?php endif ?>
    </div>
    <div class="col-md-4">
        <ul class="list-group small">
            <?php foreach ($event->zonePrices as $zp): if (!$zp->is_open) continue; ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?= Html::encode($zp->zoneLabel) ?></span><strong><?= $f->asDecimal($zp->price, 0) ?> บาท</strong>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
</div>

<?php if ($pending): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>คุณมีรายการจอง <strong><?= $pending->code ?></strong> (<?= Booking::statusLabels()[$pending->status] ?>) ในรอบนี้
        <?php if ($pending->status === Booking::STATUS_PENDING): ?> — ถ้าเลือกที่นั่งใหม่ รายการเดิมจะถูกยกเลิก<?php endif ?></span>
        <a class="btn btn-sm btn-warning" href="<?= Url::to(['/booking/view', 'code' => $pending->code]) ?>">ไปที่รายการ</a>
    </div>
<?php endif ?>

<?php if (!$canBook): ?>
    <div class="alert alert-secondary"><?= Html::encode($event->saleStatusText()) ?> — ดูผังที่นั่งได้แต่ยังจองไม่ได้</div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="seatmap-wrap">
            <div class="seatmap-tools no-print">
                <button class="btn btn-light btn-sm border" id="zin" title="ขยาย"><i class="bi bi-zoom-in"></i></button>
                <button class="btn btn-light btn-sm border" id="zout" title="ย่อ"><i class="bi bi-zoom-out"></i></button>
            </div>
            <div class="seatmap-box" id="seatmap"><div class="text-center text-muted py-5">กำลังโหลดผังที่นั่ง…</div></div>
        </div>
        <div class="legend mt-2" id="legend"></div>
    </div>
    <div class="col-lg-4">
        <div class="card cart cart-mobile border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h2 class="h6 mb-2">ที่นั่งที่เลือก <span class="text-muted" id="cnt">(0)</span></h2>
                    <a href="#" id="clear" class="small">ล้าง</a>
                </div>
                <div id="chips" class="mb-2 small text-muted">แตะที่นั่งบนผังเพื่อเลือก (สูงสุด <?= $event->max_seats_per_booking ?> ที่)</div>
                <div id="msg" class="small text-danger mb-2"></div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>ยอดรวม</span><strong class="fs-5" id="total">0 บาท</strong>
                </div>
                <?= Html::beginForm(['/booking/hold'], 'post', ['id' => 'holdForm']) ?>
                <?= Html::hiddenInput('event_id', $event->id) ?>
                <div id="seatInputs"></div>
                <?php if (Yii::$app->user->isGuest): ?>
                    <a href="<?= Url::to(['/site/login']) ?>" class="btn btn-line w-100" id="loginBtn">เข้าสู่ระบบด้วย LINE เพื่อจอง</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary w-100" id="bookBtn" disabled <?= $canBook ? '' : 'data-closed="1"' ?>>
                        จองและไปชำระเงิน
                    </button>
                    <div class="small text-muted mt-2">ระบบจะล็อกที่นั่งให้ <?= $event->hold_minutes ?> นาทีเพื่อชำระเงิน</div>
                <?php endif ?>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>
<?php
Yii::$app->user->setReturnUrl(Url::current());
$cfg = Json::htmlEncode([
    'url' => Url::to(['/event/seats', 'id' => $event->id]),
    'max' => (int)$event->max_seats_per_booking,
]);
$this->registerJs(<<<JS
(function () {
  const cfg = $cfg;
  const chips = document.getElementById('chips'), total = document.getElementById('total'),
        cnt = document.getElementById('cnt'), msg = document.getElementById('msg'),
        inputs = document.getElementById('seatInputs'), btn = document.getElementById('bookBtn');
  const fmt = (n) => Number(n).toLocaleString('th-TH');
  const map = new SeatMap(document.getElementById('seatmap'), {
    url: cfg.url, max: cfg.max,
    onChange(sel, warn) {
      msg.textContent = warn || '';
      cnt.textContent = '(' + sel.length + ')';
      chips.innerHTML = sel.length ? sel.map(s => '<span class="seat-chip">' + s.l + '</span>').join('') : 'แตะที่นั่งบนผังเพื่อเลือก';
      total.textContent = fmt(sel.reduce((a, s) => a + (s.p || 0), 0)) + ' บาท';
      inputs.innerHTML = sel.map(s => '<input type="hidden" name="seats[]" value="' + s.id + '">').join('');
      if (btn) btn.disabled = !sel.length || btn.dataset.closed;
      document.body.classList.toggle('has-cart', sel.length > 0);
    }
  });
  map.load().then(() => map.legend(document.getElementById('legend')));
  document.getElementById('zin').onclick = () => map.zoomIn();
  document.getElementById('zout').onclick = () => map.zoomOut();
  document.getElementById('clear').onclick = (e) => { e.preventDefault(); map.clear(); };
  document.getElementById('holdForm').addEventListener('submit', () => { if (btn) { btn.disabled = true; btn.textContent = 'กำลังล็อกที่นั่ง…'; } });
  setInterval(() => { if (!document.hidden) map.refresh(); }, 15000);
})();
JS);
