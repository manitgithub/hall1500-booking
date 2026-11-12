<?php

use yii\db\Migration;

/**
 * ค่าตั้งค่าระบบที่แอดมินแก้ได้จากหลังบ้าน (เช่น PromptPay)
 */
class m261112_000002_setting extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%setting}}', [
            'key' => $this->string(64)->notNull(),
            'value' => $this->text(),
            'updated_by' => $this->integer(),
            'updated_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk_setting', '{{%setting}}', 'key');
    }

    public function safeDown()
    {
        $this->dropTable('{{%setting}}');
    }
}
