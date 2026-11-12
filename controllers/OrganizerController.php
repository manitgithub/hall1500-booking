<?php

namespace app\controllers;

use app\models\OrganizerApplication;
use app\models\Setting;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

/**
 * สมัครเป็นผู้จัดงาน: กรอกข้อมูล แนบสำเนาบัตรประชาชน และยอมรับเงื่อนไข → รอแอดมินอนุมัติ
 */
class OrganizerController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['@']]]],
        ];
    }

    public function actionApply()
    {
        $user = Yii::$app->user->identity;
        $latest = OrganizerApplication::latestFor($user->id);

        if ($user->canManageEvents()) {
            return $this->render('apply-status', ['app' => $latest, 'user' => $user]);
        }
        if ($latest && $latest->status === OrganizerApplication::STATUS_PENDING) {
            return $this->render('apply-status', ['app' => $latest, 'user' => $user]);
        }
        if ($latest && $latest->status === OrganizerApplication::STATUS_REVOKED) {
            return $this->render('apply-status', ['app' => $latest, 'user' => $user]);
        }

        $terms = Setting::organizerTerms();
        $model = new OrganizerApplication([
            'full_name' => $latest->full_name ?? '',
            'organization' => $latest->organization ?? null,
            'phone' => $latest->phone ?? $user->phone,
            'email' => $latest->email ?? $user->email,
        ]);
        $model->scenario = 'apply';
        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            // ยืนยันว่าผู้สมัครยอมรับเงื่อนไขฉบับเดียวกับที่เห็นบนหน้าจอ
            if (Yii::$app->request->post('terms_hash') !== hash('sha256', $terms)) {
                Yii::$app->session->setFlash('warning', 'เงื่อนไขเพิ่งถูกแก้ไข กรุณาอ่านฉบับล่าสุดแล้วยืนยันอีกครั้ง');
            } elseif ($model->submit($user, $terms, Yii::$app->request->userIP)) {
                Yii::$app->session->setFlash('success', 'ส่งใบสมัครแล้ว ผู้ดูแลระบบจะตรวจสอบและแจ้งผล');
                return $this->refresh();
            }
        }
        return $this->render('apply', ['model' => $model, 'terms' => $terms, 'previous' => $latest]);
    }
}
