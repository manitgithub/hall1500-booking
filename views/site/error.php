<?php
/** @var string $name */
/** @var string $message */

use yii\helpers\Html;

$this->title = $name;
?>
<div class="text-center py-5">
    <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
    <h1 class="h4 mt-3"><?= Html::encode($this->title) ?></h1>
    <p class="text-muted"><?= nl2br(Html::encode($message)) ?></p>
    <a href="<?= Yii::$app->homeUrl ?>" class="btn btn-primary">กลับหน้าแรก</a>
</div>
