<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChatRoomsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'karang_taruna_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['default', 'custom'],
                'default'    => 'custom',
            ],
            'created_by' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('karang_taruna_id');
        $this->forge->addForeignKey('karang_taruna_id', 'karang_taruna', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('chat_rooms', true);
    }

    public function down()
    {
        $this->forge->dropTable('chat_rooms');
    }
}
