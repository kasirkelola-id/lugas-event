<?php

namespace Tests\Api;

use App\Services\NotificationService;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class PushCapture extends \App\Services\PushTransport
{
    public array $calls = [];
    public function send($tokens, $title, $body, $data = [])
    {
        $this->calls[] = ['tokens' => $tokens, 'data' => $data];
        return true;
    }
}

final class NotificationEligibilityTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
        \Config\Services::resetSingle('pushTransport');
    }

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('pushTransport');
        parent::tearDown();
    }

    private function bind(array $user, string $device): string
    {
        $token = $this->generateTokenForUser($user);
        $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => (string)$user['karang_taruna_id']])
            ->withBodyFormat('json')->post('api/fcm-token', ['fcm_token' => $device])->assertStatus(200);
        return $token;
    }

    public function test_pending_member_is_not_a_push_recipient(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->generateTokenForUser($user);
        $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'])
            ->withBodyFormat('json')->post('api/fcm-token', ['fcm_token' => 'synthetic-device'])
            ->assertStatus(200);
        $this->assertSame(['synthetic-device'], NotificationService::getTokensForTenant(101));
        $this->db->table('organization_members')->where('user_id', $user['id'])->where('karang_taruna_id', 101)
            ->update(['approval_status' => 'pending']);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
    }

    public function test_recipient_eligibility_rechecks_account_membership_tenant_and_session(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->bind($user, 'synthetic-device');
        $this->assertSame(['synthetic-device'], NotificationService::getTokensForUsers(101, [$user['id']]));
        $this->assertSame([], NotificationService::getTokensForUsers(102, [$user['id']]));
        foreach (['pending', 'rejected'] as $approval) {
            $this->db->table('organization_members')->where('user_id', $user['id'])->update(['approval_status' => $approval]);
            $this->assertSame([], NotificationService::getTokensForTenant(101));
        }
        $this->db->table('organization_members')->where('user_id', $user['id'])->update(['approval_status' => 'approved', 'status_aktif' => 0]);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
        $this->db->table('organization_members')->where('user_id', $user['id'])->update(['status_aktif' => 1]);
        foreach (['users', 'karang_taruna'] as $table) {
            $id = $table === 'users' ? $user['id'] : 101;
            $this->db->table($table)->where('id', $id)->update(['status_aktif' => 0]);
            $this->assertSame([], NotificationService::getTokensForTenant(101));
            $this->db->table($table)->where('id', $id)->update(['status_aktif' => 1]);
        }
        $this->db->table('user_tokens')->where('token_hash', hash('sha256', $token))->update(['revoked_at' => date('Y-m-d H:i:s')]);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
        $this->db->table('user_tokens')->where('token_hash', hash('sha256', $token))->update(['revoked_at' => null, 'expires_at' => '2000-01-01 00:00:00']);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
    }

    public function test_room_revalidates_members_and_announcement_role_targeting(): void
    {
        $sender = $this->createTestUser(101, 'ketua');
        $recipient = $this->createTestUser(101, 'anggota');
        $this->bind($sender, 'synthetic-sender-device');
        $this->bind($recipient, 'synthetic-recipient-device');
        $this->db->table('chat_rooms')->insert(['karang_taruna_id' => 101, 'name' => 'Synthetic', 'type' => 'custom', 'created_by' => $sender['id']]);
        $room = $this->db->insertID();
        $this->db->table('chat_room_members')->insert(['chat_room_id' => $room, 'user_id' => $recipient['id']]);
        $this->assertSame(['synthetic-recipient-device'], NotificationService::getTokensForRoom(101, $room, $sender['id']));
        $this->assertSame([], NotificationService::getTokensForRoom(102, $room, $sender['id']));
        $this->assertSame(['synthetic-sender-device'], NotificationService::getTokensForTenant(101, [], 'ketua', 'announcement.view'));
        $this->db->table('chat_room_members')->where('chat_room_id', $room)->where('user_id', $recipient['id'])->delete();
        $this->assertSame([], NotificationService::getTokensForRoom(101, $room, $sender['id']));
        $this->db->table('chat_room_members')->insert(['chat_room_id' => $room, 'user_id' => $recipient['id']]);
        $this->db->table('organization_members')->where('user_id', $recipient['id'])->update(['role_level' => 'wakil_ketua']);
        $this->assertSame([], NotificationService::getTokensForRoom(101, $room, $sender['id']));
    }

    public function test_account_switch_and_stale_logout_do_not_delete_new_binding(): void
    {
        $one = $this->createTestUser(101);
        $two = $this->createTestUser(101);
        $old = $this->bind($one, 'synthetic-shared-device');
        $new = $this->bind($two, 'synthetic-shared-device');
        $this->assertSame([], NotificationService::getTokensForUsers(101, [$one['id']]));
        $this->assertSame(['synthetic-shared-device'], NotificationService::getTokensForUsers(101, [$two['id']]));
        $this->withHeaders($this->getAuthHeaders($old))->withBodyFormat('json')->delete('api/fcm-token', ['fcm_token' => 'synthetic-shared-device'])->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($old))->post('api/logout')->assertStatus(200);
        $this->assertSame(['synthetic-shared-device'], NotificationService::getTokensForUsers(101, [$two['id']]));
        $this->withHeaders($this->getAuthHeaders($new))->post('api/logout')->assertStatus(200);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
        $this->assertSame(0, $this->db->table('user_devices')->countAllResults());
    }

    public function test_logout_cleans_own_binding_after_tenant_and_account_revocation(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->bind($user, 'synthetic-revoked-device');
        $this->db->table('karang_taruna')->where('id', 101)->update(['status_aktif' => 0]);
        $this->db->table('users')->where('id', $user['id'])->update(['status_aktif' => 0]);
        $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '999'])->get('api/me')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '999'])->post('api/logout')->assertStatus(200);
        $this->assertSame(0, $this->db->table('user_devices')->countAllResults());
        $row = $this->db->table('user_tokens')->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        $this->assertNotNull($row['revoked_at']);
    }

    public function test_legacy_unbound_device_is_suppressed_until_registration(): void
    {
        $user = $this->createTestUser(101);
        $this->db->table('user_devices')->insert(['user_id' => (string)$user['id'], 'fcm_token' => 'synthetic-legacy-device']);
        $this->assertSame([], NotificationService::getTokensForTenant(101));
        $this->bind($user, 'synthetic-legacy-device');
        $this->assertSame(['synthetic-legacy-device'], NotificationService::getTokensForTenant(101));
        $this->assertSame(1, $this->db->table('user_devices')->countAllResults());
    }

    public function test_rest_and_internal_chat_payload_uses_persisted_tenant(): void
    {
        $sender = $this->createTestUser(101, 'ketua');
        $recipient = $this->createTestUser(101);
        $senderToken = $this->generateTokenForUser($sender);
        $this->bind($recipient, 'synthetic-recipient-device');
        $probe = new PushCapture();
        \Config\Services::injectMock('pushTransport', $probe);
        $response = $this->withHeaders($this->getAuthHeaders($senderToken) + ['X-Karang-Taruna-ID' => '101'])
            ->withBodyFormat('json')->post('api/chats/messages', ['type' => 'private', 'receiver_id' => $recipient['id'],
                'message' => 'Synthetic', 'tenant_id' => 999]);
        $response->assertStatus(200);
        $this->assertCount(1, $probe->calls);
        $this->assertSame('101', $probe->calls[0]['data']['tenant_id']);
        $chat = json_decode($response->getJSON(), true)['data'];
        $old = getenv('INTERNAL_API_SECRET');
        putenv('INTERNAL_API_SECRET=synthetic-internal-value');
        try {
            $this->withHeaders(['X-Internal-Secret' => 'synthetic-internal-value'])->withBodyFormat('json')
                ->post('api/internal/chat-notification', ['chat_id' => $chat['id'], 'tenant_id' => 999])->assertStatus(200);
            $this->assertSame('101', $probe->calls[1]['data']['tenant_id']);
            $this->assertSame((string)$chat['id'], $probe->calls[1]['data']['chat_id']);
        } finally {
            putenv($old === false ? 'INTERNAL_API_SECRET' : 'INTERNAL_API_SECRET=' . $old);
        }
    }

    public function test_logout_device_failure_rolls_back_token_revocation(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->bind($user, 'synthetic-device');
        $beforeToken = $this->db->table('user_tokens')->get()->getResultArray();
        $beforeDevice = $this->db->table('user_devices')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER logout_device_error BEFORE DELETE ON user_devices BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $response = $this->withHeaders($this->getAuthHeaders($token))->post('api/logout');
            $response->assertStatus(503);
            $this->assertStringNotContainsString('synthetic-private-detail', $response->getJSON());
            $this->assertSame($beforeToken, $this->db->table('user_tokens')->get()->getResultArray());
            $this->assertSame($beforeDevice, $this->db->table('user_devices')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER logout_device_error');
            $this->db->resetTransStatus();
        }
    }
}
