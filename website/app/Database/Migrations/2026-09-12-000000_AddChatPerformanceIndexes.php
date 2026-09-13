<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddChatPerformanceIndexes extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE `chats` ADD INDEX `idx_chats_created_at` (`created_at`)");
            $this->db->query("ALTER TABLE `chats` ADD INDEX `idx_chats_room_id` (`chat_room_id`, `id`)");
            $this->db->query("ALTER TABLE `chats` ADD INDEX `idx_chats_private_contacts` (`karang_taruna_id`, `type`, `sender_id`, `receiver_id`, `id`)");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE `chats` DROP INDEX `idx_chats_created_at`");
            $this->db->query("ALTER TABLE `chats` DROP INDEX `idx_chats_room_id`");
            $this->db->query("ALTER TABLE `chats` DROP INDEX `idx_chats_private_contacts`");
        }
    }
}
