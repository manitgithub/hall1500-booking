<?php
/** @var yii\web\View $this */
/** @var app\models\Venue $venue */

use app\models\Hall;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = $venue->isNewRecord ? 'เพิ่มสถานที่' : 'สถานที่: ' . $venue->name;
$this->registerJsFile('@web/js/seatmap.js?v=2');
$zones = Hall::zones();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['venues']) ?>" class="btn btn-link">← สถานที่ทั้งหมด</a>
</div>
<div class="row g-4">
    <div class="col-lg-5">
        <?php $form = ActiveForm::begin([
            'fieldConfig' => [
                'options' => ['class' => 'mb-3'], 'labelOptions' => ['class' => 'form-label'],
                'inputOptions' => ['class' => 'form-control'], 'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]) ?>
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <?= $form->field($venue, 'name')->textInput(['placeholder' => 'เช่น หอประชุมใหญ่, โรงละครเล็ก']) ?>
            <?= $form->field($venue, 'location')->textInput(['placeholder' => 'เช่น อาคาร 2 ชั้น 3']) ?>
            <?= $form->field($venue, 'description')->textarea(['rows' => 2]) ?>
            <?= $form->field($venue, 'is_active')->checkbox(['class' => 'form-check-input'], false)->label('เปิดใช้งาน (เลือกได้ตอนสร้างการแสดง)') ?>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <h2 class="h6">ผังที่นั่ง</h2>
            <?= $form->field($venue, 'layout')->dropDownList($venue->layoutOptions(), ['class' => 'form-select', 'id' => 'layout']) ?>
            <div id="grid-fields">
                <div class="row">
                    <div class="col-5"><?= $form->field($venue, 'grid_rows')->input('number', ['min' => 1, 'max' => 60]) ?></div>
                    <div class="col-7"><?= $form->field($venue, 'grid_sections')->textInput(['placeholder' => '8,16,8']) ?></div>
                </div>
                <div class="form-text mb-2 mt-n2">
                    ตัวอย่าง <code>8,16,8</code> = 3 บล็อก มีทางเดินคั่น แถวละ 32 ที่ · ป้ายแถว A, B, C … เรียงจากหน้าเวที
                    · เลขที่นั่งนับต่อเนื่องทั้งแถวจากซ้ายไปขวา
                </div>
                <div class="small fw-semibold mb-1">โซนราคา (นับแถวจากหน้าเวที แถวที่เหลือเป็น<?= Html::encode($zones['C']['label']) ?>)</div>
                <div class="row">
                    <?php foreach (['grid_zone_vip' => 'VIP', 'grid_zone_a' => 'A', 'grid_zone_b' => 'B'] as $attr => $code): ?>
                        <div class="col-4"><?= $form->field($venue, $attr)->input('number', ['min' => 0])
                            ->label('<span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:' . $zones[$code]['color'] . '"></span>' . Html::encode($zones[$code]['label']) . ' (แถว)') ?></div>
                    <?php endforeach ?>
                </div>
            </div>
            <div id="file-fields" class="form-text">
                ผังละเอียดกำหนดในไฟล์ <code>config/venues/&lt;ชื่อ&gt;.php</code> (ตำแหน่งที่นั่งรายแถวจากแบบจริง)
                ถ้าแก้ไฟล์ ให้กด "สร้างผังที่นั่งใหม่จากไฟล์" ด้านล่าง
            </div>
            <?php if (!$venue->isNewRecord && $venue->hasSales()): ?>
                <div class="alert alert-warning small mt-3 mb-0">
                    สถานที่นี้มีการจองแล้ว การแก้ผังจะอัปเดตที่นั่งเดิม (จับคู่จากป้ายที่นั่ง) และปิดที่นั่งที่ไม่มีในผังใหม่ ประวัติการจองยังอยู่ครบ
                </div>
            <?php endif ?>
        </div></div>
        <button class="btn btn-primary btn-lg w-100"><i class="bi bi-save"></i> บันทึกและสร้างผังที่นั่ง</button>
        <?php ActiveForm::end() ?>
        <?php if (!$venue->isNewRecord && !$venue->isGrid()): ?>
            <?= Html::beginForm(['venue-generate', 'id' => $venue->id], 'post', ['class' => 'mt-2']) ?>
            <button class="btn btn-outline-secondary w-100">สร้างผังที่นั่งใหม่จากไฟล์</button>
            <?= Html::endForm() ?>
        <?php endif ?>
    </div>
    <div class="col-lg-7">
        <?php if ($venue->isNewRecord): ?>
            <div class="text-muted text-center py-5 border rounded bg-white">บันทึกแล้วจะเห็นตัวอย่างผังที่นั่งตรงนี้</div>
        <?php else: ?>
            <div class="d-flex justify-content-between mb-2">
                <div class="fw-semibold">ตัวอย่างผัง · <?= number_format($venue->seatCount()) ?> ที่นั่ง</div>
            </div>
            <div class="seatmap-wrap">
                <div class="seatmap-tools">
                    <button class="btn btn-light btn-sm border" id="zin" type="button"><i class="bi bi-zoom-in"></i></button>
                    <button class="btn btn-light btn-sm border" id="zout" type="button"><i class="bi bi-zoom-out"></i></button>
                </div>
                <div class="seatmap-box" id="seatmap"></div>
            </div>
            <div class="legend mt-2" id="legend"></div>
        <?php endif ?>
    </div>
</div>
<?php
$url = $venue->isNewRecord ? null : Url::to(['venue-seats', 'id' => $venue->id]);
$js = 'const venueSeatsUrl = ' . Json::htmlEncode($url) . ";\n";
$this->registerJs($js . <<<'JS'
(function () {
  const layout = document.getElementById('layout');
  const sync = () => {
    const grid = layout.value === 'grid';
    document.getElementById('grid-fields').style.display = grid ? '' : 'none';
    document.getElementById('file-fields').style.display = grid ? 'none' : '';
  };
  layout.addEventListener('change', sync); sync();
  if (venueSeatsUrl) {
    const map = new SeatMap(document.getElementById('seatmap'), { url: venueSeatsUrl, mode: 'preview' });
    map.load().then(() => map.legend(document.getElementById('legend')));
    document.getElementById('zin').onclick = () => map.zoomIn();
    document.getElementById('zout').onclick = () => map.zoomOut();
  }
})();
JS);
