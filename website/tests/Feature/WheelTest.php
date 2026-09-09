<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use App\Models\WheelSessionModel;
use App\Models\WheelItemModel;
use App\Models\WheelResultModel;

class WheelTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrateOnce = true;
    protected $refresh = false;
    protected $namespace = 'App';

    protected $tenantId;
    protected $userId;
    protected $memberId1;
    protected $memberId2;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup a tenant and user
        $this->db->table('karang_taruna')->insert([
            'nama_organisasi' => 'KT Wheel Test',
            'status_aktif' => 1,
            'kode_pin' => '123456',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $this->tenantId = $this->db->insertID();

        $this->db->table('users')->insert([
            'nama_lengkap' => 'Ketua Wheel',
            'username' => 'ketuawheel',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status_aktif' => 1,
            'role_level' => 'ketua',
            'karang_taruna_id' => $this->tenantId
        ]);
        $this->userId = $this->db->insertID();
        
        // Additional users for members test
        $this->db->table('users')->insert([
            'nama_lengkap' => 'Anggota Wheel 1',
            'username' => 'anggotawheel1',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status_aktif' => 1,
            'role_level' => 'anggota',
            'karang_taruna_id' => $this->tenantId
        ]);
        $this->memberId1 = $this->db->insertID();

        $this->db->table('users')->insert([
            'nama_lengkap' => 'Anggota Wheel 2',
            'username' => 'anggotawheel2',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status_aktif' => 1,
            'role_level' => 'anggota',
            'karang_taruna_id' => $this->tenantId
        ]);
        $this->memberId2 = $this->db->insertID();

        // Add to organization_members
        $this->db->table('organization_members')->insert([
            'karang_taruna_id' => $this->tenantId,
            'user_id' => $this->userId,
            'role_level' => 'ketua',
            'status_aktif' => 1,
            'username' => 'ketuawheel'
        ]);
        $this->db->table('organization_members')->insert([
            'karang_taruna_id' => $this->tenantId,
            'user_id' => $this->memberId1,
            'role_level' => 'anggota',
            'status_aktif' => 1,
            'username' => 'anggotawheel1'
        ]);
        $this->db->table('organization_members')->insert([
            'karang_taruna_id' => $this->tenantId,
            'user_id' => $this->memberId2,
            'role_level' => 'anggota',
            'status_aktif' => 1,
            'username' => 'anggotawheel2'
        ]);

        $userKetua = (new \App\Models\UserModel())->find($this->userId);
        $this->token = $this->generateTokenForUser($userKetua);
    }

    public function test_can_create_custom_wheel_session()
    {
        $payload = [
            'title' => 'Test Custom Wheel',
            'source_type' => 'custom',
            'spin_duration_seconds' => 12,
            'remove_winner_after_spin' => true,
            'items' => ['Apel', 'Jeruk', 'Mangga']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload);

        $response->assertStatus(201);
        $response->assertJSONFragment(['success' => true]);

        $body = json_decode($response->getJSON(), true);
        $sessionId = $body['session_id'];

        // Verify Database
        $this->seeInDatabase('wheel_sessions', [
            'id' => $sessionId,
            'title' => 'Test Custom Wheel',
            'source_type' => 'custom',
            'spin_duration_seconds' => 12,
            'remove_winner_after_spin' => 1
        ]);

        $this->seeNumRecords(3, 'wheel_items', ['session_id' => $sessionId]);
    }

    public function test_can_create_members_wheel_session()
    {
        $payload = [
            'title' => 'Test Members Wheel',
            'source_type' => 'members',
            'spin_duration_seconds' => 10,
            'items' => [] // empty means all active members
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload);

        $response->assertStatus(201);
        $body = json_decode($response->getJSON(), true);
        $sessionId = $body['session_id'];

        $this->seeNumRecords(3, 'wheel_items', ['session_id' => $sessionId]);
    }

    public function test_spin_logic_and_concurrency_lock()
    {
        // 1. Create Session
        $payload = [
            'title' => 'Test Spin',
            'source_type' => 'custom',
            'spin_duration_seconds' => 10,
            'items' => ['Kandidat A', 'Kandidat B']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload);

        $body = json_decode($response->getJSON(), true);
        $sessionId = $body['session_id'];

        // 2. Spin
        $responseSpin = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post("api/wheels/{$sessionId}/spin");

        $responseSpin->assertStatus(200);
        
        $this->seeNumRecords(1, 'wheel_results', ['session_id' => $sessionId]);

        // 3. Concurrent Spin should fail (409 equivalent mapped to resource exists)
        $responseConcurrent = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post("api/wheels/{$sessionId}/spin");

        $responseConcurrent->assertStatus(409); // failResourceExists
    }

    public function test_non_host_cannot_spin_or_close()
    {
        // 1. Create Session as Ketua
        $payload = [
            'title' => 'Host Test',
            'source_type' => 'custom',
            'spin_duration_seconds' => 10,
            'items' => ['Kandidat A', 'Kandidat B']
        ];

        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload);

        $sessionId = json_decode($res1->getJSON(), true)['session_id'];

        // 2. Generate token for Member 1
        $userMember = (new \App\Models\UserModel())->find($this->memberId1);
        $memberToken = $this->generateTokenForUser($userMember);

        // 3. Member tries to spin
        $resSpin = $this->withHeaders([
            'Authorization' => 'Bearer ' . $memberToken,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post("api/wheels/{$sessionId}/spin");

        $resSpin->assertStatus(403); // failForbidden

        // 4. Member tries to close
        $resClose = $this->withHeaders([
            'Authorization' => 'Bearer ' . $memberToken,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->patch("api/wheels/{$sessionId}/status");

        $resClose->assertStatus(403);
    }

    public function test_create_session_duration_validation()
    {
        // 1. Duration 9 -> Rejected
        $payload9 = [
            'title' => 'Test',
            'source_type' => 'custom',
            'spin_duration_seconds' => 9,
            'items' => ['A', 'B']
        ];
        $res9 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload9);
        $res9->assertStatus(400); // failValidationErrors

        // 2. Duration invalid string -> Casts to 0 -> Rejected
        $payloadInvalid = [
            'title' => 'Test',
            'source_type' => 'custom',
            'spin_duration_seconds' => 'abc',
            'items' => ['A', 'B']
        ];
        $resInvalid = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payloadInvalid);
        $resInvalid->assertStatus(400);

        // 3. Duration 10 -> Accepted
        $payload10 = [
            'title' => 'Test',
            'source_type' => 'custom',
            'spin_duration_seconds' => 10,
            'items' => ['A', 'B']
        ];
        $res10 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload10);
        $res10->assertStatus(201);
    }

    public function test_tenant_isolation()
    {
        // 1. Create Session for Tenant A
        $payload = [
            'title' => 'Tenant A Wheel',
            'source_type' => 'custom',
            'spin_duration_seconds' => 10,
            'items' => ['A', 'B']
        ];

        $resA = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Karang-Taruna-ID' => $this->tenantId
        ])->post('api/wheels', $payload);

        $sessionId = json_decode($resA->getJSON(), true)['session_id'];

        // 2. Setup Tenant B
        $this->db->table('karang_taruna')->insert([
            'nama_organisasi' => 'KT Wheel Test B',
            'status_aktif' => 1,
            'kode_pin' => '654321',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $tenantIdB = $this->db->insertID();

        $this->db->table('users')->insert([
            'nama_lengkap' => 'Ketua B',
            'username' => 'ketuab',
            'password' => password_hash('123456', PASSWORD_DEFAULT),
            'status_aktif' => 1,
            'role_level' => 'ketua',
            'karang_taruna_id' => $tenantIdB
        ]);
        $userIdB = $this->db->insertID();

        $userB = (new \App\Models\UserModel())->find($userIdB);
        $tokenB = $this->generateTokenForUser($userB);

        // 3. User B tries to get session details for Tenant A's session
        $resDetail = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenB,
            'X-Karang-Taruna-ID' => $tenantIdB
        ])->get("api/wheels/{$sessionId}");

        $resDetail->assertStatus(403); // Should be forbidden for Tenant B

        // 4. User B tries to spin Tenant A's session
        $resSpin = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenB,
            'X-Karang-Taruna-ID' => $tenantIdB
        ])->post("api/wheels/{$sessionId}/spin");

        $resSpin->assertStatus(403);
    }
}
