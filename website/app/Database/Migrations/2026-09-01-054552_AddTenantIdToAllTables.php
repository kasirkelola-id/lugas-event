<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTenantIdToAllTables extends Migration
{
    public function up()
    {
        $tables = ['users', 'events', 'absensi', 'pengumuman', 'kas', 'settings', 'user_tokens', 'event_participants'];

        // Truncate tables to avoid foreign key / unique constraint errors on existing dummy data
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($tables as $table) {
            $this->db->table($table)->truncate();
        }

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        }

        $fields = [
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ]
        ];

        // Add karang_taruna_id to all tables
        foreach ($tables as $table) {
            $this->forge->addColumn($table, $fields);
        }

        // Add foreign keys
        // Note: SQLite doesn't support ADD FOREIGN KEY via ALTER TABLE directly in Forge easily without rebuilding the table,
        // so for SQLite tests, we skip foreign key creation and just use the column.
        if ($this->db->DBDriver !== 'SQLite3') {
            foreach ($tables as $table) {
                $this->db->query("ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_tenant FOREIGN KEY (karang_taruna_id) REFERENCES karang_taruna(id) ON DELETE CASCADE");
            }

            // Update unique constraint on users table
            $this->db->query("ALTER TABLE users DROP INDEX username");
            $this->db->query("ALTER TABLE users ADD UNIQUE INDEX unique_username_tenant (username, karang_taruna_id)");
        }
    }

    public function down()
    {
        $tables = ['users', 'events', 'absensi', 'pengumuman', 'kas', 'settings', 'user_tokens', 'event_participants'];

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE users DROP INDEX unique_username_tenant");
            $this->db->query("ALTER TABLE users ADD UNIQUE INDEX username (username)");

            foreach ($tables as $table) {
                $this->db->query("ALTER TABLE {$table} DROP FOREIGN KEY fk_{$table}_tenant");
            }
        }

        foreach ($tables as $table) {
            if ($this->db->DBDriver !== 'SQLite3') {
                $this->forge->dropColumn($table, 'karang_taruna_id');
            }
        }
    }
}
