<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPasswordMustChangeToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'password_must_change' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'null' => false,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('users', 'password_must_change');
        }
    }
}
