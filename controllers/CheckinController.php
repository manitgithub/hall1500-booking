<?php

namespace app\controllers;

use app\models\Event;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * สแกน QR ตั๋วหน้างาน (แอดมิน = ทุกงาน, ผู้จัดงาน = งานของตัวเอง, ทีมงาน = งานที่ได้รับเชิญ)
 */
class CheckinController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [[
                'allow' => true, 'roles' => ['@'],
                'matchCallback' => fn() => Yii::$app->user->identity->canUseCheckin(),
            ]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['verify' => ['post']]],
        ];
    }

    public function actionIndex()
    {
        $u = Yii::$app->user->identity;
        $q = Event::find()->with('venue')->where(['>', 'show_start', date('Y-m-d H:i:s', strtotime('-1 day'))])
            ->andWhere(['not', ['status' => Event::STATUS_DRAFT]])->orderBy('show_start');
        if (!$u->isAdmin()) {
            $q->andWhere(['or', ['owner_id' => $u->isOrganizer() ? $u->id : 0], ['id' => $u->teamEventIds() ?: [0]]]);
        }
        return $this->render('/checkin/index', ['events' => $q->all()]);
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
