<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCheckOutToAbsensiTable extends Migration
{
    public function up()
    {
        $fields = [
            'waktu_checkout' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'waktu_absen'
            ],
        ];
        $this->forge->addColumn('absensi', $fields);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('absensi', 'waktu_checkout');
        }
    }
}
