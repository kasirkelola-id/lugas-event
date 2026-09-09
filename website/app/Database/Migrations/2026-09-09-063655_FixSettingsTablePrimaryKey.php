<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSettingsTablePrimaryKey extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            // Remove the old primary key
            $this->db->query("ALTER TABLE settings DROP PRIMARY KEY");
            
            // Add a new auto-increment primary key 'id'
            $this->db->query("ALTER TABLE settings ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            
            // Add a unique constraint on (setting_key, karang_taruna_id)
            $this->db->query("ALTER TABLE settings ADD UNIQUE INDEX unique_setting_tenant (setting_key, karang_taruna_id)");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE settings DROP INDEX unique_setting_tenant");
            $this->db->query("ALTER TABLE settings DROP COLUMN id");
            $this->db->query("ALTER TABLE settings ADD PRIMARY KEY (setting_key)");
        }
    }
}
