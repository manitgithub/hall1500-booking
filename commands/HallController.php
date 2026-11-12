<?php

namespace app\commands;

use app\models\Venue;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Console;

/**
 * จัดการผังที่นั่งของสถานที่
 *   php yii hall/generate            สร้างที่นั่งทุกสถานที่
 *   php yii hall/generate --venue=2  เฉพาะสถานที่ id 2
 *   php yii hall/stats
 */
class HallController extends Controller
{
    /** @var int|null id สถานที่ */
    public $venue;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['venue']);
    }

    /**
     * สร้าง/อัปเดตตาราง seat จากผังของสถานที่
     * ถ้ามีการขายแล้ว ที่นั่งเดิมจะถูกอัปเดต (จับคู่ด้วยชั้น+ป้าย) ไม่ลบประวัติการจอง
     */
    public function actionGenerate()
    {
        $q = Venue::find()->orderBy('id');
        if ($this->venue) {
            $q->where(['id' => (int)$this->venue]);
        }
        $venues = $q->all();
        if (!$venues) {
            $this->stderr("ไม่พบสถานที่\n");
            return ExitCode::DATAERR;
        }
        foreach ($venues as $v) {
            $n = $v->generateSeats();
            $this->stdout("#{$v->id} {$v->name}: $n ที่นั่ง\n", Console::FG_GREEN);
        }
        $this->stdout("หมายเหตุ: การแสดงที่เปิดขายอยู่ ให้กด 'อัปเดตรายการที่นั่ง' ในหน้าแอดมิน\n");
        return $this->actionStats();
    }

    /** สรุปจำนวนที่นั่งตามสถานที่ / ชั้น / section / โซน */
    public function actionStats()
    {
        $rows = (new Query())->select(['v.name', 's.floor', 's.section', 's.zone_code', 'cnt' => 'COUNT(*)'])
            ->from('{{%seat}} s')->innerJoin('{{%venue}} v', 'v.id = s.venue_id')->where(['s.is_active' => 1])
            ->groupBy(['v.id', 'v.name', 's.floor', 's.section', 's.zone_code'])
            ->orderBy(['v.id' => SORT_ASC, 's.floor' => SORT_ASC, 's.section' => SORT_ASC])->all();
        $this->stdout(sprintf("%-30s %4s %-8s %-6s %6s\n", 'สถานที่', 'ชั้น', 'section', 'โซน', 'ที่นั่ง'));
        $total = [];
        foreach ($rows as $r) {
            $this->stdout(sprintf("%-30s %4d %-8s %-6s %6d\n", $r['name'], $r['floor'], $r['section'], $r['zone_code'], $r['cnt']));
            $total[$r['name']] = ($total[$r['name']] ?? 0) + $r['cnt'];
        }
        foreach ($total as $name => $n) {
            $this->stdout("รวม $name: $n ที่นั่ง\n", Console::BOLD);
        }
        return ExitCode::OK;
    }
}
