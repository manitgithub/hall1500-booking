<?php
/** @var yii\web\View $this */
/** @var app\models\Event $event */
/** @var array $members */

use app\components\QrImage;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'ทีมงาน: ' . $event->title;
$f = Yii::$app->formatter;
$link = $event->teamInviteUrl();
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['event-update', 'id' => $event->id]) ?>" class="btn btn-link">← กลับไปหน้าการแสดง</a>
</div>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h6"><i class="bi bi-person-plus"></i> เชิญเข้าทีม</h2>
            <p class="small text-muted">
                ส่งลิงก์หรือ QR นี้ให้ทีมงาน เปิดแล้วล็อกอินด้วย LINE กด "เข้าร่วมทีม" ก็สแกนเช็คอินตั๋วของงานนี้ได้ทันที
                ทีมงานเห็นเฉพาะหน้าเช็คอิน ไม่เห็นยอดเงิน สลิป หรือหลังบ้าน
            </p>
            <div class="input-group mb-2">
                <input class="form-control form-control-sm" id="invite" value="<?= Html::encode($link) ?>" readonly>
                <button class="btn btn-sm btn-outline-primary" type="button" id="copyBtn"><i class="bi bi-clipboard"></i> คัดลอก</button>
            </div>
            <div class="text-center my-3">
                <img src="data:image/png;base64,<?= base64_encode(QrImage::png($link, 220)) ?>" alt="QR ลิงก์เชิญ" class="border rounded">
            </div>
            <?= Html::beginForm(['event-team-reset', 'id' => $event->id], 'post', ['onsubmit' => "return confirm('เปลี่ยนลิงก์เชิญ? ลิงก์เดิมจะใช้ไม่ได้')"]) ?>
            <button class="btn btn-sm btn-outline-secondary w-100">เปลี่ยนลิงก์เชิญ (ถ้าลิงก์หลุดไปถึงคนอื่น)</button>
            <?= Html::endForm() ?>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h2 class="h6">สมาชิกในทีม (<?= count($members) ?>)</h2>
            <?php if (!$members): ?>
                <div class="text-muted small py-3">ยังไม่มีทีมงาน</div>
            <?php else: ?>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>ชื่อ (LINE)</th><th>เข้าร่วม</th><th class="text-end">เช็คอินแล้ว</th><th></th></tr></thead>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <?php if ($m['picture_url']): ?><img src="<?= Html::encode($m['picture_url']) ?>" width="24" height="24" class="rounded-circle me-1" alt=""><?php endif ?>
                                <?= Html::encode($m['display_name']) ?>
                            </td>
                            <td class="small"><?= $f->asDatetime($m['joined_at']) ?></td>
                            <td class="text-end"><?= (int)$m['checked'] ?></td>
                            <td class="text-end">
                                <?= Html::beginForm(['event-team-remove', 'id' => $event->id], 'post', ['onsubmit' => "return confirm('นำออกจากทีม?')"]) ?>
                                <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">นำออก</button>
                                <?= Html::endForm() ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </table>
            <?php endif ?>
        </div></div>
    </div>
</div>
<?php $this->registerJs(<<<'JS'
document.getElementById('copyBtn').addEventListener('click', async function () {
  const v = document.getElementById('invite');
  try { await navigator.clipboard.writeText(v.value); } catch (e) { v.select(); document.execCommand('copy'); }
  this.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว';
});
JS); ?>
