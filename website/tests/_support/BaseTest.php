<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;

use CodeIgniter\Test\DatabaseTestTrait;

abstract class BaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrateOnce = true;
    protected $refresh = false;
    protected function setUp(): void
    {
        parent::setUp();
        
        // Only run truncation if we are using the test database
        $db = \Config\Database::connect();
        
        if ($db->DBDriver === 'SQLite3') {
            $db->disableForeignKeyChecks();
            
            $tables = $db->listTables();
            foreach ($tables as $table) {
                if ($table !== 'migrations') {
                    $db->table($table)->emptyTable();
                }
            }
            
            $db->enableForeignKeyChecks();
        }
    }
}
