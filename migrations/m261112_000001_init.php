<?php

use yii\db\Migration;

/**
 * โครงสร้างฐานข้อมูลระบบจองที่นั่งห้องประชุม 1500
 */
class m261112_000001_init extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'line_user_id' => $this->string(64)->unique(),
            'display_name' => $this->string(255)->notNull(),
            'picture_url' => $this->string(500),
            'email' => $this->string(255),
            'phone' => $this->string(20),
            'role' => "ENUM('user','staff','admin') NOT NULL DEFAULT 'user'",
            'auth_key' => $this->string(32)->notNull(),
            'last_login_at' => $this->dateTime(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);

        // ที่นั่งทางกายภาพของห้อง (สร้างจาก config/hall1500.php)
        $this->createTable('{{%seat}}', [
            'id' => $this->primaryKey(),
            'floor' => $this->tinyInteger()->notNull(),
            'section' => $this->string(8)->notNull(),
            'row_label' => $this->string(4)->notNull(),
            'row_index' => $this->smallInteger()->notNull(),
            'seat_number' => $this->smallInteger()->notNull(),
            'label' => $this->string(16)->notNull(),
            'zone_code' => $this->string(16)->notNull(),
            'pos_x' => $this->decimal(8, 2)->notNull(),
            'pos_y' => $this->decimal(8, 2)->notNull(),
            'is_wheelchair' => $this->boolean()->notNull()->defaultValue(false),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
        ], $opt);
        $this->createIndex('ux_seat_label', '{{%seat}}', ['floor', 'label'], true);
        $this->createIndex('ix_seat_zone', '{{%seat}}', 'zone_code');

        $this->createTable('{{%event}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'category' => $this->string(50),
            'description' => $this->text(),
            'poster' => $this->string(255),
            'organizer' => $this->string(255),
            'show_start' => $this->dateTime()->notNull(),
            'show_end' => $this->dateTime(),
            'sale_start' => $this->dateTime(),
            'sale_end' => $this->dateTime(),
            'status' => "ENUM('draft','published','closed','cancelled') NOT NULL DEFAULT 'draft'",
            'max_seats_per_booking' => $this->smallInteger()->notNull()->defaultValue(10),
            'hold_minutes' => $this->smallInteger()->notNull()->defaultValue(15),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);

        $this->createTable('{{%event_zone_price}}', [
            'event_id' => $this->integer()->notNull(),
            'zone_code' => $this->string(16)->notNull(),
            'price' => $this->decimal(10, 2)->notNull(),
            'is_open' => $this->boolean()->notNull()->defaultValue(true),
        ], $opt);
        $this->addPrimaryKey('pk_ezp', '{{%event_zone_price}}', ['event_id', 'zone_code']);
        $this->addForeignKey('fk_ezp_event', '{{%event_zone_price}}', 'event_id', '{{%event}}', 'id', 'CASCADE');

        $this->createTable('{{%booking}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(20)->notNull()->unique(),
            'event_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'status' => "ENUM('pending','verifying','paid','cancelled','expired','refunded') NOT NULL DEFAULT 'pending'",
            'total_amount' => $this->decimal(10, 2)->notNull()->defaultValue(0),
            'seat_count' => $this->smallInteger()->notNull()->defaultValue(0),
            'expires_at' => $this->dateTime(),
            'paid_at' => $this->dateTime(),
            'ticket_token' => $this->string(64)->unique(),
            'checked_in_at' => $this->dateTime(),
            'checked_in_by' => $this->integer(),
            'note' => $this->string(500),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->createIndex('ix_booking_event_status', '{{%booking}}', ['event_id', 'status']);
        $this->createIndex('ix_booking_user', '{{%booking}}', 'user_id');
        $this->addForeignKey('fk_booking_event', '{{%booking}}', 'event_id', '{{%event}}', 'id');
        $this->addForeignKey('fk_booking_user', '{{%booking}}', 'user_id', '{{%user}}', 'id');

        // สถานะที่นั่งต่อรอบการแสดง (1 แถว = 1 ที่นั่ง ใน 1 event)
        // การล็อกที่นั่งใช้ UPDATE ... WHERE status='available' แบบ atomic
        $this->createTable('{{%event_seat}}', [
            'event_id' => $this->integer()->notNull(),
            'seat_id' => $this->integer()->notNull(),
            'status' => "ENUM('available','held','sold','blocked') NOT NULL DEFAULT 'available'",
            'price' => $this->decimal(10, 2)->notNull(),
            'booking_id' => $this->integer(),
            'held_until' => $this->dateTime(),
        ], $opt);
        $this->addPrimaryKey('pk_event_seat', '{{%event_seat}}', ['event_id', 'seat_id']);
        $this->createIndex('ix_es_booking', '{{%event_seat}}', 'booking_id');
        $this->createIndex('ix_es_hold', '{{%event_seat}}', ['status', 'held_until']);
        $this->addForeignKey('fk_es_event', '{{%event_seat}}', 'event_id', '{{%event}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_es_seat', '{{%event_seat}}', 'seat_id', '{{%seat}}', 'id');

        // ประวัติที่นั่งของแต่ละ booking (คงอยู่แม้ booking หมดอายุ)
        $this->createTable('{{%booking_seat}}', [
            'booking_id' => $this->integer()->notNull(),
            'seat_id' => $this->integer()->notNull(),
            'price' => $this->decimal(10, 2)->notNull(),
        ], $opt);
        $this->addPrimaryKey('pk_booking_seat', '{{%booking_seat}}', ['booking_id', 'seat_id']);
        $this->addForeignKey('fk_bs_booking', '{{%booking_seat}}', 'booking_id', '{{%booking}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_bs_seat', '{{%booking_seat}}', 'seat_id', '{{%seat}}', 'id');

        $this->createTable('{{%payment}}', [
            'id' => $this->primaryKey(),
            'booking_id' => $this->integer()->notNull(),
            'method' => "ENUM('promptpay_slip','omise_promptpay','omise_card','cash') NOT NULL",
            'amount' => $this->decimal(10, 2)->notNull(),
            'status' => "ENUM('pending','success','failed','rejected') NOT NULL DEFAULT 'pending'",
            'gateway_ref' => $this->string(100),
            'slip_path' => $this->string(255),
            'transferred_at' => $this->dateTime(),
            'raw_response' => $this->text(),
            'verified_by' => $this->integer(),
            'verified_at' => $this->dateTime(),
            'note' => $this->string(500),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->createIndex('ix_payment_booking', '{{%payment}}', 'booking_id');
        $this->createIndex('ix_payment_ref', '{{%payment}}', 'gateway_ref');
        $this->createIndex('ix_payment_status', '{{%payment}}', ['status', 'method']);
        $this->addForeignKey('fk_payment_booking', '{{%payment}}', 'booking_id', '{{%booking}}', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        foreach (['payment', 'booking_seat', 'event_seat', 'booking', 'event_zone_price', 'event', 'seat', 'user'] as $t) {
            $this->dropTable("{{%$t}}");
        }
    }
}
