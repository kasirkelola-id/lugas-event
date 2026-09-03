<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganizationMembersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role_level' => [
                'type'       => 'ENUM',
                'constraint' => ['superadmin', 'admin', 'ketua', 'wakil_ketua', 'sekretaris', 'bendahara', 'seksi', 'pengelola', 'anggota'],
                'default'    => 'anggota',
            ],
            'status_aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'joined_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        
        // Add foreign keys (Note: skip direct forge FK on SQLite to avoid issues, we'll use DB driver query)
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('karang_taruna_id', 'karang_taruna', 'id', 'CASCADE', 'CASCADE');
        }
        
        $this->forge->createTable('organization_members');

        // Add Unique Constraint
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE organization_members ADD UNIQUE INDEX unique_user_tenant (user_id, karang_taruna_id)");
        } else {
            $this->db->query("CREATE UNIQUE INDEX unique_user_tenant ON organization_members (user_id, karang_taruna_id)");
        }

        // ---------------------------------------------------------
        // Safe Legacy Backfill from `users` table
        // ---------------------------------------------------------
        
        // If users already exist, copy their tenant info into the new membership table
        $users = $this->db->table('users')->get()->getResultArray();
        
        $memberships = [];
        $now = date('Y-m-d H:i:s');
        
        foreach ($users as $u) {
            // Some users might have null tenant if they are superadmins, skip if so
            if (empty($u['karang_taruna_id'])) {
                continue;
            }
            
            $memberships[] = [
                'user_id' => $u['id'],
                'karang_taruna_id' => $u['karang_taruna_id'],
                // Assume standard users table originally had `role_level` and `status_aktif`
                'role_level' => $u['role_level'] ?? 'anggota',
                'status_aktif' => $u['status_aktif'] ?? 1,
                'joined_at' => $u['created_at'] ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($memberships)) {
            // Using ignore to gracefully handle any duplicates on retry
            $this->db->table('organization_members')->ignore(true)->insertBatch($memberships);
        }
    }

    public function down()
    {
        $this->forge->dropTable('organization_members');
    }
}
