<?php
/** @var app\models\Event[] $events */
/** @var array $revenue */
/** @var int $pendingSlips */
/** @var int $pendingOrganizers */

use app\models\Event;
use yii\helpers\Html;
use yii\helpers\Url;

$isAdmin = Yii::$app->user->identity->isAdmin();
$this->title = $isAdmin ? 'หลังบ้าน' : 'งานของฉัน';
$f = Yii::$app->formatter;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= $isAdmin ? 'การแสดงทั้งหมด' : 'งานของฉัน' ?></h1>
    <div>
        <?php if ($pendingOrganizers): ?>
            <a href="<?= Url::to(['organizers']) ?>" class="btn btn-outline-info">
                ใบสมัครผู้จัดงาน <span class="badge bg-danger"><?= $pendingOrganizers ?></span>
            </a>
        <?php endif ?>
        <a href="<?= Url::to(['payments']) ?>" class="btn btn-outline-warning">
            ตรวจสลิป <?php if ($pendingSlips): ?><span class="badge bg-danger"><?= $pendingSlips ?></span><?php endif ?>
        </a>
        <a href="<?= Url::to(['event-create']) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่มการแสดง</a>
    </div>
</div>
<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table align-middle mb-0">
        <thead><tr><th>การแสดง</th><th>วันแสดง</th><th>สถานะ</th><th class="text-end">ขายแล้ว</th><th class="text-end">ว่าง</th><th class="text-end">รายได้</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): $s = $e->seatSummary(); $rv = $revenue[$e->id] ?? null; ?>
            <tr>
                <td>
                    <a href="<?= Url::to(['/event/view', 'id' => $e->id]) ?>" class="fw-bold"><?= Html::encode($e->title) ?></a>
                    <div class="small text-muted">
                        <?= Html::encode($e->venue->name) ?>
                        <?php if ($isAdmin && $e->owner): ?> · ผู้จัด: <?= Html::encode($e->owner->display_name) ?><?php endif ?>
                    </div>
                </td>
                <td class="small"><?= $f->asDatetime($e->show_start) ?></td>
                <td><span class="badge bg-<?= $e->status === Event::STATUS_PUBLISHED ? 'success' : 'secondary' ?>"><?= Event::statusLabels()[$e->status] ?></span></td>
                <td class="text-end"><?= $s['sold'] ?><?php if ($s['held']): ?> <span class="text-muted small">(+<?= $s['held'] ?> ล็อก)</span><?php endif ?></td>
                <td class="text-end"><?= $s['available'] ?></td>
                <td class="text-end"><?= $f->asDecimal($rv['total'] ?? 0, 0) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= Url::to(['event-update', 'id' => $e->id]) ?>">แก้ไข</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= Url::to(['event-seats', 'id' => $e->id]) ?>">ผังที่นั่ง</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= Url::to(['bookings', 'event_id' => $e->id]) ?>">รายการจอง</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= Url::to(['export', 'event_id' => $e->id]) ?>" title="CSV"><i class="bi bi-download"></i></a>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if (!$events): ?><tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีการแสดง</td></tr><?php endif ?>
        </tbody>
    </table>
</div>
