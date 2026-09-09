<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWheelItemsTable extends Migration
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
            'member_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'label_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('session_id');
        
        $this->forge->addForeignKey('session_id', 'wheel_sessions', 'id', 'CASCADE', 'CASCADE');
        // We don't necessarily cascade delete items if a user is deleted, we just set null, 
        // but for MVP cascade delete or RESTRICT is fine. Let's use SET NULL so history is preserved.
        // Wait, MySQL SET NULL requires the column to be nullable, which it is.
        $this->forge->addForeignKey('member_user_id', 'users', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('wheel_items', true);
    }

    public function down()
    {
        $this->forge->dropTable('wheel_items', true);
    }
}
