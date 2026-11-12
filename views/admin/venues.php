<?php
/** @var app\models\Venue[] $venues */
/** @var array $eventCounts */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'สถานที่ / ผังที่นั่ง';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= $this->title ?></h1>
    <a href="<?= Url::to(['venue-create']) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่มสถานที่</a>
</div>
<p class="text-muted small">
    แต่ละสถานที่มีผังที่นั่งของตัวเอง เวลาเพิ่มการแสดงให้เลือกว่าจัดที่ไหน ผู้ชมจะเห็นและจองตามผังของสถานที่นั้น
</p>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table align-middle mb-0">
        <thead><tr><th>สถานที่</th><th>ผังที่นั่ง</th><th class="text-end">ที่นั่ง</th><th class="text-end">การแสดง</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($venues as $v): ?>
            <tr class="<?= $v->is_active ? '' : 'text-muted' ?>">
                <td>
                    <div class="fw-bold"><?= Html::encode($v->name) ?><?php if (!$v->is_active): ?> <span class="badge bg-secondary">ปิดใช้งาน</span><?php endif ?></div>
                    <div class="small text-muted"><?= Html::encode($v->location) ?></div>
                </td>
                <td class="small">
                    <?= $v->isGrid()
                        ? Html::encode("{$v->grid_rows} แถว · บล็อก {$v->grid_sections}")
                        : 'ผังละเอียด <code>' . Html::encode($v->layout) . '</code>' ?>
                </td>
                <td class="text-end"><?= number_format($v->seatCount()) ?></td>
                <td class="text-end"><?= (int)($eventCounts[$v->id] ?? 0) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= Url::to(['venue-update', 'id' => $v->id]) ?>">แก้ไข / ดูผัง</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['event-create', 'venue_id' => $v->id]) ?>">+ การแสดง</a>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if (!$venues): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีสถานที่</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>
