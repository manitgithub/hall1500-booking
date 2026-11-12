<?php
/** @var yii\data\ActiveDataProvider $provider */
/** @var array $events */

use app\models\Booking;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'รายการจอง';
?>
<h1 class="h4 mb-3"><?= $this->title ?></h1>
<form class="row g-2 mb-3" method="get" action="<?= Url::to(['bookings']) ?>">
    <div class="col-md-4"><?= Html::dropDownList('event_id', $event_id, $events, ['class' => 'form-select', 'prompt' => 'ทุกการแสดง']) ?></div>
    <div class="col-md-3"><?= Html::dropDownList('status', $status, Booking::statusLabels(), ['class' => 'form-select', 'prompt' => 'ทุกสถานะ']) ?></div>
    <div class="col-md-3"><?= Html::textInput('q', $q, ['class' => 'form-control', 'placeholder' => 'รหัสจอง / ชื่อ']) ?></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">ค้นหา</button></div>
</form>
<div class="bg-white rounded shadow-sm p-2 table-responsive">
<?= GridView::widget([
    'dataProvider' => $provider,
    'columns' => [
        'code',
        ['label' => 'ผู้จอง', 'value' => fn(Booking $b) => $b->user->display_name],
        ['label' => 'การแสดง', 'value' => fn(Booking $b) => $b->event->title],
        ['label' => 'ที่นั่ง', 'value' => fn(Booking $b) => $b->seatLabels(), 'contentOptions' => ['class' => 'small']],
        ['attribute' => 'total_amount', 'label' => 'ยอด', 'format' => ['decimal', 2]],
        ['attribute' => 'status', 'label' => 'สถานะ', 'format' => 'raw', 'value' => fn(Booking $b) => Booking::statusBadge($b->status)
            . ($b->checked_in_at ? ' <span class="badge bg-primary">เช็คอินแล้ว</span>' : '')
            . ($b->note ? '<div class="small text-muted">' . Html::encode($b->note) . '</div>' : '')],
        ['attribute' => 'created_at', 'label' => 'จองเมื่อ', 'format' => 'datetime'],
        ['format' => 'raw', 'value' => function (Booking $b) {
            if (!in_array($b->status, [Booking::STATUS_PENDING, Booking::STATUS_VERIFYING, Booking::STATUS_PAID], true)) {
                return '';
            }
            $refund = $b->status === Booking::STATUS_PAID;
            return Html::beginForm(['booking-cancel', 'id' => $b->id], 'post', [
                    'onsubmit' => "return confirm('ยกเลิก {$b->code} และปล่อยที่นั่ง?')"])
                . ($refund ? Html::hiddenInput('refund', 1) : '')
                . Html::submitButton($refund ? 'ยกเลิก+คืนเงิน' : 'ยกเลิก', ['class' => 'btn btn-sm btn-outline-danger'])
                . Html::endForm();
        }],
    ],
]) ?>
</div>
