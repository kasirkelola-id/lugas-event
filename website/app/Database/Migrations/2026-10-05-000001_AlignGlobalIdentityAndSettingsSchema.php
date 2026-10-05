<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Forward-only: preserves legacy data while allowing global identity writes. */
class AlignGlobalIdentityAndSettingsSchema extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            // Keep values, tenant FK and existing unique/index definitions. New
            // identities no longer need a legacy organization authority field.
            if (!$this->forge->modifyColumn('users', [
                'karang_taruna_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            ])) {
                throw new \RuntimeException('Could not make the legacy user tenant nullable');
            }
            // MySQL settings already has id + unique(key,tenant) from 2026-09-09.
            return;
        }
        if ($this->db->DBDriver !== 'SQLite3') {
            throw new \RuntimeException('Schema alignment supports MySQLi and SQLite3 only');
        }
        // The historical SQLite users rebuild already permits NULL tenant IDs.
        // Settings was excluded from the historical MySQL primary-key repair.
        if ($this->db->fieldExists('id', 'settings')) {
            $this->checked('CREATE UNIQUE INDEX IF NOT EXISTS unique_setting_tenant ON settings (setting_key, karang_taruna_id)');
            return;
        }
        if ($this->db->transDepth !== 0 || !$this->db->transBegin()) {
            throw new \RuntimeException('Settings schema rebuild requires its own transaction');
        }
        $committed = false;
        try {
            // No truncation, filtering, or UPDATE of existing values. Every
            // statement is checked, including with DBDebug=false in test setup.
            $this->checked('CREATE TABLE settings_batch4_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                description VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME,
                karang_taruna_id INTEGER NOT NULL,
                UNIQUE (setting_key, karang_taruna_id)
            )');
            $this->checked('INSERT INTO settings_batch4_new
                (setting_key, setting_value, description, created_at, updated_at, karang_taruna_id)
                SELECT setting_key, setting_value, description, created_at, updated_at, karang_taruna_id FROM settings');
            $this->checked('DROP TABLE settings');
            $this->checked('ALTER TABLE settings_batch4_new RENAME TO settings');
            if (!$this->db->transCommit()) {
                throw new \RuntimeException('Settings schema rebuild commit failed');
            }
            $committed = true;
        } finally {
            if (!$committed && $this->db->transDepth > 0) {
                $this->db->transRollback();
            }
        }
    }

    public function down()
    {
        // Requiring legacy tenants again cannot represent newly global users;
        // a key-only settings PK cannot represent the same key in many tenants.
        // Refuse a lossy automatic rollback instead of inventing tenant values.
        throw new \RuntimeException('Forward-only schema alignment: restore an independently verified backup or roll forward');
    }

    private function checked(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            throw new \RuntimeException('Settings schema rebuild failed');
        }
    }
}
