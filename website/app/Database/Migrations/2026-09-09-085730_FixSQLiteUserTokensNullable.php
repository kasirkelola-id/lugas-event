<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSQLiteUserTokensNullable extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys=off');
            $this->db->query('CREATE TABLE user_tokens_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                karang_taruna_id INTEGER NULL,
                token_hash VARCHAR(255) UNIQUE,
                expires_at DATETIME NOT NULL,
                created_at DATETIME,
                revoked_at DATETIME
            )');
            $this->db->query('INSERT INTO user_tokens_new (id, user_id, karang_taruna_id, token_hash, expires_at, created_at, revoked_at)
                              SELECT id, user_id, karang_taruna_id, token_hash, expires_at, created_at, revoked_at FROM user_tokens');
            $this->db->query('DROP TABLE user_tokens');
            $this->db->query('ALTER TABLE user_tokens_new RENAME TO user_tokens');
            $this->db->query('PRAGMA foreign_keys=on');
        }
    }

    public function down()
    {
        // Not reversible
    }
}
