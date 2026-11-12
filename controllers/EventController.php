<?php

namespace app\controllers;

use app\models\Booking;
use app\models\Event;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class EventController extends Controller
{
    public function actionView($id)
    {
        $event = $this->findEvent($id);
        $pending = null;
        if (!Yii::$app->user->isGuest) {
            $pending = Booking::find()->where([
                'event_id' => $event->id, 'user_id' => Yii::$app->user->id,
                'status' => [Booking::STATUS_PENDING, Booking::STATUS_VERIFYING],
            ])->one();
        }
        return $this->render('view', ['event' => $event, 'pending' => $pending]);
    }

    /** JSON ผังที่นั่ง + สถานะ (โพลทุก ๆ 15 วินาทีจากหน้าเลือกที่นั่ง) */
    public function actionSeats($id)
    {
        $event = $this->findEvent($id);
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        return $event->seatMapData(Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id);
    }

    private function findEvent($id): Event
    {
        $e = Event::find()->with('zonePrices')->where(['id' => (int)$id])->one();
        $isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
        if (!$e || ($e->status === Event::STATUS_DRAFT && !$isAdmin)) {
            throw new NotFoundHttpException('ไม่พบการแสดงนี้');
        }
        return $e;
    }
}
