<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVotingOptionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'voting_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
            'option_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('voting_id', 'votings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('voting_options', true);
    }

    public function down()
    {
        $this->forge->dropTable('voting_options', true);
    }
}
