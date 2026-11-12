<?php

namespace app\commands;

use app\models\Hall;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Console;

/**
 * จัดการผังที่นั่งห้องประชุม 1500 (อ่านจาก config/hall1500.php)
 */
class HallController extends Controller
{
    /** @var bool ลบที่นั่งเดิมแม้มีการแสดงที่ใช้งานอยู่ (อันตราย) */
    public $force = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['force']);
    }

    /**
     * สร้าง/อัปเดตตาราง seat จาก config
     * - ถ้ายังไม่มีการขาย: ลบแล้วสร้างใหม่ทั้งหมด
     * - ถ้ามีการขายแล้ว: อัปเดตตำแหน่ง/โซนของที่นั่งเดิม (จับคู่ด้วย floor+label) และเพิ่มที่นั่งใหม่
     *   ที่นั่งที่หายไปจาก config จะถูกตั้ง is_active = 0
     */
    public function actionGenerate()
    {
        $seats = Hall::buildSeats();
        $db = Yii::$app->db;
        $used = (new Query())->from('{{%booking_seat}}')->exists();

        $tx = $db->beginTransaction();
        try {
            if (!$used || $this->force) {
                if ($this->force && $used && !$this->confirm('มีประวัติการจองอยู่ ต้องการลบทั้งหมดจริงหรือไม่?')) {
                    return ExitCode::OK;
                }
                $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
                $db->createCommand()->delete('{{%booking_seat}}')->execute();
                $db->createCommand()->delete('{{%event_seat}}')->execute();
                $db->createCommand()->delete('{{%seat}}')->execute();
                $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
                foreach (array_chunk($seats, 500) as $chunk) {
                    $db->createCommand()->batchInsert('{{%seat}}', array_keys($chunk[0]), array_map('array_values', $chunk))->execute();
                }
            } else {
                $existing = (new Query())->select(['id', 'k' => "CONCAT(floor, ':', label)"])->from('{{%seat}}')->indexBy('k')->column();
                $seen = [];
                foreach ($seats as $s) {
                    $k = $s['floor'] . ':' . $s['label'];
                    if (isset($existing[$k])) {
                        $db->createCommand()->update('{{%seat}}', $s, ['id' => $existing[$k]])->execute();
                        $seen[] = $existing[$k];
                    } else {
                        $db->createCommand()->insert('{{%seat}}', $s)->execute();
                    }
                }
                $db->createCommand()->update('{{%seat}}', ['is_active' => 0], ['not in', 'id', $seen ?: [0]])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $this->stdout('สร้างผังที่นั่งแล้ว ' . count($seats) . " ที่นั่ง\n", Console::FG_GREEN);
        $this->stdout("หมายเหตุ: รอบการแสดงที่เปิดขายอยู่ ให้กด 'อัปเดตรายการที่นั่ง' ในหน้าแอดมิน\n");
        return $this->actionStats();
    }

    /** สรุปจำนวนที่นั่งตามชั้น / section / โซน */
    public function actionStats()
    {
        $rows = (new Query())->select(['floor', 'section', 'zone_code', 'cnt' => 'COUNT(*)'])
            ->from('{{%seat}}')->where(['is_active' => 1])
            ->groupBy(['floor', 'section', 'zone_code'])->orderBy(['floor' => SORT_ASC, 'section' => SORT_ASC])->all();
        $total = 0;
        $this->stdout(sprintf("%-20s %-8s %-6s %6s\n", 'ชั้น', 'section', 'โซน', 'ที่นั่ง'));
        foreach ($rows as $r) {
            $this->stdout(sprintf("%-20s %-8s %-6s %6d\n", Hall::floorLabel((int)$r['floor']), $r['section'], $r['zone_code'], $r['cnt']));
            $total += $r['cnt'];
        }
        $this->stdout("รวม $total ที่นั่ง\n", Console::BOLD);
        return ExitCode::OK;
    }
}
