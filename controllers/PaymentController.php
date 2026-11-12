<?php

namespace app\controllers;

use app\components\PromptPay;
use app\components\QrImage;
use app\models\Booking;
use app\models\Payment;
use app\models\SlipForm;
use Yii;
use yii\base\UserException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class PaymentController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'except' => ['omise-webhook'],
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'slip' => ['post'], 'omise-promptpay' => ['post'], 'omise-card' => ['post'], 'omise-webhook' => ['post'],
            ]],
        ];
    }

    public function beforeAction($action)
    {
        if ($action->id === 'omise-webhook') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /** QR PromptPay ตามยอดเงินของรายการจอง */
    public function actionPromptpayQr($code)
    {
        $b = $this->findOwnBooking($code);
        $id = Yii::$app->params['payment']['promptpayId'];
        if (!$id || !$b->isPayable()) {
            throw new NotFoundHttpException();
        }
        return QrImage::send(PromptPay::payload($id, (float)$b->total_amount), 320);
    }

    /** แนบสลิป */
    public function actionSlip($code)
    {
        $b = $this->findOwnBooking($code);
        $form = new SlipForm();
        $form->load(Yii::$app->request->post());
        $form->slip = UploadedFile::getInstance($form, 'slip');
        if (!$form->validate()) {
            Yii::$app->session->setFlash('danger', implode(' ', $form->getErrorSummary(true)));
            return $this->redirect(['/booking/view', 'code' => $b->code]);
        }
        try {
            Yii::$app->booking->submitSlip($b, $form);
            Yii::$app->session->setFlash('success', 'ส่งสลิปเรียบร้อย เจ้าหน้าที่จะตรวจสอบและแจ้งผลทาง LINE');
        } catch (UserException $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
        }
        return $this->redirect(['/booking/view', 'code' => $b->code]);
    }

    /** สร้าง charge PromptPay ผ่าน Omise */
    public function actionOmisePromptpay($code)
    {
        $b = $this->findOwnBooking($code);
        $omise = Yii::$app->omise;
        if (!$omise->isEnabled() || !$b->isPayable()) {
            throw new NotFoundHttpException();
        }
        // ใช้ charge เดิมถ้ายังรออยู่
        $p = Payment::find()->where([
            'booking_id' => $b->id, 'method' => Payment::METHOD_OMISE_PROMPTPAY, 'status' => Payment::STATUS_PENDING,
        ])->one();
        if (!$p) {
            try {
                $charge = $omise->createPromptPayCharge($b);
            } catch (\Throwable $e) {
                Yii::error($e->getMessage(), 'payment');
                Yii::$app->session->setFlash('danger', 'สร้างรายการชำระเงินไม่สำเร็จ กรุณาลองใหม่');
                return $this->redirect(['/booking/view', 'code' => $b->code]);
            }
            $p = new Payment([
                'booking_id' => $b->id,
                'method' => Payment::METHOD_OMISE_PROMPTPAY,
                'amount' => $b->total_amount,
                'status' => Payment::STATUS_PENDING,
                'gateway_ref' => $charge['id'],
                'raw_response' => json_encode($charge, JSON_UNESCAPED_UNICODE),
            ]);
            $p->save(false);
        }
        $charge = json_decode($p->raw_response, true);
        $qrUrl = $charge['source']['scannable_code']['image']['download_uri'] ?? null;
        return $this->render('omise-promptpay', ['b' => $b, 'p' => $p, 'qrUrl' => $qrUrl]);
    }

    /** ชำระด้วยบัตร: รับ token จาก Omise.js */
    public function actionOmiseCard($code)
    {
        $b = $this->findOwnBooking($code);
        $omise = Yii::$app->omise;
        $token = (string)Yii::$app->request->post('omiseToken');
        if (!$omise->isEnabled() || !$b->isPayable() || !$token) {
            throw new NotFoundHttpException();
        }
        try {
            $returnUri = Yii::$app->params['appUrl'] . '/payment/omise-return?code=' . $b->code;
            $charge = $omise->createCardCharge($b, $token, $returnUri);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'payment');
            Yii::$app->session->setFlash('danger', 'ชำระด้วยบัตรไม่สำเร็จ: ' . $e->getMessage());
            return $this->redirect(['/booking/view', 'code' => $b->code]);
        }
        $p = new Payment([
            'booking_id' => $b->id, 'method' => Payment::METHOD_OMISE_CARD, 'amount' => $b->total_amount,
            'status' => Payment::STATUS_PENDING, 'gateway_ref' => $charge['id'],
            'raw_response' => json_encode($charge, JSON_UNESCAPED_UNICODE),
        ]);
        $p->save(false);
        if (!empty($charge['authorize_uri']) && ($charge['status'] ?? '') === 'pending') {
            return $this->redirect($charge['authorize_uri']);   // 3-D Secure
        }
        $this->applyCharge($p, $charge);
        return $this->redirect(['/booking/view', 'code' => $b->code]);
    }

    /** กลับจากหน้า 3-D Secure */
    public function actionOmiseReturn($code)
    {
        $b = $this->findOwnBooking($code);
        $this->syncOmise($b);
        return $this->redirect(['/booking/view', 'code' => $b->code]);
    }

    /** หน้า QR โพลถามสถานะ */
    public function actionOmiseStatus($code)
    {
        $b = $this->findOwnBooking($code);
        $this->syncOmise($b);
        $b->refresh();
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['status' => $b->status, 'paid' => $b->status === Booking::STATUS_PAID];
    }

    /**
     * Webhook จาก Omise: ไม่เชื่อ payload ตรง ๆ แต่ดึง charge จาก API มาตรวจอีกครั้ง
     * ตั้งค่า URL ใน Omise Dashboard: {APP_URL}/payment/omise-webhook
     */
    public function actionOmiseWebhook()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $body = Yii::$app->request->bodyParams;
        $chargeId = $body['data']['id'] ?? null;
        if (($body['key'] ?? '') !== 'charge.complete' || !$chargeId) {
            return ['ok' => true];
        }
        $p = Payment::findOne(['gateway_ref' => $chargeId]);
        if (!$p) {
            return ['ok' => true];
        }
        try {
            $this->applyCharge($p, Yii::$app->omise->retrieveCharge($chargeId));
        } catch (\Throwable $e) {
            Yii::error('webhook: ' . $e->getMessage(), 'payment');
            Yii::$app->response->statusCode = 500;
            return ['ok' => false];
        }
        return ['ok' => true];
    }

    private function syncOmise(Booking $b): void
    {
        if (!Yii::$app->omise->isEnabled()) {
            return;
        }
        $pending = Payment::find()->where([
            'booking_id' => $b->id, 'status' => Payment::STATUS_PENDING,
            'method' => [Payment::METHOD_OMISE_PROMPTPAY, Payment::METHOD_OMISE_CARD],
        ])->all();
        foreach ($pending as $p) {
            try {
                $this->applyCharge($p, Yii::$app->omise->retrieveCharge($p->gateway_ref));
            } catch (\Throwable $e) {
                Yii::warning($e->getMessage(), 'payment');
            }
        }
    }

    /** อัปเดตสถานะ payment ตาม charge ที่ดึงจาก Omise */
    private function applyCharge(Payment $p, array $charge): void
    {
        if ($p->status !== Payment::STATUS_PENDING) {
            return;
        }
        $status = $charge['status'] ?? '';
        $amountOk = (int)($charge['amount'] ?? 0) === (int)round($p->amount * 100)
            && ($charge['metadata']['booking_code'] ?? null) === $p->booking->code;

        if ($status === 'successful' && $amountOk) {
            $p->updateAttributes(['status' => Payment::STATUS_SUCCESS, 'verified_at' => date('Y-m-d H:i:s'),
                'raw_response' => json_encode($charge, JSON_UNESCAPED_UNICODE)]);
            Yii::$app->booking->confirmPaid($p->booking);
        } elseif (in_array($status, ['failed', 'expired', 'reversed'], true) || ($status === 'successful' && !$amountOk)) {
            $p->updateAttributes(['status' => Payment::STATUS_FAILED, 'note' => $charge['failure_message'] ?? $status,
                'raw_response' => json_encode($charge, JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function findOwnBooking(string $code): Booking
    {
        $b = Booking::find()->with('event')->where(['code' => $code])->one();
        if (!$b) {
            throw new NotFoundHttpException();
        }
        if ($b->user_id !== (int)Yii::$app->user->id) {
            throw new ForbiddenHttpException();
        }
        return $b;
    }
}
