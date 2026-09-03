<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLocationToAbsensi extends Migration
{
    public function up()
    {
        $fields = [
            'latitude' => [
                'type' => 'DOUBLE',
                'null' => true,
            ],
            'longitude' => [
                'type' => 'DOUBLE',
                'null' => true,
            ],
            'accuracy' => [
                'type' => 'DOUBLE',
                'null' => true,
            ],
            'distance_m' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
        ];
        $this->forge->addColumn('absensi', $fields);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('absensi', ['latitude', 'longitude', 'accuracy', 'distance_m']);
        }
    }
}
