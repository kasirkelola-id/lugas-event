<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWheelSessionsTable extends Migration
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
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_by_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'source_type' => [
                'type'       => 'ENUM',
                'constraint' => ['members', 'custom'],
                'default'    => 'members',
            ],
            'spin_duration_seconds' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 10,
            ],
            'remove_winner_after_spin' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'closed'],
                'default'    => 'active',
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
        $this->forge->addKey('karang_taruna_id');
        
        $this->forge->addForeignKey('karang_taruna_id', 'karang_taruna', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('wheel_sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('wheel_sessions', true);
    }
}
