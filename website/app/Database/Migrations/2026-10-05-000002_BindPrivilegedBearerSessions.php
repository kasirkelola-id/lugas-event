<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Additive identity binding; unbound historical privileged tokens fail closed. */
final class BindPrivilegedBearerSessions extends Migration
{
    public function up()
    {
        if (!$this->forge->addColumn('user_tokens', [
            'superadmin_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'credential_version' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'default' => null],
        ])) throw new \RuntimeException('Privileged bearer binding migration failed');
    }

    public function down()
    {
        throw new \RuntimeException('Removing privileged session bindings requires an explicit session revocation/rollout plan');
    }
}
