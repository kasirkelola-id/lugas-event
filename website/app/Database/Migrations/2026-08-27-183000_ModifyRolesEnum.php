<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifyRolesEnum extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            // Modify users table role_level
            $this->db->query("ALTER TABLE `users` MODIFY COLUMN `role_level` ENUM('admin', 'ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota') NOT NULL DEFAULT 'anggota'");

            // Modify pengumuman table target_role
            $this->db->query("ALTER TABLE `pengumuman` MODIFY COLUMN `target_role` ENUM('semua', 'admin', 'ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota') NOT NULL DEFAULT 'semua'");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            // Revert users table role_level (will fail if there are existing rows with the new roles)
            $this->db->query("ALTER TABLE `users` MODIFY COLUMN `role_level` ENUM('admin', 'pengelola', 'anggota') NOT NULL DEFAULT 'anggota'");

            // Revert pengumuman table target_role
            $this->db->query("ALTER TABLE `pengumuman` MODIFY COLUMN `target_role` ENUM('semua', 'pengelola', 'anggota') NOT NULL DEFAULT 'semua'");
        }
    }
}
