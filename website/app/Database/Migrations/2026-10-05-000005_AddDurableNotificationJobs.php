<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Shared outbox: PHP and Node domain writes enqueue in the same DB statement. */
final class AddDurableNotificationJobs extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $tables = $this->db->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('chats','events','pengumuman','inventory_loans')")->getResultArray();
            if (count($tables) !== 4 || count(array_filter($tables, static fn($table) => strcasecmp($table['ENGINE'], 'InnoDB') === 0)) !== 4) {
                throw new \RuntimeException('Durable notifications require all domain tables to use InnoDB; review the schema before migration');
            }
        }
        $id = ['type' => 'INT', 'unsigned' => true];
        $time = ['type' => 'DATETIME', 'null' => true, 'default' => null];
        $this->forge->addField([
            'id' => $id + ['auto_increment' => true],
            'job_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 20],
            'entity_id' => $id, 'karang_taruna_id' => $id,
            'action' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'created'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'attempts' => $id + ['default' => 0], 'cursor_device_id' => $id + ['default' => 0],
            'expanded' => ['type' => 'TINYINT', 'default' => 0],
            'fanout_done' => ['type' => 'TINYINT', 'default' => 1],
            'fanout_attempts' => $id + ['default' => 0], 'fanout_next_attempt_at' => $time,
            'lease_token' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'lease_expires_at' => $time, 'next_attempt_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'], 'completed_at' => $time,
            'last_error' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('job_key');
        $this->forge->addKey(['status', 'next_attempt_at', 'id']);
        $attributes = $this->db->DBDriver === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : [];
        if (!$this->forge->createTable('notification_jobs', false, $attributes)) throw new \RuntimeException('Notification jobs creation failed');
        $this->forge->addField([
            'id' => $id + ['auto_increment' => true], 'job_id' => $id, 'device_id' => $id,
            'registration_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'attempts' => $id + ['default' => 0], 'next_attempt_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'], 'completed_at' => $time,
            'last_error' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['job_id', 'device_id', 'registration_hash']);
        $this->forge->addKey(['job_id', 'status', 'next_attempt_at', 'id']);
        $this->forge->addForeignKey('job_id', 'notification_jobs', 'id', 'CASCADE', 'CASCADE');
        if (!$this->forge->createTable('notification_deliveries', false, $attributes)) throw new \RuntimeException('Notification deliveries creation failed');

        foreach (['chats' => 'chat', 'events' => 'event', 'pengumuman' => 'announcement'] as $table => $kind) {
            $condition = $kind === 'announcement' ? 'NEW.status_aktif = 1' : ($kind === 'event' ? "NEW.status_aktif IN ('aktif', '1')" : '1 = 1');
            $now = $this->db->DBDriver === 'MySQLi' ? 'UTC_TIMESTAMP()' : "datetime('now')";
            $key = $this->db->DBDriver === 'MySQLi' ? "CONCAT('$kind:', NEW.id)" : "'$kind:' || NEW.id";
            $statement = "INSERT INTO notification_jobs (job_key,kind,entity_id,karang_taruna_id,fanout_done,next_attempt_at,created_at,updated_at) SELECT $key,'$kind',NEW.id,NEW.karang_taruna_id," . ($kind === 'chat' ? '0' : '1') . ",$now,$now,$now WHERE $condition;";
            $this->trigger('enqueue_' . $kind . '_notification', 'INSERT', $table, $statement);
        }
        $now = $this->db->DBDriver === 'MySQLi' ? 'UTC_TIMESTAMP()' : "datetime('now')";
        $key = $this->db->DBDriver === 'MySQLi' ? "CONCAT('loan:', NEW.id, ':', NEW.status)" : "'loan:' || NEW.id || ':' || NEW.status";
        $this->trigger('enqueue_loan_notification', 'UPDATE', 'inventory_loans',
            "INSERT INTO notification_jobs (job_key,kind,entity_id,karang_taruna_id,action,next_attempt_at,created_at,updated_at) SELECT $key,'loan',NEW.id,i.karang_taruna_id,NEW.status,$now,$now,$now FROM inventories i WHERE i.id = NEW.inventory_id AND NEW.status <> OLD.status;");
    }

    private function trigger(string $name, string $event, string $table, string $statement): void
    {
        $body = $this->db->DBDriver === 'MySQLi' ? 'FOR EACH ROW BEGIN ' . $statement . ' END' : 'BEGIN ' . $statement . ' END';
        if (!$this->db->query("CREATE TRIGGER $name AFTER $event ON $table $body")) throw new \RuntimeException('Notification outbox trigger failed');
    }

    public function down()
    {
        throw new \RuntimeException('Removing durable notification jobs requires an explicit drain and rollout plan');
    }
}
