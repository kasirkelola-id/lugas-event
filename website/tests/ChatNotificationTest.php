<?php

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\CIUnitTestCase;

class ChatNotificationTest extends CIUnitTestCase
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

    public function testChatNotificationFailsWithoutSecret()
    {
        $result = $this->withHeaders([])->withBody(json_encode(['chat_id' => 1]))->post('api/internal/chat-notification');
        $result->assertStatus(403);
    }

    public function testChatNotificationPrivateChat()
    {
        $db = \Config\Database::connect();
        
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
        
        // Create chat
        $db->table('chats')->insert([
            'karang_taruna_id' => $tenantId,
            'type' => 'private',
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => 'Hello private',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $chatId = $db->insertID();
        
        $result = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->withBody(json_encode(['chat_id' => $chatId]))->post('api/internal/chat-notification');
        
        $result->assertStatus(200);
        $result->assertJSONExact(['status' => true, 'message' => 'Notification processed']);
    }

    public function testChatNotificationGroupChat()
    {
        $db = \Config\Database::connect();
        
        $db->table('karang_taruna')->insert([
            'nama_organisasi' => 'KT Test Group',
            'kode_pin' => '222222'
        ]);
        $tenantId = $db->insertID();
        
        $db->table('users')->insert([
            'nama_lengkap' => 'Sender Group',
            'username' => 'sender2',
            'password' => 'pass'
        ]);
        $senderId = $db->insertID();
        
        $db->table('users')->insert([
            'nama_lengkap' => 'Receiver Group',
            'username' => 'receiver2',
            'password' => 'pass'
        ]);
        $receiverId = $db->insertID();
        
        // Add devices
        $db->table('user_devices')->insert(['user_id' => $senderId, 'fcm_token' => 'token_sender2']);
        $db->table('user_devices')->insert(['user_id' => $receiverId, 'fcm_token' => 'token_receiver2']);
        
        $db->table('chat_rooms')->insert([
            'karang_taruna_id' => $tenantId,
            'name' => 'Group Room',
            'type' => 'custom',
            'created_by' => $senderId
        ]);
        $roomId = $db->insertID();
        
        $db->table('chat_room_members')->insert(['chat_room_id' => $roomId, 'user_id' => $senderId]);
        $db->table('chat_room_members')->insert(['chat_room_id' => $roomId, 'user_id' => $receiverId]);
        
        $db->table('chats')->insert([
            'karang_taruna_id' => $tenantId,
            'type' => 'group',
            'chat_room_id' => $roomId,
            'sender_id' => $senderId,
            'message' => 'Hello group',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $chatId = $db->insertID();
        
        $result = $this->withHeaders([
            'X-Internal-Secret' => 'test_secret'
        ])->withBody(json_encode(['chat_id' => $chatId]))->post('api/internal/chat-notification');
        
        $result->assertStatus(200);
        $result->assertJSONExact(['status' => true, 'message' => 'Notification processed']);
    }
}
