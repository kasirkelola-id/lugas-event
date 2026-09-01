<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGpsToEvents extends Migration
{
    public function up()
    {
        $fields = [
            'require_gps' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
            'latitude' => [
                'type'       => 'DOUBLE',
                'null'       => true,
            ],
            'longitude' => [
                'type'       => 'DOUBLE',
                'null'       => true,
            ],
            'radius' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
        ];
        $this->forge->addColumn('events', $fields);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('events', ['require_gps', 'latitude', 'longitude', 'radius']);
        }
    }
}
