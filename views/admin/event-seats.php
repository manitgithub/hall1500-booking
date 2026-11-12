<?php
/** @var yii\web\View $this */
/** @var app\models\Event $event */
/** @var array $summary */
/** @var array $holders */

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'ผังที่นั่ง: ' . $event->title;
$this->registerJsFile('@web/js/seatmap.js?v=1');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0"><?= Html::encode($this->title) ?></h1>
    <div class="small">
        ว่าง <b><?= $summary['available'] ?></b> · ล็อก <b><?= $summary['held'] ?></b> · ขายแล้ว <b><?= $summary['sold'] ?></b> ·
        กันไว้ <b><?= $summary['blocked'] ?></b> · รวม <b><?= $summary['total'] ?></b>
    </div>
</div>
<div class="alert alert-light border small">
    เลือกที่นั่งว่าง/ที่กันไว้ แล้วกด "กันที่นั่ง" (เช่น ห้องควบคุมเสียง กล้อง แขกพิเศษ) หรือ "ปลดกัน" ·
    วางเมาส์บนที่นั่งที่ขายแล้วเพื่อดูผู้จอง
    <?= Html::beginForm(['sync-seats', 'id' => $event->id], 'post', ['class' => 'd-inline']) ?>
    · <button class="btn btn-link btn-sm p-0 align-baseline">อัปเดตรายการที่นั่งจากผัง/ราคาโซน</button>
    <?= Html::endForm() ?>
</div>
<div class="row g-3">
    <div class="col-lg-9">
        <div class="seatmap-wrap">
            <div class="seatmap-tools">
                <button class="btn btn-light btn-sm border" id="zin"><i class="bi bi-zoom-in"></i></button>
                <button class="btn btn-light btn-sm border" id="zout"><i class="bi bi-zoom-out"></i></button>
            </div>
            <div class="seatmap-box" id="seatmap"></div>
        </div>
        <div class="legend mt-2" id="legend"></div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm cart"><div class="card-body">
            <div class="mb-2">เลือกแล้ว <b id="cnt">0</b> ที่</div>
            <div id="chips" class="small mb-3"></div>
            <div class="d-grid gap-2">
                <button class="btn btn-dark" id="blockBtn" disabled>กันที่นั่ง</button>
                <button class="btn btn-outline-dark" id="unblockBtn" disabled>ปลดกัน</button>
                <button class="btn btn-link btn-sm" id="clearBtn">ล้างการเลือก</button>
            </div>
        </div></div>
    </div>
</div>
<?php
$cfg = Json::htmlEncode([
    'url' => Url::to(['/event/seats', 'id' => $event->id]),
    'blockUrl' => Url::to(['block', 'id' => $event->id]),
    'holders' => ArrayHelper::index($holders, 'seat_id'),
    'csrf' => Yii::$app->request->csrfToken,
]);
$this->registerJs(<<<JS
(function () {
  const cfg = $cfg;
  const blockBtn = document.getElementById('blockBtn'), unblockBtn = document.getElementById('unblockBtn');
  const map = new SeatMap(document.getElementById('seatmap'), {
    url: cfg.url, mode: 'admin', holders: cfg.holders,
    onChange(sel) {
      document.getElementById('cnt').textContent = sel.length;
      document.getElementById('chips').innerHTML = sel.map(s => '<span class="seat-chip">' + s.l + '</span>').join('');
      blockBtn.disabled = !sel.some(s => s.s === 'available');
      unblockBtn.disabled = !sel.some(s => s.s === 'blocked');
    }
  });
  map.load().then(() => map.legend(document.getElementById('legend')));
  async function send(block) {
    const body = new URLSearchParams();
    body.append('_csrf', cfg.csrf); body.append('block', block ? 1 : 0);
    map.getSelected().forEach(s => body.append('seats[]', s.id));
    await fetch(cfg.blockUrl, { method: 'POST', body, credentials: 'same-origin' });
    map.clear(); await map.refresh();
  }
  blockBtn.onclick = () => send(true);
  unblockBtn.onclick = () => send(false);
  document.getElementById('clearBtn').onclick = () => map.clear();
  document.getElementById('zin').onclick = () => map.zoomIn();
  document.getElementById('zout').onclick = () => map.zoomOut();
})();
JS);
