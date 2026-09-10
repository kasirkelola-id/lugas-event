<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDashboardUntilToWheelSessions extends Migration
{
    public function up()
    {
        $fields = $this->db->getFieldNames('wheel_sessions');
        if (!in_array('dashboard_until', $fields)) {
            $this->forge->addColumn('wheel_sessions', [
                'dashboard_until' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'status'
                ]
            ]);
        }
        
        // Backfill legacy wheel sessions to 1 hour after creation
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query("UPDATE wheel_sessions SET dashboard_until = datetime(created_at, '+1 hours') WHERE dashboard_until IS NULL AND created_at IS NOT NULL");
        } else {
            $this->db->query("UPDATE wheel_sessions SET dashboard_until = DATE_ADD(created_at, INTERVAL 1 HOUR) WHERE dashboard_until IS NULL AND created_at IS NOT NULL");
        }
    }

    public function down()
    {
        $this->forge->dropColumn('wheel_sessions', 'dashboard_until');
    }
}
