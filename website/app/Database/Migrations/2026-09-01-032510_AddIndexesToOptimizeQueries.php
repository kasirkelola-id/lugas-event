<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIndexesToOptimizeQueries extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            // Add index on absensi
            $this->db->query("CREATE INDEX idx_absensi_user_event ON absensi (user_id, event_id)");
            $this->db->query("CREATE INDEX idx_absensi_waktu ON absensi (waktu_absen, waktu_checkout)");

            // Add index on events
            $this->db->query("CREATE INDEX idx_events_status_tanggal ON events (status_aktif, tanggal_acara)");
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query("DROP INDEX idx_absensi_user_event ON absensi");
            $this->db->query("DROP INDEX idx_absensi_waktu ON absensi");
            $this->db->query("DROP INDEX idx_events_status_tanggal ON events");
        }
    }
}
