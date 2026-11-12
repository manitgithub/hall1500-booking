<?php
/** @var yii\web\View $this */
/** @var string $content */

use yii\helpers\Html;
use yii\helpers\Url;

$user = Yii::$app->user->identity;
$this->registerCsrfMetaTags();
$this->registerJsFile('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', ['position' => $this::POS_END]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::encode($this->title ? $this->title . ' | ' . Yii::$app->name : Yii::$app->name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= Url::to('@web/css/site.css') ?>?v=1" rel="stylesheet">
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php $this->beginBody() ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-brand">
    <div class="container">
        <a class="navbar-brand" href="<?= Url::home() ?>"><i class="bi bi-ticket-perforated"></i> <?= Html::encode(Yii::$app->name) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= Url::home() ?>">การแสดง</a></li>
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/booking/my']) ?>">ตั๋วของฉัน</a></li>
                <?php endif ?>
                <?php if ($user && $user->canUseCheckin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/checkin/index']) ?>"><i class="bi bi-qr-code-scan"></i> เช็คอิน</a></li>
                <?php endif ?>
                <?php if ($user && $user->isOrganizer()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">จัดงาน</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/index']) ?>">งานของฉัน</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/event-create']) ?>">สร้างงานใหม่</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/payments']) ?>">ตรวจสลิป</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/bookings']) ?>">รายการจอง</a></li>
                        </ul>
                    </li>
                <?php elseif ($user && !$user->isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Url::to(['/organizer/apply']) ?>">อยากจัดงาน?</a></li>
                <?php endif ?>
                <?php if ($user && $user->isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">หลังบ้าน</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/index']) ?>">ภาพรวม / การแสดง</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/venues']) ?>">สถานที่ / ผังที่นั่ง</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/payments']) ?>">ตรวจสลิป</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/bookings']) ?>">รายการจอง</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/organizers']) ?>">ผู้จัดงาน</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/users']) ?>">ผู้ใช้</a></li>
                            <li><a class="dropdown-item" href="<?= Url::to(['/admin/settings']) ?>">ตั้งค่าระบบ</a></li>
                        </ul>
                    </li>
                <?php endif ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item d-flex align-items-center text-white me-2">
                        <?php if ($user->picture_url): ?>
                            <img src="<?= Html::encode($user->picture_url) ?>" class="rounded-circle me-2" width="28" height="28" alt="">
                        <?php endif ?>
                        <?= Html::encode($user->display_name) ?>
                    </li>
                    <li class="nav-item">
                        <?= Html::beginForm(['/site/logout']) . Html::submitButton('ออกจากระบบ', ['class' => 'btn btn-sm btn-outline-light']) . Html::endForm() ?>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="btn btn-sm btn-line" href="<?= Url::to(['/site/login']) ?>">เข้าสู่ระบบด้วย LINE</a></li>
                <?php endif ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1 py-4">
    <div class="container">
        <?php foreach (Yii::$app->session->getAllFlashes() as $type => $messages): ?>
            <?php foreach ((array)$messages as $m): ?>
                <div class="alert alert-<?= Html::encode($type) ?> alert-dismissible fade show">
                    <?= Html::encode($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach ?>
        <?php endforeach ?>
        <?= $content ?>
    </div>
</main>

<footer class="py-3 text-center text-muted small border-top bg-white">
    <?= Html::encode(Yii::$app->name) ?> · &copy; <?= date('Y') + 543 ?>
    · <a href="<?= Url::to(['/site/terms']) ?>" class="text-decoration-none">เงื่อนไขผู้จัดงาน</a>
    · พัฒนาโดย <a href="https://manitgithub.github.io/" target="_blank" rel="noopener" class="text-decoration-none">มานิต จิตต์ประไพย</a>
</footer>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
