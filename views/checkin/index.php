<?php
/** @var yii\web\View $this */

use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'เช็คอินหน้างาน';
$this->registerJsFile('https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js');
?>
<div class="mx-auto" style="max-width:480px">
    <h1 class="h4 text-center mb-3"><i class="bi bi-qr-code-scan"></i> สแกนตั๋ว</h1>
    <div id="reader" class="bg-white rounded shadow-sm overflow-hidden"></div>
    <div id="result" class="mt-3"></div>
    <div class="input-group mt-3">
        <input id="manual" class="form-control" placeholder="หรือวางข้อความจาก QR">
        <button class="btn btn-outline-primary" id="manualBtn">ตรวจ</button>
    </div>
</div>
<?php
$cfg = Json::htmlEncode(['url' => Url::to(['verify']), 'csrf' => Yii::$app->request->csrfToken]);
$this->registerJs(<<<JS
(function () {
  const cfg = $cfg;
  const out = document.getElementById('result');
  let busy = false, last = '';
  const esc = (s) => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  async function verify(data) {
    if (busy || data === last) return;
    busy = true; last = data;
    const body = new URLSearchParams({ _csrf: cfg.csrf, data });
    try {
      const r = await (await fetch(cfg.url, { method: 'POST', body, credentials: 'same-origin' })).json();
      const b = r.booking;
      out.innerHTML = '<div class="alert alert-' + (r.ok ? 'success' : 'danger') + ' fs-5">'
        + '<b>' + esc(r.message) + '</b>'
        + (b ? '<div class="fs-6 mt-2">' + esc(b.code) + ' · ' + esc(b.name) + '<br>' + esc(b.event)
        + '<br>ที่นั่ง (' + b.count + '): <b>' + esc(b.seats) + '</b></div>' : '') + '</div>';
      if (navigator.vibrate) navigator.vibrate(r.ok ? 100 : [100, 80, 100]);
    } finally {
      busy = false;
      setTimeout(() => { last = ''; }, 3000);
    }
  }
  new Html5QrcodeScanner('reader', { fps: 10, qrbox: 250 }, false).render(verify, () => {});
  document.getElementById('manualBtn').onclick = () => verify(document.getElementById('manual').value);
})();
JS);
