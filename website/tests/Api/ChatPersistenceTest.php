<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;
use App\Services\ChatPersistenceService;

final class ChatPersistenceTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';
    private const ID = '01234567-89ab-4cde-8f01-23456789abcd';

    private function payload(array $sender, array $receiver): array
    {
        return ['karang_taruna_id' => $sender['karang_taruna_id'], 'sender_id' => $sender['id'],
            'receiver_id' => $receiver['id'], 'type' => 'private', 'message' => 'Synthetic',
            'client_message_id' => self::ID, 'created_at' => gmdate('Y-m-d H:i:s')];
    }

    public function test_lost_response_retry_returns_exact_stored_row(): void
    {
        $sender = $this->createTestUser(101, 'ketua');
        $receiver = $this->createTestUser(101);
        $first = ChatPersistenceService::persist($this->payload($sender, $receiver));
        $retry = ChatPersistenceService::persist($this->payload($sender, $receiver));
        $this->assertTrue($first['created']);
        $this->assertFalse($retry['created']);
        $this->assertSame($first['row'], $retry['row']);
        $this->assertSame(1, $this->db->table('chats')->countAllResults());
    }

    public function test_same_id_different_sender_and_tenant_are_independent(): void
    {
        $a = $this->createTestUser(101);
        $b = $this->createTestUser(101);
        $c = $this->createTestUser(102);
        foreach ([[$a, $b], [$b, $a], [$c, $c]] as [$sender, $receiver]) {
            $this->assertTrue(ChatPersistenceService::persist($this->payload($sender, $receiver))['created']);
        }
        $this->assertSame(3, $this->db->table('chats')->countAllResults());
    }

    public function test_conflicting_payload_does_not_overwrite_existing_message(): void
    {
        $a = $this->createTestUser(101);
        $b = $this->createTestUser(101);
        $data = $this->payload($a, $b);
        $first = ChatPersistenceService::persist($data);
        try {
            ChatPersistenceService::persist(array_replace($data, ['message' => 'Different']));
            $this->fail('Conflicting reuse must fail');
        } catch (\DomainException $error) {
            $this->assertSame($first['row'], $this->db->table('chats')->get()->getRowArray());
        }
    }

    public function test_api_replay_checks_current_permissions_and_rejects_conflict(): void
    {
        $a = $this->createTestUser(101, 'ketua');
        $b = $this->createTestUser(101);
        $token = $this->generateTokenForUser($a);
        $request = ['type' => 'private', 'receiver_id' => $b['id'], 'message' => 'Synthetic', 'client_message_id' => self::ID];
        $send = fn(array $body) => $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'])
            ->withBodyFormat('json')->post('api/chats/messages', $body);
        $first = $send($request); $first->assertStatus(200);
        $retry = $send($request); $retry->assertStatus(200);
        $this->assertSame(json_decode($first->getJSON(), true)['data'], json_decode($retry->getJSON(), true)['data']);
        $send(array_replace($request, ['message' => 'Different']))->assertStatus(409);
        $send(array_replace($request, ['client_message_id' => ['bad']]))->assertStatus(400);
        $this->db->table('organization_members')->where('user_id', $a['id'])->update(['role_level' => 'wakil_ketua']);
        $send($request)->assertStatus(403);
        $this->assertSame(1, $this->db->table('chats')->countAllResults());
    }

    public function test_failed_insert_never_reports_success_or_notifies(): void
    {
        $a = $this->createTestUser(101, 'ketua');
        $b = $this->createTestUser(101);
        $token = $this->generateTokenForUser($a);
        $probe = $this->getMockBuilder(\App\Services\PushTransport::class)->onlyMethods(['send'])->getMock();
        $probe->expects($this->never())->method('send');
        \Config\Services::injectMock('pushTransport', $probe);
        $this->db->query("CREATE TRIGGER chat_insert_failure BEFORE INSERT ON chats BEGIN SELECT RAISE(ABORT, 'synthetic-private'); END");
        try {
            $response = $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'])->withBodyFormat('json')
                ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => $b['id'], 'message' => 'Synthetic', 'client_message_id' => self::ID]);
            $response->assertStatus(500);
            $this->assertStringNotContainsString('synthetic-private', $response->getJSON());
            $this->assertSame(0, $this->db->table('chats')->countAllResults());
        } finally {
            $this->db->query('DROP TRIGGER chat_insert_failure');
            \Config\Services::resetSingle('pushTransport');
            $this->db->resetTransStatus();
        }
    }

    public function test_notification_observes_persisted_row_and_retry_does_not_dispatch_twice(): void
    {
        $a = $this->createTestUser(101, 'ketua');
        $b = $this->createTestUser(101);
        $senderToken = $this->generateTokenForUser($a);
        $receiverToken = $this->generateTokenForUser($b);
        $this->withHeaders($this->getAuthHeaders($receiverToken) + ['X-Karang-Taruna-ID' => '101'])->withBodyFormat('json')
            ->post('api/fcm-token', ['fcm_token' => 'synthetic-chat-device'])->assertStatus(200);
        $probe = $this->getMockBuilder(\App\Services\PushTransport::class)->onlyMethods(['send'])->getMock();
        $probe->expects($this->once())->method('send')->willReturnCallback(function () {
            $this->assertSame(1, $this->db->table('chats')->countAllResults());
            $this->assertSame(0, $this->db->transDepth);
            return true;
        });
        \Config\Services::injectMock('pushTransport', $probe);
        try {
            for ($i = 0; $i < 2; $i++) {
                $this->withHeaders($this->getAuthHeaders($senderToken) + ['X-Karang-Taruna-ID' => '101'])->withBodyFormat('json')
                    ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => $b['id'], 'message' => 'Synthetic', 'client_message_id' => self::ID])->assertStatus(200);
            }
        } finally {
            \Config\Services::resetSingle('pushTransport');
        }
    }
}
