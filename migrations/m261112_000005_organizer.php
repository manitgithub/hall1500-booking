<?php

use yii\db\Migration;

/**
 * ผู้จัดงาน: สมัครพร้อมสำเนาบัตรประชาชน + ยอมรับเงื่อนไข แอดมินอนุมัติแล้วจึงสร้าง/จัดการงานของตัวเองได้
 */
class m261112_000005_organizer extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('{{%user}}', 'role', "ENUM('user','organizer','staff','admin') NOT NULL DEFAULT 'user'");

        // เจ้าของการแสดง (NULL = งานของแอดมิน/ระบบ)
        $this->addColumn('{{%event}}', 'owner_id', $this->integer()->after('venue_id'));
        $this->addForeignKey('fk_event_owner', '{{%event}}', 'owner_id', '{{%user}}', 'id');

        $this->createTable('{{%organizer_application}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'full_name' => $this->string(255)->notNull(),
            'organization' => $this->string(255),
            'phone' => $this->string(20)->notNull(),
            'email' => $this->string(255),
            // ไฟล์เก็บนอก web root (storage/id-cards) เปิดดูได้เฉพาะแอดมิน
            'id_card_file' => $this->string(100)->notNull(),
            'id_card_mime' => $this->string(50)->notNull(),
            // หลักฐานการยอมรับเงื่อนไข
            'terms_hash' => $this->string(64)->notNull(),
            'terms_text' => $this->text()->notNull(),
            'terms_accepted_at' => $this->dateTime()->notNull(),
            'accepted_ip' => $this->string(45),
            'pdpa_consent' => $this->boolean()->notNull()->defaultValue(false),
            'status' => "ENUM('pending','approved','rejected','revoked') NOT NULL DEFAULT 'pending'",
            'reviewed_by' => $this->integer(),
            'reviewed_at' => $this->dateTime(),
            'review_note' => $this->string(500),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');
        $this->createIndex('ix_oa_user', '{{%organizer_application}}', 'user_id');
        $this->createIndex('ix_oa_status', '{{%organizer_application}}', 'status');
        $this->addForeignKey('fk_oa_user', '{{%organizer_application}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%organizer_application}}');
        $this->dropForeignKey('fk_event_owner', '{{%event}}');
        $this->dropColumn('{{%event}}', 'owner_id');
        $this->update('{{%user}}', ['role' => 'user'], ['role' => 'organizer']);
        $this->alterColumn('{{%user}}', 'role', "ENUM('user','staff','admin') NOT NULL DEFAULT 'user'");
    }
}
