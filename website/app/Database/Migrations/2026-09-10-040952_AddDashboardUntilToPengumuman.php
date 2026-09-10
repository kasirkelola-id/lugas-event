<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDashboardUntilToPengumuman extends Migration
{
    public function up()
    {
        $fields = $this->db->getFieldNames('pengumuman');
        if (!in_array('dashboard_until', $fields)) {
            $this->forge->addColumn('pengumuman', [
                'dashboard_until' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'status_aktif'
                ]
            ]);
        }
        
        // Backfill legacy announcements to 3 days after creation
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query("UPDATE pengumuman SET dashboard_until = datetime(created_at, '+3 days') WHERE dashboard_until IS NULL AND created_at IS NOT NULL");
        } else {
            $this->db->query("UPDATE pengumuman SET dashboard_until = DATE_ADD(created_at, INTERVAL 3 DAY) WHERE dashboard_until IS NULL AND created_at IS NOT NULL");
        }
    }

    public function down()
    {
        $this->forge->dropColumn('pengumuman', 'dashboard_until');
    }
}
