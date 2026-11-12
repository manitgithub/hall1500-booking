<?php
/** @var app\models\Event $event */
/** @var string $t */
/** @var bool $already */
/** @var bool $manages */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'เข้าร่วมทีมงาน';
$f = Yii::$app->formatter;
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 text-center">
            <i class="bi bi-people fs-1 text-primary"></i>
            <h1 class="h5 mt-2">ทีมงาน: <?= Html::encode($event->title) ?></h1>
            <div class="text-muted small mb-3">
                <?= $f->asDatetime($event->show_start) ?> · <?= Html::encode($event->venue->name) ?>
                <?php if ($event->organizer): ?><br>ผู้จัด: <?= Html::encode($event->organizer) ?><?php endif ?>
            </div>
            <?php if ($already): ?>
                <p><?= $manages ? 'คุณเป็นผู้จัดงานนี้อยู่แล้ว' : 'คุณอยู่ในทีมงานนี้แล้ว' ?></p>
                <a href="<?= Url::to(['/checkin/index']) ?>" class="btn btn-primary">ไปหน้าเช็คอิน</a>
            <?php else: ?>
                <p class="small">ทีมงานสแกน QR ตั๋วเพื่อเช็คอินผู้เข้างานนี้ได้ (เห็นชื่อผู้จองและที่นั่ง)</p>
                <?= Html::beginForm(['accept', 'id' => $event->id, 't' => $t]) ?>
                <button class="btn btn-primary btn-lg w-100">เข้าร่วมทีม</button>
                <?= Html::endForm() ?>
            <?php endif ?>
        </div></div>
    </div>
</div>
