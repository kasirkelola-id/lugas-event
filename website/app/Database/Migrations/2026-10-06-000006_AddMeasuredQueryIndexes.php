<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Supported by the disposable MySQL8 cash/queue query plans, not speculative indexes. */
final class AddMeasuredQueryIndexes extends Migration
{
    private const INDEXES = [
        'kas' => ['idx_kas_tenant_page' => ['karang_taruna_id' => false, 'tanggal' => true, 'created_at' => true, 'id' => false]],
        'notification_jobs' => ['idx_notification_expired_lease' => ['status' => false, 'lease_expires_at' => false, 'id' => false]],
        'wheel_results' => ['idx_wheel_results_page' => ['session_id' => false, 'spin_sequence' => true, 'id' => false]],
    ];

    public function up()
    {
        if (!in_array($this->db->DBDriver, ['MySQLi', 'SQLite3'], true)) throw new \RuntimeException('Unsupported measured index driver');
        $pending = [];
        foreach (self::INDEXES as $table => $definitions) {
            $existing = $this->definitions($table);
            foreach ($definitions as $name => $columns) {
                if (isset($existing[$name]) && $existing[$name] !== $columns) throw new \RuntimeException('Measured index name conflicts with existing schema');
                // Respect an equivalent operator-created index, including sort directions.
                if (!in_array($columns, $existing, true)) $pending[] = [$table, $name, $columns];
            }
        }
        foreach ($pending as [$table, $name, $columns]) {
            $parts = [];
            foreach ($columns as $column => $descending) $parts[] = '`' . $column . '` ' . ($descending ? 'DESC' : 'ASC');
            if (!$this->db->query('CREATE INDEX `' . $name . '` ON `' . $table . '` (' . implode(', ', $parts) . ')')) throw new \RuntimeException('Measured index creation failed');
        }
    }

    private function definitions(string $table): array
    {
        $indexes = [];
        if ($this->db->DBDriver === 'MySQLi') {
            foreach ($this->db->query('SHOW INDEX FROM `' . $table . '`')->getResultArray() as $row) {
                // Functional/prefix indexes do not prove an equivalent full-column index.
                $column = $row['Column_name'] ?? null;
                if ($column === null || $row['Sub_part'] !== null) $column = '__non_equivalent_' . $row['Seq_in_index'];
                $indexes[$row['Key_name']][$column] = $row['Collation'] === 'D';
            }
        } else {
            foreach ($this->db->query('PRAGMA index_list(`' . $table . '`)')->getResultArray() as $index) {
                if ((int)$index['partial']) continue;
                $columns = [];
                $quoted = $this->db->escape($index['name']);
                foreach ($this->db->query('PRAGMA index_xinfo(' . $quoted . ')')->getResultArray() as $column) {
                    if ((int)$column['key']) $columns[$column['name'] ?? '__expression'] = (bool)$column['desc'];
                }
                $indexes[$index['name']] = $columns;
            }
        }
        return $indexes;
    }

    public function down()
    {
        // An equivalent pre-existing index may have been reused. Never remove it automatically.
        throw new \RuntimeException('Measured index removal requires explicit ownership and query-plan review');
    }
}
