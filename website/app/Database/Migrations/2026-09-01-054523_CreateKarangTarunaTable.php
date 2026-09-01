<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKarangTarunaTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama_organisasi' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
            ],
            'kode_pin' => [
                'type'       => 'VARCHAR',
                'constraint' => '6',
                'unique'     => true,
            ],
            'alamat_lengkap' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'nama_ketua' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'status_aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('karang_taruna');
    }

    public function down()
    {
        $this->forge->dropTable('karang_taruna');
    }
}
