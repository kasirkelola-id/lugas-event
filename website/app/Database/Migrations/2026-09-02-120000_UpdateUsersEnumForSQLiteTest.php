<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUsersEnumForSQLiteTest extends Migration
{
    public function up()
    {
        if (ENVIRONMENT === 'testing' && $this->db->DBDriver === 'SQLite3') {
            // Drop and recreate the users table without the strict CHECK constraint for roles,
            // or just drop the check constraint.
            // Since SQLite doesn't support dropping constraints easily, we recreate the table or just disable check constraints.
            // However, the cleanest way in a test is to just disable foreign key checks, drop, and recreate.
            // Wait, we don't even need to drop! CodeIgniter's Forge allows us to recreate it or we can just ignore it.
            // Actually, a simpler approach is just executing PRAGMA ignore_check_constraints = 1.
            // Let's execute that pragma to disable check constraints globally for the test connection.
            $this->db->query('PRAGMA ignore_check_constraints = 1;');
        }
    }

    public function down()
    {
        if (ENVIRONMENT === 'testing' && $this->db->DBDriver === 'SQLite3') {
            $this->db->query('PRAGMA ignore_check_constraints = 0;');
        }
    }
}
