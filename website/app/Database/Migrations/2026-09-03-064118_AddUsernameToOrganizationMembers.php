<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsernameToOrganizationMembers extends Migration
{
    public function up()
    {
        // 1. Add username column
        $this->forge->addColumn('organization_members', [
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true, // initially true for safe backfill
                'after'      => 'karang_taruna_id',
            ],
        ]);

        // 2. Safe Backfill: UPDATE organization_members JOIN users
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("
                UPDATE organization_members om
                JOIN users u ON u.id = om.user_id
                SET om.username = u.username
            ");
        } else {
            // SQLite update with join workaround
            $this->db->query("
                UPDATE organization_members
                SET username = (
                    SELECT username 
                    FROM users 
                    WHERE users.id = organization_members.user_id
                )
            ");
        }

        // 3. Add Unique Constraint
        if ($this->db->DBDriver !== 'SQLite3') {
            // First drop any potential duplicate index if it was previously created
            try {
                $this->db->query("ALTER TABLE organization_members DROP INDEX unique_karang_taruna_username");
            } catch (\Exception $e) {
                // Ignore if it doesn't exist
            }
            $this->db->query("ALTER TABLE organization_members ADD UNIQUE INDEX unique_karang_taruna_username (karang_taruna_id, username)");
        } else {
            $this->db->query("CREATE UNIQUE INDEX unique_karang_taruna_username ON organization_members (karang_taruna_id, username)");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("ALTER TABLE organization_members DROP INDEX unique_karang_taruna_username");
        } else {
            $this->db->query("DROP INDEX unique_karang_taruna_username");
        }

        $this->forge->dropColumn('organization_members', 'username');
    }
}
