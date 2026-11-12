<?php
/** @var app\models\Booking $b */

use app\models\Hall;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'ตั๋ว ' . $b->code;
$f = Yii::$app->formatter;
?>
<div class="ticket">
    <div class="ticket-head">
        <div class="small opacity-75">ห้องประชุม 1500</div>
        <div class="fs-5 fw-bold"><?= Html::encode($b->event->title) ?></div>
        <div><i class="bi bi-calendar-event"></i> <?= $f->asDatetime($b->event->show_start) ?></div>
    </div>
    <div class="p-3">
        <div class="row small">
            <div class="col-6"><div class="text-muted">ผู้จอง</div><div class="fw-bold"><?= Html::encode($b->user->display_name) ?></div></div>
            <div class="col-6 text-end"><div class="text-muted">รหัสจอง</div><div class="fw-bold"><?= $b->code ?></div></div>
        </div>
        <div class="mt-3">
            <div class="small text-muted">ที่นั่ง (<?= $b->seat_count ?>)</div>
            <?php foreach ($b->seats as $s): ?>
                <span class="seat-chip"><?= Html::encode(Hall::floorLabel($s->floor)) ?> <?= Html::encode($s->label) ?></span>
            <?php endforeach ?>
        </div>
    </div>
    <div class="ticket-cut"></div>
    <div class="p-3 text-center">
        <?php if ($b->checked_in_at): ?>
            <div class="alert alert-success py-2 small">เช็คอินแล้ว <?= $f->asDatetime($b->checked_in_at) ?></div>
        <?php endif ?>
        <img src="<?= Url::to(['/booking/qr', 'code' => $b->code]) ?>" alt="QR ตั๋ว" class="img-fluid" style="max-width:260px">
        <div class="small text-muted">แสดง QR นี้ที่จุดตรวจบัตรหน้าห้องประชุม<br>1 QR ใช้เข้าได้ทั้งกลุ่ม (<?= $b->seat_count ?> ที่นั่ง)</div>
    </div>
</div>
<div class="text-center mt-3 no-print">
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
    <a class="btn btn-link btn-sm" href="<?= Url::to(['/booking/my']) ?>">ตั๋วทั้งหมด</a>
</div>
