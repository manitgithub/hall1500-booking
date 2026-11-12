<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * งานตามเวลา: ตั้ง cron ทุกนาที
 *   * * * * * php /path/to/yii booking/expire >/dev/null 2>&1
 */
class BookingController extends Controller
{
    public function actionExpire()
    {
        $n = Yii::$app->booking->releaseExpired();
        if ($n) {
            $this->stdout("ปล่อยที่นั่งจาก $n รายการที่หมดเวลา\n");
        }
        return ExitCode::OK;
    }
}
