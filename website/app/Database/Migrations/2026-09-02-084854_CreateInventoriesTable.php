<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoriesTable extends Migration
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
                'constraint' => '255',
            ],
            'total_quantity' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 1,
            ],
            'available_quantity' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 1,
            ],
            'condition' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
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
        $this->forge->addForeignKey('karang_taruna_id', 'karang_taruna', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventories', true);
    }

    public function down()
    {
        $this->forge->dropTable('inventories', true);
    }
}
