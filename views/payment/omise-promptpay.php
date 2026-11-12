<?php
/** @var app\models\Booking $b */
/** @var app\models\Payment $p */
/** @var string|null $qrUrl */

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'ชำระเงิน PromptPay';
?>
<div class="card border-0 shadow-sm mx-auto text-center" style="max-width:420px">
    <div class="card-body">
        <h1 class="h5">สแกนเพื่อชำระ <?= Yii::$app->formatter->asDecimal($b->total_amount, 2) ?> บาท</h1>
        <div class="small text-muted mb-2">รหัสจอง <?= $b->code ?></div>
        <?php if ($qrUrl): ?>
            <img src="<?= Html::encode($qrUrl) ?>" class="img-fluid" alt="PromptPay QR" style="max-width:300px">
        <?php else: ?>
            <div class="alert alert-danger">ไม่พบ QR จาก Omise</div>
        <?php endif ?>
        <div class="mt-3" id="state"><span class="spinner-border spinner-border-sm"></span> รอการชำระเงิน… ระบบจะยืนยันอัตโนมัติ</div>
        <a href="<?= Url::to(['/booking/view', 'code' => $b->code]) ?>" class="btn btn-link btn-sm mt-2">กลับ</a>
    </div>
</div>
<?php
$statusUrl = Json::htmlEncode(Url::to(['/payment/omise-status', 'code' => $b->code]));
$ticketUrl = Json::htmlEncode(Url::to(['/booking/ticket', 'code' => $b->code]));
$this->registerJs(<<<JS
setInterval(async () => {
  const r = await fetch($statusUrl, { credentials: 'same-origin' });
  const j = await r.json();
  if (j.paid) location.href = $ticketUrl;
}, 5000);
JS);
