<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddChatIdempotency extends Migration
{
    public function up()
    {
        if (!$this->forge->addColumn('chats', [
            'client_message_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true, 'default' => null],
        ])) throw new \RuntimeException('Chat idempotency column failed');
        $this->forge->addUniqueKey(['karang_taruna_id', 'sender_id', 'client_message_id'], 'chat_logical_send');
        if (!$this->forge->processIndexes('chats')) throw new \RuntimeException('Chat idempotency index failed');
    }

    public function down()
    {
        throw new \RuntimeException('Removing chat idempotency requires an explicit delivery rollout plan');
    }
}
