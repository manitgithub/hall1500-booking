<?php

namespace app\controllers;

use app\models\Event;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * เข้าร่วมทีมงานของการแสดงผ่านลิงก์เชิญ (ต้องล็อกอินก่อน)
 */
class TeamController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['@']]]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['accept' => ['post']]],
        ];
    }

    public function actionJoin($id, $t)
    {
        $event = $this->findByToken($id, $t);
        $user = Yii::$app->user->identity;
        return $this->render('join', [
            'event' => $event, 't' => $t,
            'already' => $user->canCheckInEvent($event),
            'manages' => $user->managesEvent($event),
        ]);
    }

    public function actionAccept($id, $t)
    {
        $event = $this->findByToken($id, $t);
        $user = Yii::$app->user->identity;
        if (!$user->managesEvent($event)) {
            $event->addTeamMember($user);
        }
        Yii::$app->session->setFlash('success', 'เข้าร่วมทีมงาน "' . $event->title . '" แล้ว สแกนตั๋วได้ที่หน้านี้');
        return $this->redirect(['/checkin/index']);
    }

    private function findByToken($id, $t): Event
    {
        $event = Event::findOne((int)$id);
        if (!$event || !$event->team_token || !is_string($t) || !hash_equals($event->team_token, $t)
            || in_array($event->status, [Event::STATUS_CANCELLED], true)) {
            throw new NotFoundHttpException('ลิงก์เชิญไม่ถูกต้อง หรือถูกเปลี่ยนแล้ว กรุณาขอลิงก์ใหม่จากผู้จัดงาน');
        }
        return $event;
    }
}
