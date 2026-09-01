<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRtToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'rt' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'default'    => 1,
                'null'       => false,
                'after'      => 'no_whatsapp',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('users', 'rt');
        }
    }
}
