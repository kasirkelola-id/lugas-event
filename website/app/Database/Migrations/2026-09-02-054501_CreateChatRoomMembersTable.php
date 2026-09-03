<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatRoomMembersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'chat_room_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
        ]);
        
        $this->forge->addKey(['chat_room_id', 'user_id'], true);
        $this->forge->addForeignKey('chat_room_id', 'chat_rooms', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('chat_room_members', true);
    }

    public function down()
    {
        $this->forge->dropTable('chat_room_members');
    }
}
