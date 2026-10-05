<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Preserve legacy global-user authors; browser authors belong to superadmins. */
final class AddSuperadminAnnouncementAuthor extends Migration
{
    public function up()
    {
        if ($this->db->DBPrefix !== '') throw new \RuntimeException('Announcement author migration requires reviewed unprefixed schema');
        if ($this->db->fieldExists('dibuat_oleh_superadmin', 'pengumuman')) {
            $this->verify();
            return;
        }
        if ($this->db->DBDriver === 'MySQLi') {
            // One atomic MySQL8 DDL statement, no existing row/author rewrites.
            $this->checked('ALTER TABLE pengumuman MODIFY dibuat_oleh INT UNSIGNED NULL DEFAULT NULL,
                ADD dibuat_oleh_superadmin INT UNSIGNED NULL DEFAULT NULL,
                ADD CONSTRAINT pengumuman_superadmin_author_fk FOREIGN KEY (dibuat_oleh_superadmin)
                REFERENCES superadmins(id) ON UPDATE CASCADE ON DELETE RESTRICT');
        } elseif ($this->db->DBDriver === 'SQLite3') {
            $this->sqlite();
        } else throw new \RuntimeException('Announcement author migration supports MySQLi and SQLite3');
        $this->db->resetDataCache();
        $this->verify();
    }

    private function sqlite(): void
    {
        if ($this->db->transDepth !== 0) throw new \RuntimeException('Announcement rebuild requires its own transaction');
        // Refuse an unknown incoming dependency before a SQLite table replacement.
        foreach ($this->db->listTables() as $table) {
            foreach ($this->db->query('PRAGMA foreign_key_list(' . $this->db->escapeIdentifiers($table) . ')')->getResultArray() as $fk) {
                if ($fk['table'] === 'pengumuman') throw new \RuntimeException('Review incoming announcement foreign keys before rebuild');
            }
        }
        $ddl = $this->db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'pengumuman'")->getRowArray()['sql'];
        $count = 0;
        $ddl = preg_replace('/([`"]?dibuat_oleh[`"]?\s+[^,\n]*?)\s+NOT NULL\b/i', '$1', $ddl, 1, $count);
        if ($count !== 1) throw new \RuntimeException('Unexpected legacy announcement author definition');
        $ddl = preg_replace('/\ACREATE TABLE\s+[`"]?pengumuman[`"]?/i', 'CREATE TABLE pengumuman_author_forward_new', $ddl, 1, $count);
        if ($count !== 1) throw new \RuntimeException('Unexpected announcement table definition');
        $start = strpos($ddl, '(');
        if ($start === false) throw new \RuntimeException('Invalid announcement table definition');
        // SQLite requires columns before table constraints, unlike appending after
        // the existing FK definitions. Keep their original definitions intact.
        $ddl = substr($ddl, 0, $start + 1) . ' dibuat_oleh_superadmin INTEGER NULL REFERENCES superadmins(id) ON UPDATE CASCADE ON DELETE RESTRICT,' . substr($ddl, $start + 1);
        $objects = $this->db->query("SELECT sql FROM sqlite_master WHERE tbl_name = 'pengumuman' AND type IN ('index', 'trigger') AND sql IS NOT NULL ORDER BY type, name")->getResultArray();
        $columns = implode(',', array_map(fn($name) => $this->db->escapeIdentifiers($name), $this->db->getFieldNames('pengumuman')));
        $sequence = $this->db->query("SELECT seq FROM sqlite_sequence WHERE name = 'pengumuman'")->getRowArray()['seq'] ?? 0;
        // Historical SQLite compatibility attempts leave a sticky status outside
        // a transaction. This migration owns a new transaction; every new write
        // and the final transaction status are checked, never an inherited scope.
        $this->db->resetTransStatus();
        if (!$this->db->transBegin()) throw new \RuntimeException('Announcement rebuild transaction unavailable');
        $committed = false;
        try {
            $this->checked($ddl); // Plain CREATE; no adoption of an existing staging table.
            $this->checked("INSERT INTO pengumuman_author_forward_new ($columns) SELECT $columns FROM pengumuman");
            $this->checked('DROP TABLE pengumuman');
            $this->checked('ALTER TABLE pengumuman_author_forward_new RENAME TO pengumuman');
            foreach ($objects as $object) $this->checked($object['sql']);
            $this->checked("UPDATE sqlite_sequence SET seq = MAX(seq, " . (int)$sequence . ") WHERE name = 'pengumuman'");
            $this->db->resetDataCache();
            $this->verify();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Announcement rebuild commit failed');
            $committed = true;
        } finally {
            if (!$committed && $this->db->transDepth > 0) $this->db->transRollback();
        }
    }

    private function verify(): void
    {
        $fields = $this->db->getFieldData('pengumuman');
        $authors = array_filter($fields, static fn($field) => in_array($field->name, ['dibuat_oleh', 'dibuat_oleh_superadmin'], true) && $field->nullable);
        if (count($authors) !== 2) throw new \RuntimeException('Announcement authors must be nullable independent identities');
        $found = false;
        foreach ($this->db->getForeignKeyData('pengumuman') as $fk) {
            if ($fk->column_name === ['dibuat_oleh_superadmin'] && $fk->foreign_table_name === 'superadmins'
                && $fk->foreign_column_name === ['id'] && $fk->on_delete === 'RESTRICT' && $fk->on_update === 'CASCADE') $found = true;
        }
        if (!$found) throw new \RuntimeException('Announcement superadmin author foreign key missing');
    }

    private function checked(string $sql): void
    {
        if ($this->db->query($sql) === false) throw new \RuntimeException('Announcement author schema write failed');
    }

    public function down()
    {
        throw new \RuntimeException('Announcement authors cannot be mapped losslessly to global users; roll forward or use a verified restore');
    }
}
