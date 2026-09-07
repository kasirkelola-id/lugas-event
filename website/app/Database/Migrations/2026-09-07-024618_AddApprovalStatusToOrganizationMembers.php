<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApprovalStatusToOrganizationMembers extends Migration
{
    public function up()
    {
        $fields = [
            'approval_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'approved', 'rejected'],
                'default'    => 'approved',
                'after'      => 'role_level',
            ],
        ];
        $this->forge->addColumn('organization_members', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('organization_members', 'approval_status');
    }
}
