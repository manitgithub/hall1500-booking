<?php

use yii\db\Migration;

/**
 * บัญชี PromptPay แยกต่อการแสดง (ว่าง = ใช้ค่าจาก หลังบ้าน → ตั้งค่าระบบ)
 */
class m261112_000004_event_promptpay extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%event}}', 'promptpay_id', $this->string(20)->after('hold_minutes'));
        $this->addColumn('{{%event}}', 'promptpay_name', $this->string(255)->after('promptpay_id'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%event}}', 'promptpay_name');
        $this->dropColumn('{{%event}}', 'promptpay_id');
    }
}
