<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMembershipApprovalHistoryTable extends Migration
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
            'organization_member_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'karang_taruna_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'action' => [
                'type'       => 'ENUM',
                'constraint' => ['approved', 'rejected'],
            ],
            'actor_user_id' => [
                'type'       => 'INT', // Int because superadmin or user id
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true, // Might be 0 or null for system/superadmin, but let's make it null for safety
            ],
            'actor_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'user', // user, superadmin
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('organization_member_id');
        $this->forge->addKey('karang_taruna_id');
        
        // Ensure no foreign key on actor_user_id since it might be superadmin or user
        $this->forge->addForeignKey('organization_member_id', 'organization_members', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('karang_taruna_id', 'karang_taruna', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('membership_approval_history');
    }

    public function down()
    {
        $this->forge->dropTable('membership_approval_history');
    }
}
