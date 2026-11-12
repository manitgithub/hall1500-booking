<?php

use yii\db\Migration;

/**
 * ย้ายสลิปจาก web/uploads/slips (เปิดผ่าน URL ได้) ไป storage/slips (นอก web root)
 * ดูสลิปผ่าน /payment/slip-image ซึ่งตรวจสิทธิ์ทุกครั้ง
 */
class m261112_000006_move_slips extends Migration
{
    public function safeUp()
    {
        $this->moveAll(Yii::getAlias('@app/web/uploads/slips'), Yii::getAlias('@app/storage/slips'));
    }

    public function safeDown()
    {
        $this->moveAll(Yii::getAlias('@app/storage/slips'), Yii::getAlias('@app/web/uploads/slips'));
    }

    private function moveAll(string $from, string $to): void
    {
        if (!is_dir($from)) {
            echo "    > ไม่มีโฟลเดอร์ $from ข้าม\n";
            return;
        }
        if (!is_dir($to)) {
            mkdir($to, 0750, true);
        }
        $n = 0;
        foreach (new DirectoryIterator($from) as $f) {
            if (!$f->isFile() || str_starts_with($f->getFilename(), '.')) {
                continue;
            }
            $target = $to . '/' . $f->getFilename();
            if (!rename($f->getPathname(), $target)) {
                throw new RuntimeException("ย้ายไฟล์ไม่สำเร็จ: {$f->getPathname()}");
            }
            $n++;
        }
        echo "    > ย้ายสลิป $n ไฟล์ไป $to\n";
    }
}
