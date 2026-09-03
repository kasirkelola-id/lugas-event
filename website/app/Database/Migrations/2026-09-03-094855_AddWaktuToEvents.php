<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWaktuToEvents extends Migration
{
    public function up()
    {
        $fields = [
            'waktu_mulai' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'waktu_selesai' => [
                'type' => 'TIME',
                'null' => true,
            ],
        ];
        $this->forge->addColumn('events', $fields);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('events', ['waktu_mulai', 'waktu_selesai']);
        }
    }
}
