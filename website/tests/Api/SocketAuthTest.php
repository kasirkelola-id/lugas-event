<?php

namespace Tests\Api;

use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\AuthTrait;

class SocketAuthTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use AuthTrait;
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testInternalAuthWithValidTokenAndMembership()
    {
        $db = \Config\Database::connect();
        $tenantId = 100;
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantId, 'nama_organisasi' => "KT Socket Test", 'kode_pin' => '123123', 'status_aktif' => 1]);

        $user = $this->createTestUser($tenantId, 'ketua', 'ketua_socket');
        
        $token = $this->generateTokenForUser($user);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Karang-Taruna-ID' => (string)$tenantId,
            'X-Internal-Secret' => getenv('INTERNAL_API_SECRET') ?: 'default_internal_secret_for_dev'
        ])->post('/api/internal/socket-auth');

        $result->assertStatus(200);
        $result->assertJSONExact([
            'status' => true,
            'message' => 'Authenticated',
            'data' => [
                'user_id' => $user['id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'karang_taruna_id' => $tenantId,
                'role_level' => 'ketua',
                'permissions' => config('Rbac')->permissions['ketua'],
                'profile_photo_url' => null,
            ]
        ]);
    }

    public function testInternalAuthRejectsWithoutSecretOrLocalhost()
    {
        $db = \Config\Database::connect();
        $tenantId = 101;
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantId, 'nama_organisasi' => "KT Socket Test", 'kode_pin' => '123123', 'status_aktif' => 1]);

        $user = $this->createTestUser($tenantId, 'ketua', 'ketua_socket2');
        
        $token = $this->generateTokenForUser($user);

        // Since we cannot easily fake IP internally via FeatureTestTrait directly in some setups without server globals,
        // we omit the X-Internal-Secret to simulate an external attempt missing the secret.
        // Assuming the test runner's IP might be '127.0.0.1' which bypasses it. Wait, if IP is 127.0.0.1, it allows it.
        // Let's modify the $_SERVER['REMOTE_ADDR'] explicitly.
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Karang-Taruna-ID' => $tenantId,
            'X-Internal-Secret' => 'wrong-secret'
        ])->post('/api/internal/socket-auth');

        $result->assertStatus(403);
        $result->assertJSONExact([
            'status' => false,
            'message' => 'Forbidden: External access denied to internal API'
        ]);
    }

    public function testInternalAuthRejectsCrossTenantIdor()
    {
        $db = \Config\Database::connect();
        $tenantId = 102;
        $tenantIdCross = 103;
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantId, 'nama_organisasi' => "KT Socket Test", 'kode_pin' => '123123', 'status_aktif' => 1]);
        $db->table('karang_taruna')->ignore(true)->insert(['id' => $tenantIdCross, 'nama_organisasi' => "KT Socket Test 2", 'kode_pin' => '123123', 'status_aktif' => 1]);

        $user = $this->createTestUser($tenantId, 'anggota', 'anggota_socket1');
        
        // Memiliki membership di $tenantId, tapi TIDAK di $tenantIdCross
        
        $token = $this->generateTokenForUser($user);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Karang-Taruna-ID' => (string)$tenantIdCross,
            'X-Internal-Secret' => getenv('INTERNAL_API_SECRET') ?: 'default_internal_secret_for_dev'
        ])->post('/api/internal/socket-auth');

        // Should be 403 Forbidden because they lack membership
        $result->assertStatus(403);
    }
}
