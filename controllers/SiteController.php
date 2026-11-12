<?php

namespace app\controllers;

use app\models\Event;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;

class SiteController extends Controller
{
    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['post']]]];
    }

    public function actions()
    {
        return ['error' => ['class' => 'yii\web\ErrorAction']];
    }

    public function actionIndex()
    {
        $events = Event::find()
            ->with(['zonePrices', 'venue'])
            ->where(['status' => [Event::STATUS_PUBLISHED, Event::STATUS_CLOSED]])
            ->andWhere(['>', 'show_start', date('Y-m-d H:i:s', strtotime('-1 day'))])
            ->orderBy(['show_start' => SORT_ASC])
            ->all();
        return $this->render('index', ['events' => $events]);
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goBack();
        }
        return $this->render('login', [
            'lineReady' => Yii::$app->lineLogin->isConfigured(),
            'devLogin' => Yii::$app->params['devLogin'],
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }
}
