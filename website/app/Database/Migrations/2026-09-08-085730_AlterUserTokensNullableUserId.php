<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterUserTokensNullableUserId extends Migration
{
    public function up()
    {
        // 1. Drop the foreign key (name depends on when it was created, usually table_column_foreign)
        // Since we are dealing with SQLite in some tests but MySQL in prod, we check driver
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropForeignKey('user_tokens', 'user_tokens_user_id_foreign');
        }

        // 2. Modify column to allow NULL
        $fields = [
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ];

        $this->forge->modifyColumn('user_tokens', $fields);

        // 3. Re-add foreign key constraint with cascade
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->processIndexes('user_tokens');
        }
    }

    public function down()
    {
        // Not easily reversible if there are null values
    }
}
