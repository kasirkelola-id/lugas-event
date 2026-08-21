<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            'nama_lengkap'   => 'Administrator Pengelola',
            'nama_panggilan' => 'Admin',
            'username'       => 'pengelola',
            'password'       => password_hash('rahasia123', PASSWORD_BCRYPT),
            'no_whatsapp'    => '081234567890',
            'role_level'     => 'pengelola',
            'status_aktif'   => 1,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $this->db->table('users')->insert($data);
    }
}
