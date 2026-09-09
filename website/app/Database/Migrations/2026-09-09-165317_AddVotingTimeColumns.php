<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVotingTimeColumns extends Migration
{
    public function up()
    {
        $this->forge->addColumn('votings', [
            'waktu_mulai' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'waktu_selesai' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('votings', 'waktu_mulai');
        $this->forge->dropColumn('votings', 'waktu_selesai');
    }
}
