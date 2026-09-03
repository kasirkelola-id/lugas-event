<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLogoToKarangTaruna extends Migration
{
    public function up()
    {
        $this->forge->addColumn('karang_taruna', [
            'logo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('karang_taruna', 'logo_path');
        }
    }
}
