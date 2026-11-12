<?php

use yii\db\Migration;

/**
 * ทีมงานต่อการแสดง: ผู้จัดงานส่งลิงก์เชิญ คนในทีมเช็คอินหน้างานได้เฉพาะงานนั้น
 */
class m261112_000008_event_team extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%event}}', 'team_token', $this->string(32)->after('promptpay_name'));

        $this->createTable('{{%event_team}}', [
            'event_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'joined_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->addPrimaryKey('pk_event_team', '{{%event_team}}', ['event_id', 'user_id']);
        $this->createIndex('ix_event_team_user', '{{%event_team}}', 'user_id');
        $this->addForeignKey('fk_et_event', '{{%event_team}}', 'event_id', '{{%event}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_et_user', '{{%event_team}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%event_team}}');
        $this->dropColumn('{{%event}}', 'team_token');
    }
}
