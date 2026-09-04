<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateOrganizationMembersRoles extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE organization_members MODIFY COLUMN role_level ENUM('superadmin', 'admin', 'ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'wakil_bendahara', 'seksi', 'pengelola', 'anggota') DEFAULT 'anggota'");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            // Note: Data loss might occur if rolling back with wakil_sekretaris or wakil_bendahara users
            $this->db->query("ALTER TABLE organization_members MODIFY COLUMN role_level ENUM('superadmin', 'admin', 'ketua', 'wakil_ketua', 'sekretaris', 'bendahara', 'seksi', 'pengelola', 'anggota') DEFAULT 'anggota'");
        }
    }
}
