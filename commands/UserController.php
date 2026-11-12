<?php

namespace app\commands;

use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * จัดการสิทธิ์ผู้ใช้
 *   php yii user/list
 *   php yii user/role 1 admin
 */
class UserController extends Controller
{
    public function actionList()
    {
        foreach (User::find()->orderBy('id')->all() as $u) {
            $this->stdout(sprintf("%4d  %-6s %-34s %s\n", $u->id, $u->role, $u->line_user_id, $u->display_name));
        }
        return ExitCode::OK;
    }

    /** @param string $id user id หรือ LINE userId */
    public function actionRole($id, $role)
    {
        $u = ctype_digit((string)$id) ? User::findOne((int)$id) : User::findOne(['line_user_id' => $id]);
        if (!$u || !in_array($role, ['user', 'organizer', 'admin'], true)) {
            $this->stderr("ไม่พบผู้ใช้ หรือ role ไม่ถูกต้อง (user|organizer|admin)\n");
            return ExitCode::DATAERR;
        }
        $u->updateAttributes(['role' => $role]);
        $this->stdout("{$u->display_name} => $role\n");
        return ExitCode::OK;
    }
}
