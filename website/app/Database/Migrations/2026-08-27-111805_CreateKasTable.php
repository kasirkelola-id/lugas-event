<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKasTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'jenis'       => [
                'type'       => 'ENUM',
                'constraint' => ['pemasukan', 'pengeluaran'],
                'default'    => 'pemasukan',
            ],
            'nominal'     => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'keterangan'  => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'tanggal'     => [
                'type'       => 'DATE',
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at'  => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at'  => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('dibuat_oleh', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('kas');
    }

    public function down()
    {
        $this->forge->dropTable('kas');
    }
}
