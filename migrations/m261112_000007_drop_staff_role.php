<?php

use yii\db\Migration;

/**
 * ยกเลิกสิทธิ์ "เจ้าหน้าที่" (staff): เช็คอินหน้างานทำโดยแอดมิน (ทุกงาน) หรือผู้จัดงาน (งานของตัวเอง)
 * ผู้ใช้ที่เป็น staff อยู่จะกลับเป็นผู้ใช้ทั่วไป
 */
class m261112_000007_drop_staff_role extends Migration
{
    public function safeUp()
    {
        $n = $this->db->createCommand()->update('{{%user}}', ['role' => 'user'], ['role' => 'staff'])->execute();
        echo "    > เปลี่ยน staff เป็น user $n คน\n";
        $this->alterColumn('{{%user}}', 'role', "ENUM('user','organizer','admin') NOT NULL DEFAULT 'user'");
    }

    public function safeDown()
    {
        $this->alterColumn('{{%user}}', 'role', "ENUM('user','organizer','staff','admin') NOT NULL DEFAULT 'user'");
    }
}
