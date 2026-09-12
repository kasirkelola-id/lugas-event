<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\UserModel;
use App\Models\ChatRoomModel;
use App\Models\ChatRoomMemberModel;
use App\Models\ChatModel;
use App\Models\UserTokenModel;
use App\Services\AuthService;

class ChatTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrateOnce = false;
    protected $refresh = false;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();

        // Clean tables
        $db->table('chats')->emptyTable();
        $db->table('chat_room_members')->emptyTable();
        $db->table('chat_rooms')->emptyTable();
        $db->table('user_tokens')->emptyTable();
        $db->table('organization_members')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();

        // Setup Tenant 1 and 2
        $db->table('karang_taruna')->insert(['id' => 1, 'nama_organisasi' => 'KT Satu', 'kode_pin' => '123']);
        $db->table('karang_taruna')->insert(['id' => 2, 'nama_organisasi' => 'KT Dua', 'kode_pin' => '456']);

        // Setup Users
        $db->table('users')->insert(['id' => 10, 'nama_lengkap' => 'User T1 A', 'username' => 'u1a', 'password' => '123']);
        $db->table('users')->insert(['id' => 11, 'nama_lengkap' => 'User T1 B', 'username' => 'u1b', 'password' => '123']);
        $db->table('users')->insert(['id' => 20, 'nama_lengkap' => 'User T2 A', 'username' => 'u2a', 'password' => '123']);

        // Setup Memberships
        $db->table('organization_members')->insert(['user_id' => 10, 'karang_taruna_id' => 1, 'status_aktif' => 1, 'role_level' => 'admin']);
        $db->table('organization_members')->insert(['user_id' => 11, 'karang_taruna_id' => 1, 'status_aktif' => 1, 'role_level' => 'anggota']);
        $db->table('organization_members')->insert(['user_id' => 20, 'karang_taruna_id' => 2, 'status_aktif' => 1, 'role_level' => 'anggota']);

        // Setup Default Rooms
        $db->table('chat_rooms')->insert(['id' => 100, 'karang_taruna_id' => 1, 'name' => 'Forum T1', 'type' => 'default']);
        $db->table('chat_rooms')->insert(['id' => 200, 'karang_taruna_id' => 2, 'name' => 'Forum T2', 'type' => 'default']);

        // Setup Custom Rooms for T1
        $db->table('chat_rooms')->insert(['id' => 101, 'karang_taruna_id' => 1, 'name' => 'Custom T1', 'type' => 'custom']);
        $db->table('chat_room_members')->insert(['chat_room_id' => 101, 'user_id' => 10]); // User T1 A is member, T1 B is not
    }

    private function getAuthHeader($userId, $tenantId)
    {
        $token = "fake_jwt_token_{$userId}_{$tenantId}";
        $db = \Config\Database::connect();
        $db->table('user_tokens')->insert([
            'user_id' => $userId,
            'karang_taruna_id' => $tenantId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        return $token;
    }

    public function testDefaultGroupDeletionProtected()
    {
        $token = $this->getAuthHeader(10, 1);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Karang-Taruna-ID' => '1'
        ])->delete('api/chats/rooms/100');

        $result->assertStatus(403); // Controller returns 403 for default room deletion
        $this->assertStringContainsString('Grup default tidak dapat dihapus', $result->getJSON());
    }

    public function testTenantIsolationCannotReadOtherTenantGroup()
    {
        $token = $this->getAuthHeader(10, 1);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Karang-Taruna-ID' => '1'
        ])->get('api/chats/rooms/200/messages');

        $result->assertStatus(404);
    }

    public function testCustomGroupAccessAllowedForMember()
    {
        $token = $this->getAuthHeader(10, 1);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Karang-Taruna-ID' => '1'
        ])->get('api/chats/rooms/101/messages');

        $result->assertStatus(200);
    }

    public function testCustomGroupAccessDeniedForNonMember()
    {
        $token = $this->getAuthHeader(11, 1);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Karang-Taruna-ID' => '1'
        ])->get('api/chats/rooms/101/messages');

        $result->assertStatus(403);
    }

    public function testActiveTenantMemberCanReadDefaultGroup()
    {
        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/rooms/100/messages');
        $result->assertStatus(200);
    }

    public function testPrivateChatInsertAndRead()
    {
        // A -> B
        $db = \Config\Database::connect();
        $db->table('chats')->insert([
            'karang_taruna_id' => 1,
            'type' => 'private',
            'sender_id' => 10, // A
            'message' => 'Hello B',
            'receiver_id' => 11, // B
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $tokenA = $this->getAuthHeader(10, 1);
        $resultA = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/private/11');
        $resultA->assertStatus(200);
        $this->assertStringContainsString('Hello B', $resultA->getJSON());

        // B -> A should read same history
        $tokenB = $this->getAuthHeader(11, 1);
        $resultB = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenB, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/private/10');
        $resultB->assertStatus(200);
        $this->assertStringContainsString('Hello B', $resultB->getJSON());

        // getPrivateChatContacts A
        $contactsA = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/private-contacts');
        $contactsA->assertStatus(200);
        $this->assertStringContainsString('User T1 B', $contactsA->getJSON());
    }

    public function testPrivateTenantIsolation()
    {
        $db = \Config\Database::connect();
        $db->table('chats')->insert([
            'karang_taruna_id' => 1,
            'type' => 'private',
            'sender_id' => 10,
            'message' => 'Hello B',
            'receiver_id' => 11,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // User T2 A (id=20) trying to read private chat of User 11 or 10 in Tenant 1
        $tokenT2 = $this->getAuthHeader(20, 2);

        // Attempt to get contact list
        $contactsT2 = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenT2, 'X-Karang-Taruna-ID' => '2'])
            ->get('api/chats/private-contacts');
        $contactsT2->assertStatus(200);
        $this->assertStringNotContainsString('User T1 A', $contactsT2->getJSON());

        // Attempt to read private chat with user 10
        $privateT2 = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenT2, 'X-Karang-Taruna-ID' => '2'])
            ->get('api/chats/private/10');
        $privateT2->assertStatus(404);
        $this->assertStringContainsString('Pengguna tidak ditemukan', $privateT2->getJSON());
    }

    public function testCustomGroupSendDeniedForNonMember()
    {
        $token = $this->getAuthHeader(11, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', [
                'type' => 'group',
                'chat_room_id' => 101,
                'message' => 'Hello custom group'
            ]);
        $result->assertStatus(403);
        $this->assertStringContainsString('Anda bukan anggota grup ini', $result->getJSON());
    }

    public function testTimestampCanonicalFormat()
    {
        $db = \Config\Database::connect();
        $db->table('chats')->insert([
            'karang_taruna_id' => 1,
            'type' => 'private',
            'sender_id' => 10,
            'message' => 'Timestamp Test',
            'receiver_id' => 11,
            'created_at' => '2026-09-12 10:15:30' // UTC assumed
        ]);

        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/private/11');

        $result->assertStatus(200);
        $this->assertStringContainsString('2026-09-12T10:15:30Z', $result->getJSON());
    }

    public function testInactiveMemberPrivateContact()
    {
        // Add inactive user
        $db = \Config\Database::connect();
        $db->table('users')->insert(['id' => 12, 'nama_lengkap' => 'User Inactive', 'username' => 'u12', 'password' => '123']);
        $db->table('organization_members')->insert(['user_id' => 12, 'karang_taruna_id' => 1, 'status_aktif' => 0, 'role_level' => 'anggota']);

        $db->table('chats')->insert([
            'karang_taruna_id' => 1,
            'type' => 'private',
            'sender_id' => 12,
            'message' => 'Hello A',
            'receiver_id' => 10,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->get('api/chats/private-contacts');

        $result->assertStatus(200);
        // Contact list should NOT contain User Inactive because status_aktif = 0
        $this->assertStringNotContainsString('User Inactive', $result->getJSON());
    }

    // NEW SECURITY TESTS

    public function testPrivateCrossTenantRejectedAndNoInsert()
    {
        $token = $this->getAuthHeader(10, 1); // User T1 A
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => 20, 'message' => 'secret']); // User T2 A
        $result->assertStatus(404);
        $this->dontSeeInDatabase('chats', ['sender_id' => 10, 'receiver_id' => 20]);
    }

    public function testPrivateInactiveReceiverRejectedAndNoInsert()
    {
        $db = \Config\Database::connect();
        $db->table('users')->insert(['id' => 13, 'nama_lengkap' => 'Inactive', 'username' => 'u13', 'password' => '123']);
        $db->table('organization_members')->insert(['user_id' => 13, 'karang_taruna_id' => 1, 'status_aktif' => 0, 'role_level' => 'anggota']);

        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => 13, 'message' => 'secret']);
        $result->assertStatus(404);
        $this->dontSeeInDatabase('chats', ['sender_id' => 10, 'receiver_id' => 13]);
    }

    public function testPrivateNonexistentReceiverRejectedAndNoInsert()
    {
        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => 999, 'message' => 'secret']);
        $result->assertStatus(404);
        $this->dontSeeInDatabase('chats', ['sender_id' => 10, 'receiver_id' => 999]);
    }

    public function testCustomGroupCreateRejectsForeignMemberAtomically()
    {
        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/rooms', ['name' => 'Bad Group', 'type' => 'custom', 'members' => [11, 20]]); // 20 is foreign
        $result->assertStatus(400);
        $this->dontSeeInDatabase('chat_rooms', ['name' => 'Bad Group']);
        // Check that room member was not inserted for 11 either
        $this->dontSeeInDatabase('chat_room_members', ['user_id' => 11]);
    }

    public function testCustomGroupCreateRejectsInactiveMemberAtomically()
    {
        $db = \Config\Database::connect();
        $db->table('users')->insert(['id' => 14, 'nama_lengkap' => 'Inactive', 'username' => 'u14', 'password' => '123']);
        $db->table('organization_members')->insert(['user_id' => 14, 'karang_taruna_id' => 1, 'status_aktif' => 0, 'role_level' => 'anggota']);

        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/rooms', ['name' => 'Bad Group 2', 'type' => 'custom', 'members' => [11, 14]]);
        $result->assertStatus(400);
        $this->dontSeeInDatabase('chat_rooms', ['name' => 'Bad Group 2']);
    }

    public function testGroupSendCustomNonMemberRejectedAndNoInsert()
    {
        $token = $this->getAuthHeader(11, 1); // Not in room 101
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'group', 'chat_room_id' => 101, 'message' => 'secret']);
        $result->assertStatus(403);
        $this->dontSeeInDatabase('chats', ['sender_id' => 11, 'chat_room_id' => 101]);
    }

    public function testGroupSendRevokedMemberRejectedAndNoInsert()
    {
        $db = \Config\Database::connect();
        $db->table('organization_members')->where('user_id', 11)->update(['status_aktif' => 0]); // Revoke 11

        $token = $this->getAuthHeader(11, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'group', 'chat_room_id' => 100, 'message' => 'secret']);
        $result->assertStatus(403);
        $this->dontSeeInDatabase('chats', ['sender_id' => 11, 'chat_room_id' => 100]);
    }

    public function testGroupSendOtherTenantRoomRejectedAndNoInsert()
    {
        $token = $this->getAuthHeader(10, 1);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '1'])
            ->post('api/chats/messages', ['type' => 'group', 'chat_room_id' => 200, 'message' => 'secret']); // Room 200 is T2
        $result->assertStatus(404);
        $this->dontSeeInDatabase('chats', ['sender_id' => 10, 'chat_room_id' => 200]);
    }
}
