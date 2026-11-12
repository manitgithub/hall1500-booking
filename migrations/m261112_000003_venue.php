<?php

use yii\db\Migration;

/**
 * รองรับหลายสถานที่: ผังที่นั่งแยกตามสถานที่ และการแสดงระบุสถานที่จัด
 * ข้อมูลเดิมทั้งหมดถูกย้ายไปอยู่ใต้สถานที่แรก "ห้องประชุม 1500"
 */
class m261112_000003_venue extends Migration
{
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');
        $this->createTable('{{%venue}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'location' => $this->string(255),
            'description' => $this->text(),
            // grid = สร้างผังจากหน้าแอดมิน, อื่น ๆ = ชื่อไฟล์ใน config/venues/
            'layout' => $this->string(64)->notNull()->defaultValue('grid'),
            'settings' => $this->text(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->insert('{{%venue}}', [
            'id' => 1, 'name' => 'ห้องประชุม 1500', 'location' => 'อาคารไทยบุรี มหาวิทยาลัยวลัยลักษณ์',
            'layout' => 'hall1500', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->addColumn('{{%seat}}', 'venue_id', $this->integer()->notNull()->defaultValue(1)->after('id'));
        $this->alterColumn('{{%seat}}', 'venue_id', $this->integer()->notNull());
        $this->dropIndex('ux_seat_label', '{{%seat}}');
        $this->createIndex('ux_seat_label', '{{%seat}}', ['venue_id', 'floor', 'label'], true);
        $this->addForeignKey('fk_seat_venue', '{{%seat}}', 'venue_id', '{{%venue}}', 'id');

        $this->addColumn('{{%event}}', 'venue_id', $this->integer()->notNull()->defaultValue(1)->after('id'));
        $this->alterColumn('{{%event}}', 'venue_id', $this->integer()->notNull());
        $this->addForeignKey('fk_event_venue', '{{%event}}', 'venue_id', '{{%venue}}', 'id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_event_venue', '{{%event}}');
        $this->dropColumn('{{%event}}', 'venue_id');
        $this->dropForeignKey('fk_seat_venue', '{{%seat}}');
        $this->dropIndex('ux_seat_label', '{{%seat}}');
        $this->createIndex('ux_seat_label', '{{%seat}}', ['floor', 'label'], true);
        $this->dropColumn('{{%seat}}', 'venue_id');
        $this->dropTable('{{%venue}}');
    }
}
