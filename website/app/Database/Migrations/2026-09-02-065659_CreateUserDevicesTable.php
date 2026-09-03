<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserDevicesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true, // null for superadmins? or just keep it
            ],
            'fcm_token' => [
                'type'       => 'TEXT',
            ],
            'device_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'karang_taruna_id']);
        $this->forge->createTable('user_devices');
    }

    public function down()
    {
        $this->forge->dropTable('user_devices');
    }
}
