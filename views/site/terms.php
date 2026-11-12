<?php
/** @var string $terms */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'เงื่อนไขการใช้งานสำหรับผู้จัดงาน';
$lines = preg_split('/\n/', $terms);
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 terms-text">
            <?php foreach ($lines as $i => $line): $line = trim($line); ?>
                <?php if ($line === ''): continue; endif ?>
                <?php if ($i === 0): ?>
                    <h1 class="h4 mb-3"><?= Html::encode($line) ?></h1>
                <?php elseif (preg_match('/^\d+\.\s/', $line)): ?>
                    <h2 class="h6 mt-3 mb-1"><?= Html::encode($line) ?></h2>
                <?php else: ?>
                    <p class="mb-2"><?= Html::encode($line) ?></p>
                <?php endif ?>
            <?php endforeach ?>
        </div></div>
        <div class="text-center mt-3">
            <a href="<?= Url::to(['/organizer/apply']) ?>" class="btn btn-primary">สมัครเป็นผู้จัดงาน</a>
        </div>
    </div>
</div>
