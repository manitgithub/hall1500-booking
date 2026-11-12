<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * สแกน QR ตั๋วหน้างาน (staff / admin)
 */
class CheckinController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [[
                'allow' => true, 'roles' => ['@'],
                'matchCallback' => fn() => Yii::$app->user->identity->isStaff(),
            ]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['verify' => ['post']]],
        ];
    }

    public function actionIndex()
    {
        return $this->render('/checkin/index');
    }

    public function actionVerify()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->booking->checkIn((string)Yii::$app->request->post('data'), Yii::$app->user->identity);
        $b = $r['booking'];
        return [
            'ok' => $r['ok'],
            'message' => $r['message'],
            'booking' => $b ? [
                'code' => $b->code,
                'name' => $b->user->display_name,
                'event' => $b->event->title,
                'seats' => $b->seatLabels(),
                'count' => $b->seat_count,
            ] : null,
        ];
    }
}
