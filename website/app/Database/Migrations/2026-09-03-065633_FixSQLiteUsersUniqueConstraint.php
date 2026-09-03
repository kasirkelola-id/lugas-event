<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixSQLiteUsersUniqueConstraint extends Migration
{
    public function up()
    {
        if (ENVIRONMENT === 'testing' && $this->db->DBDriver === 'SQLite3') {
            // In SQLite, we cannot easily drop a column constraint.
            // But we can create a temporary table, copy data, drop old, and rename new.
            // Since this runs during migration up(), we might not have data yet, but just to be safe.
            $this->db->query('PRAGMA foreign_keys = OFF');
            $this->db->query('CREATE TABLE new_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_lengkap VARCHAR(255) NOT NULL,
                nama_panggilan VARCHAR(100),
                username VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                no_whatsapp VARCHAR(20),
                role_level VARCHAR(20) DEFAULT "anggota",
                status_aktif INTEGER DEFAULT 1,
                created_at DATETIME,
                updated_at DATETIME,
                karang_taruna_id INTEGER,
                rt INTEGER,
                password_must_change INTEGER DEFAULT 0,
                UNIQUE(username, karang_taruna_id)
            )');
            $this->db->query('INSERT INTO new_users SELECT * FROM users');
            $this->db->query('DROP TABLE users');
            $this->db->query('ALTER TABLE new_users RENAME TO users');
            $this->db->query('PRAGMA foreign_keys = ON');
        }
    }

    public function down()
    {
        // Don't need to revert for testing environment typically, but we can do it if needed.
    }
}
