<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * เข้าสู่ระบบด้วย LINE
 */
class AuthController extends Controller
{
    public function actionLine()
    {
        return $this->redirect(Yii::$app->lineLogin->buildAuthorizeUrl());
    }

    public function actionLineCallback($code = null, $state = null, $error = null, $error_description = null)
    {
        if ($error) {
            Yii::$app->session->setFlash('warning', 'ยกเลิกการเข้าสู่ระบบด้วย LINE');
            return $this->redirect(['/site/login']);
        }
        $profile = Yii::$app->lineLogin->handleCallback($code, $state);
        $user = User::upsertFromLine($profile);
        Yii::$app->user->login($user, 3600 * 24 * 30);
        Yii::$app->session->setFlash('success', 'สวัสดีคุณ ' . $user->display_name);
        return $this->goBack(['/site/index']);
    }

    /**
     * ล็อกอินทดสอบ (เปิดด้วย DEV_LOGIN=true เท่านั้น)
     */
    public function actionDevLogin($role = 'user')
    {
        if (!Yii::$app->params['devLogin']) {
            throw new NotFoundHttpException();
        }
        $role = in_array($role, ['user', 'staff', 'admin'], true) ? $role : 'user';
        $user = User::upsertFromLine(['sub' => 'Udev' . $role, 'name' => "ทดสอบ ($role)"]);
        $user->updateAttributes(['role' => $role]);
        Yii::$app->user->login($user, 3600);
        return $this->goBack(['/site/index']);
    }
}
