<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWheelResultsTable extends Migration
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
            'session_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'wheel_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'result_label_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'spin_sequence' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'started_at' => [
                'type' => 'DATETIME',
            ],
            'duration_seconds' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('session_id');
        
        $this->forge->addForeignKey('session_id', 'wheel_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('wheel_item_id', 'wheel_items', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('wheel_results', true);
    }

    public function down()
    {
        $this->forge->dropTable('wheel_results', true);
    }
}
