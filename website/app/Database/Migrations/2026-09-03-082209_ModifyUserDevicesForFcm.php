<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifyUserDevicesForFcm extends Migration
{
    public function up()
    {
        // Change fcm_token from TEXT to VARCHAR(255) first so we can add unique index
        $this->forge->modifyColumn('user_devices', [
            'fcm_token' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ]
        ]);

        if ($this->db->DBDriver !== 'SQLite3') {
            // Drop the composite key that included karang_taruna_id
            $this->forge->dropKey('user_devices', 'user_id_karang_taruna_id');
        }
        
        $this->forge->dropColumn('user_devices', 'karang_taruna_id');
        
        // Add new indexes
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('CREATE UNIQUE INDEX fcm_token_unique ON user_devices (fcm_token)');
            $this->db->query('CREATE INDEX user_id_index ON user_devices (user_id)');
        } else {
            $this->db->query('ALTER TABLE user_devices ADD UNIQUE INDEX fcm_token_unique (fcm_token)');
            $this->db->query('ALTER TABLE user_devices ADD INDEX user_id_index (user_id)');
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX fcm_token_unique');
            $this->db->query('DROP INDEX user_id_index');
        } else {
            $this->db->query('ALTER TABLE user_devices DROP INDEX fcm_token_unique');
            $this->db->query('ALTER TABLE user_devices DROP INDEX user_id_index');
        }
        
        $this->forge->addColumn('user_devices', [
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ]
        ]);
        
        $this->forge->modifyColumn('user_devices', [
            'fcm_token' => [
                'type' => 'TEXT',
            ]
        ]);
        
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('CREATE INDEX user_id_kt_index ON user_devices (user_id, karang_taruna_id)');
        } else {
            $this->db->query('ALTER TABLE user_devices ADD INDEX user_id_kt_index (user_id, karang_taruna_id)');
        }
    }
}
