<?php
/** @var app\models\Event[] $events */

use app\models\Event;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'การแสดงที่ห้องประชุม 1500';
$f = Yii::$app->formatter;
?>
<div class="d-flex align-items-end justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">การแสดงที่กำลังจะมาถึง</h1>
        <div class="text-muted">คอนเสิร์ต ละครสั้น และกิจกรรม ณ ห้องประชุม 1500 ที่นั่ง</div>
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
                    <div class="small"><i class="bi bi-cash"></i> <?= $e->priceRange() ?></div>
                </div>
                <div class="card-footer bg-white border-0 pb-3">
                    <span class="badge <?= $e->isOnSale() ? 'bg-success' : 'bg-secondary' ?>"><?= Html::encode($e->saleStatusText()) ?></span>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>
