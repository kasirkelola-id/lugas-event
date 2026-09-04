<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSettingsTableFk extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            // Hapus foreign key agar kita bisa menggunakan karang_taruna_id = 0 untuk setting global superadmin
            try {
                $this->db->query("ALTER TABLE settings DROP FOREIGN KEY fk_settings_tenant");
            } catch (\Exception $e) {
                // Abaikan jika FK tidak ada
            }
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE settings ADD CONSTRAINT fk_settings_tenant FOREIGN KEY (karang_taruna_id) REFERENCES karang_taruna(id) ON DELETE CASCADE");
        }
    }
}
