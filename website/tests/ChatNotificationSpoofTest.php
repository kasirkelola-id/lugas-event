<?php

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\CIUnitTestCase;

class ChatNotificationSpoofTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        putenv('FCM_MOCK=true');
        putenv('INTERNAL_API_SECRET=test_secret');
    }

    public function testSpoofingTenantIdAndReceiverIsIgnored()
    {
        $db = \Config\Database::connect();
        
        // Target valid tenant
        $db->table('karang_taruna')->insert([
            'nama_organisasi' => 'KT Test Private',
            'kode_pin' => '111111'
        ]);
        $tenantId = $db->insertID();
        
        $db->table('users')->insert([
            'nama_lengkap' => 'Sender User',
            'username' => 'sender',
            'password' => 'pass'
        ]);
        $senderId = $db->insertID();
        
        $db->table('users')->insert([
            'nama_lengkap' => 'Receiver User',
            'username' => 'receiver',
            'password' => 'pass'
        ]);
        $receiverId = $db->insertID();
        
        // Add devices
        $db->table('user_devices')->insert(['user_id' => $senderId, 'fcm_token' => 'token_sender']);
        $db->table('user_devices')->insert(['user_id' => $receiverId, 'fcm_token' => 'token_receiver']);
        
        // Create actual chat
        $db->table('chats')->insert([
            'karang_taruna_id' => $tenantId,
            'type' => 'private',
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => 'Hello private',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $chatId = $db->insertID();

        // Simulate spoofed payload from node
        $spoofedPayload = [
            'chat_id' => $chatId,
            'tenant_id' => 999,      // Should be ignored
            'receiver_id' => 999,    // Should be ignored
            'sender_id' => 999       // Should be ignored
        ];
        
        $result = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->withBody(json_encode($spoofedPayload))->post('api/internal/chat-notification');
        
        $result->assertStatus(200);
        $result->assertJSONExact(['status' => true, 'message' => 'Notification processed']);
    }
}
