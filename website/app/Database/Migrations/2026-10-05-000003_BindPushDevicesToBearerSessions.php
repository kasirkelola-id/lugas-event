<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class BindPushDevicesToBearerSessions extends Migration
{
    public function up()
    {
        if (!$this->forge->addColumn('user_devices', [
            'user_token_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
        ])) throw new \RuntimeException('Push session binding migration failed');
        $this->forge->addKey('user_token_id', false, false, 'push_session_binding');
        if (!$this->forge->processIndexes('user_devices')) throw new \RuntimeException('Push session index migration failed');
    }

    public function down()
    {
        throw new \RuntimeException('Removing push session binding requires an explicit privacy and rollout plan');
    }
}
