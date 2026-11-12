<?php
/** @var yii\data\ActiveDataProvider $provider */
/** @var string|null $q */

use app\models\User;
use yii\grid\GridView;
use yii\helpers\Html;

$this->title = 'ผู้ใช้';
?>
<h1 class="h4 mb-3"><?= $this->title ?></h1>
<form class="mb-3" method="get"><input name="q" value="<?= Html::encode($q) ?>" class="form-control" placeholder="ค้นหาชื่อ / อีเมล"></form>
<div class="bg-white rounded shadow-sm p-2 table-responsive">
<?= GridView::widget([
    'dataProvider' => $provider,
    'columns' => [
        'id',
        ['label' => '', 'format' => 'raw', 'value' => fn(User $u) => $u->picture_url ? Html::img($u->picture_url, ['width' => 32, 'class' => 'rounded-circle']) : ''],
        'display_name', 'email', 'phone',
        ['attribute' => 'last_login_at', 'label' => 'เข้าใช้ล่าสุด', 'format' => 'datetime'],
        ['label' => 'สิทธิ์', 'format' => 'raw', 'value' => fn(User $u) =>
            Html::beginForm(['user-role', 'id' => $u->id], 'post', ['class' => 'd-flex gap-1'])
            . Html::dropDownList('role', $u->role, User::roleLabels(), ['class' => 'form-select form-select-sm'])
            . Html::submitButton('บันทึก', ['class' => 'btn btn-sm btn-outline-primary'])
            . Html::endForm()],
    ],
]) ?>
</div>
