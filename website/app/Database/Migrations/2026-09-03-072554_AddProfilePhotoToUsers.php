<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfilePhotoToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'profile_photo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('users', 'profile_photo');
        }
    }
}
