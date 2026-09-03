<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVotingVotesTable extends Migration
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
            'option_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('voting_id', 'votings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('option_id', 'voting_options', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        // A user can only vote once per voting
        $this->forge->addUniqueKey(['voting_id', 'user_id']);
        $this->forge->createTable('voting_votes', true);
    }

    public function down()
    {
        $this->forge->dropTable('voting_votes', true);
    }
}
