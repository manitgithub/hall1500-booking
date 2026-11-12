<?php

namespace app\controllers;

use app\components\QrImage;
use app\models\Booking;
use app\models\Event;
use app\models\SlipForm;
use Yii;
use yii\base\UserException;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class BookingController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['@']]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['hold' => ['post'], 'cancel' => ['post']]],
        ];
    }

    /** เลือกที่นั่งแล้วกด "จอง" */
    public function actionHold()
    {
        $req = Yii::$app->request;
        $event = Event::findOne((int)$req->post('event_id'));
        if (!$event) {
            throw new NotFoundHttpException();
        }
        $seatIds = (array)$req->post('seats', []);
        try {
            $booking = Yii::$app->booking->hold($event, Yii::$app->user->identity, $seatIds);
        } catch (UserException $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
            return $this->redirect(['/event/view', 'id' => $event->id]);
        }
        return $this->redirect(['view', 'code' => $booking->code]);
    }

    /** หน้าชำระเงิน / รายละเอียดการจอง */
    public function actionView($code)
    {
        $b = $this->findBooking($code);
        if ($b->status === Booking::STATUS_PENDING && $b->secondsLeft() === 0) {
            Yii::$app->booking->releaseExpired($b->event_id);
            $b->refresh();
        }
        if ($b->status === Booking::STATUS_PAID) {
            return $this->redirect(['ticket', 'code' => $b->code]);
        }
        return $this->render('view', [
            'b' => $b,
            'slipForm' => new SlipForm(['transferred_at' => date('Y-m-d\TH:i')]),
            'omiseEnabled' => Yii::$app->omise->isEnabled(),
        ]);
    }

    public function actionCancel($code)
    {
        $b = $this->findBooking($code, true);
        try {
            Yii::$app->booking->cancel($b);
            Yii::$app->session->setFlash('info', 'ยกเลิกการจองแล้ว');
        } catch (UserException $e) {
            Yii::$app->session->setFlash('danger', $e->getMessage());
        }
        return $this->redirect(['my']);
    }

    public function actionMy()
    {
        $provider = new ActiveDataProvider([
            'query' => Booking::find()->with(['event', 'seats'])
                ->where(['user_id' => Yii::$app->user->id])
                ->andWhere(['not', ['status' => Booking::STATUS_CANCELLED]])
                ->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);
        return $this->render('my', ['provider' => $provider]);
    }

    public function actionTicket($code)
    {
        $b = $this->findBooking($code);
        if ($b->status !== Booking::STATUS_PAID) {
            return $this->redirect(['view', 'code' => $b->code]);
        }
        return $this->render('ticket', ['b' => $b]);
    }

    /** รูป QR ของตั๋ว */
    public function actionQr($code)
    {
        $b = $this->findBooking($code);
        if ($b->status !== Booking::STATUS_PAID) {
            throw new NotFoundHttpException();
        }
        return QrImage::send($b->ticketQrData(), 360);
    }

    private function findBooking(string $code, bool $ownerOnly = false): Booking
    {
        $b = Booking::find()->with(['event', 'seats', 'user'])->where(['code' => $code])->one();
        if (!$b) {
            throw new NotFoundHttpException('ไม่พบรายการจอง');
        }
        $user = Yii::$app->user->identity;
        if ($ownerOnly ? $b->user_id !== $user->id : !$b->canView($user)) {
            throw new ForbiddenHttpException();
        }
        return $b;
    }
}
