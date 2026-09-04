<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDefaultChatRooms extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        $karangTaruna = $db->table('karang_taruna')->get()->getResultArray();
        
        foreach ($karangTaruna as $kt) {
            $existing = $db->table('chat_rooms')
                           ->where('karang_taruna_id', $kt['id'])
                           ->where('type', 'default')
                           ->get()
                           ->getRowArray();
                           
            if (!$existing) {
                $db->table('chat_rooms')->insert([
                    'karang_taruna_id' => $kt['id'],
                    'name' => 'Forum ' . $kt['nama_organisasi'],
                    'type' => 'default',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public function down()
    {
        // No down needed for data migration
    }
}
