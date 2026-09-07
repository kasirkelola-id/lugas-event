<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class InsertSuperadminDummyUser extends Migration
{
    public function up()
    {
        if (ENVIRONMENT !== 'testing' || $this->db->DBDriver !== 'SQLite3') {
            return;
        }

        // Insert Dummy Tenant 0
        $this->db->table('karang_taruna')->ignore(true)->insert([
            'id' => 0,
            'nama_organisasi' => 'Sistem Utama',
            'kode_pin' => '000000',
            'alamat_lengkap' => 'Sistem',
            'status_aktif' => 0
        ]);

        // Insert Dummy User 0
        $this->db->table('users')->ignore(true)->insert([
            'id' => 0,
            'karang_taruna_id' => 0,
            'nama_lengkap' => 'Superadmin System',
            'nama_panggilan' => 'System',
            'username' => 'superadmin_sys',
            'password' => '',
            'no_whatsapp' => '000',
            'rt' => 1,
            'role_level' => 'superadmin',
            'status_aktif' => 1
        ]);
    }

    public function down()
    {
        if (ENVIRONMENT !== 'testing' || $this->db->DBDriver !== 'SQLite3') {
            return;
        }
        
        $this->db->table('users')->where('id', 0)->delete();
        $this->db->table('karang_taruna')->where('id', 0)->delete();
    }
}
