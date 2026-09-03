<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifyChatsTableForRooms extends Migration
{
    public function up()
    {
        $this->forge->addColumn('chats', [
            'chat_room_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'karang_taruna_id',
            ],
        ]);
        
        // Add foreign key constraint for chat_room_id
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE `chats` ADD CONSTRAINT `chats_chat_room_id_foreign` FOREIGN KEY (`chat_room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE `chats` DROP FOREIGN KEY `chats_chat_room_id_foreign`");
        }
        $this->forge->dropColumn('chats', 'chat_room_id');
    }
}
