<?php
/** @var yii\data\ActiveDataProvider $provider */
/** @var string|null $status */
/** @var array $eventCounts */

use app\models\OrganizerApplication;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'ผู้จัดงาน';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><?= $this->title ?></h1>
    <a href="<?= Url::to(['/site/terms']) ?>" target="_blank" class="small">ดูเงื่อนไขที่ผู้สมัครต้องยอมรับ</a>
</div>
<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'ทั้งหมด'] + OrganizerApplication::statusLabels() as $k => $label): ?>
        <li class="nav-item"><a class="nav-link <?= (string)$status === (string)$k ? 'active' : '' ?>" href="<?= Url::to(['organizers', 'status' => $k]) ?>"><?= $label ?></a></li>
    <?php endforeach ?>
</ul>
<div class="bg-white rounded shadow-sm p-2 table-responsive">
<?= GridView::widget([
    'dataProvider' => $provider,
    'emptyText' => 'ไม่มีใบสมัคร',
    'columns' => [
        ['label' => 'ผู้สมัคร', 'format' => 'raw', 'value' => fn(OrganizerApplication $a) =>
            '<div class="fw-bold">' . Html::encode($a->full_name) . '</div>'
            . '<div class="small text-muted">' . Html::encode($a->organization ?: '-') . ' · LINE: ' . Html::encode($a->user->display_name) . '</div>'],
        ['label' => 'ติดต่อ', 'format' => 'raw', 'value' => fn(OrganizerApplication $a) =>
            Html::encode($a->phone) . ($a->email ? '<div class="small">' . Html::encode($a->email) . '</div>' : '')],
        ['attribute' => 'created_at', 'format' => 'datetime'],
        ['label' => 'สถานะ', 'format' => 'raw', 'value' => fn(OrganizerApplication $a) =>
            '<span class="badge bg-' . OrganizerApplication::statusBadge($a->status) . '">' . OrganizerApplication::statusLabels()[$a->status] . '</span>'],
        ['label' => 'งาน', 'value' => fn(OrganizerApplication $a) => (int)($eventCounts[$a->user_id] ?? 0)],
        ['format' => 'raw', 'value' => fn(OrganizerApplication $a) =>
            Html::a($a->status === OrganizerApplication::STATUS_PENDING ? 'ตรวจสอบ' : 'ดู', ['organizer', 'id' => $a->id], ['class' => 'btn btn-sm btn-outline-primary'])],
    ],
]) ?>
</div>
