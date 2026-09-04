<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddKelurahanToKarangTaruna extends Migration
{
    public function up()
    {
        $fields = [
            'kelurahan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ];

        $this->forge->addColumn('karang_taruna', $fields);

        // Add Foreign Key (Manual query since CodeIgniter 4 Forge doesn't easily support adding FK to existing tables without constraints issues sometimes)
        $this->db->query('ALTER TABLE `karang_taruna` ADD CONSTRAINT `fk_kt_kelurahan` FOREIGN KEY (`kelurahan_id`) REFERENCES `kelurahan`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `karang_taruna` DROP FOREIGN KEY `fk_kt_kelurahan`');
        $this->forge->dropColumn('karang_taruna', 'kelurahan_id');
    }
}
