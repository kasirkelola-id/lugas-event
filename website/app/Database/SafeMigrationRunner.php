<?php

namespace App\Database;

class SafeMigrationRunner extends \CodeIgniter\Database\MigrationRunner
{
    protected function migrate($direction, $migration): bool
    {
        // No historical file edit: stop before include/instance/up/down executes.
        if (strcasecmp(ltrim($migration->class, '\\'), 'App\\Database\\Migrations\\AddTenantIdToAllTables') === 0
            && ENVIRONMENT !== 'testing') {
            throw new \RuntimeException('Historical destructive tenant migration is disabled outside disposable tests. Review migration history and a non-destructive baseline/backfill on an isolated restore.');
        }
        return parent::migrate($direction, $migration);
    }
}
