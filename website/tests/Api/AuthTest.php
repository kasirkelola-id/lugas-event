<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use App\Models\OrganizationMemberModel;
use App\Models\UserTokenModel;
use CodeIgniter\Test\DatabaseTestTrait;

class AuthTest extends CIUnitTestCase
{
    use FeatureTestTrait, DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset rate limiter cache before each test
        cache()->clean();
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => 1,
            'nama_organisasi' => 'Test Karang Taruna',
            'kode_pin' => '123456',
            'alamat_lengkap' => 'Alamat',
            'status_aktif' => 1
        ]);
        
        $userModel = new UserModel();
        $user1 = $userModel->where('username', 'tester')->first();
        if (!$user1) {
            $userId1 = $userModel->insert([
                'karang_taruna_id' => 1,
                'nama_lengkap'   => 'Tester User',
                'nama_panggilan' => 'Test',
                'username'       => 'tester',
                'password'       => password_hash('password123', PASSWORD_BCRYPT),
                'role_level'     => 'pengelola',
                'status_aktif'   => 1,
            ]);
            
            $db->table('organization_members')->insert([
                'user_id' => $userId1,
                'karang_taruna_id' => 1,
                'username' => 'tester',
                'role_level' => 'pengelola',
                'status_aktif' => 1
            ]);
        }
        
        $user2 = $userModel->where('username', 'inactive')->first();
        if (!$user2) {
            $userId2 = $userModel->insert([
                'karang_taruna_id' => 1,
                'nama_lengkap'   => 'Inactive User',
                'nama_panggilan' => 'Inactive',
                'username'       => 'inactive',
                'password'       => password_hash('password123', PASSWORD_BCRYPT),
                'role_level'     => 'anggota',
                'status_aktif'   => 0,
            ]);
            
            $db->table('organization_members')->insert([
                'user_id' => $userId2,
                'karang_taruna_id' => 1,
                'username' => 'inactive',
                'role_level' => 'anggota',
                'status_aktif' => 0
            ]);
        }
    }

    public function testLoginSuccess()
    {
        $result = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'tester',
            'password' => 'password123'
        ]);
        
        $result->assertStatus(200);
        $result->assertJSONFragment(['status' => true]);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['status']);
        $this->assertArrayHasKey('token', $json['data']);
        $this->assertArrayNotHasKey('password', $json['data']['user']);
        
        // Assert token is in DB hashed
        $tokenModel = new UserTokenModel();
        $hash = hash('sha256', $json['data']['token']);
        $this->assertNotNull($tokenModel->where('token_hash', $hash)->first());
        
        // Single membership should not require tenant selection
        $this->assertFalse($json['data']['requires_tenant_selection']);
        $this->assertCount(1, $json['data']['memberships'], 'No memberships array if single membership returned in AuthController? Wait, it returns array of 1');
        // Actually AuthController returns array of 1 for single membership right now? Let's fix the assert
    }

    public function testLoginWithMultipleMemberships()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => 2,
            'nama_organisasi' => 'KT 2',
            'kode_pin' => '222222',
            'alamat_lengkap' => '-',
            'status_aktif' => 1
        ]);
        
        $userModel = new UserModel();
        $multiUser = $userModel->where('username', 'multi')->first();
        if (!$multiUser) {
            $userId = $userModel->insert([
                'karang_taruna_id' => 1,
                'nama_lengkap'   => 'Multi',
                'username'       => 'multi',
                'password'       => password_hash('pass', PASSWORD_BCRYPT),
                'role_level'     => 'anggota',
                'status_aktif'   => 1,
            ]);

            $memberModel = new OrganizationMemberModel();
            $memberModel->insert([
                'user_id' => $userId,
                'karang_taruna_id' => 1,
                'username' => 'multi',
                'role_level' => 'anggota',
                'status_aktif' => 1
            ]);
            $memberModel->insert([
                'user_id' => $userId,
                'karang_taruna_id' => 2,
                'username' => 'multi_2',
                'role_level' => 'pengelola',
                'status_aktif' => 1
            ]);
        }
        $result = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'multi',
            'password' => 'pass'
        ]);
        
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        
        $this->assertArrayHasKey('memberships', $json['data']);
        $this->assertCount(2, $json['data']['memberships']);
    }

    public function testDuplicateGlobalUsernamesDifferentTenants()
    {
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => 3,
            'nama_organisasi' => 'KT 3',
            'kode_pin' => '333333',
            'alamat_lengkap' => '-',
            'status_aktif' => 1
        ]);
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => 4,
            'nama_organisasi' => 'KT 4',
            'kode_pin' => '444444',
            'alamat_lengkap' => '-',
            'status_aktif' => 1
        ]);

        // User X in Tenant 3
        $userModel = new UserModel();
        $userIdX = $userModel->insert([
            'karang_taruna_id' => 3,
            'nama_lengkap'   => 'User X',
            'username'       => 'duplicate_andi',
            'password'       => password_hash('passwordX', PASSWORD_BCRYPT),
            'role_level'     => 'anggota',
            'status_aktif'   => 1,
        ]);
        $memberModel = new OrganizationMemberModel();
        $memberModel->insert([
            'user_id' => $userIdX,
            'karang_taruna_id' => 3,
            'username' => 'duplicate_andi',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);

        // User Y in Tenant 4
        $userIdY = $userModel->insert([
            'karang_taruna_id' => 4,
            'nama_lengkap'   => 'User Y',
            'username'       => 'duplicate_andi',
            'password'       => password_hash('passwordY', PASSWORD_BCRYPT),
            'role_level'     => 'anggota',
            'status_aktif'   => 1,
        ]);
        $memberModel->insert([
            'user_id' => $userIdY,
            'karang_taruna_id' => 4,
            'username' => 'duplicate_andi',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);

        // Assert both are created and have different IDs
        $this->assertNotEquals($userIdX, $userIdY);

        // Test Login User X
        $resX = $this->post('api/login', [
            'karang_taruna_id' => 3,
            'username' => 'duplicate_andi',
            'password' => 'passwordX'
        ]);
        $resX->assertStatus(200);
        
        // Test Login User Y
        $resY = $this->post('api/login', [
            'karang_taruna_id' => 4,
            'username' => 'duplicate_andi',
            'password' => 'passwordY'
        ]);
        $resY->assertStatus(200);
    }

    public function testCrossPasswordLogin()
    {
        $uniqueIp = '10.0.0.' . rand(1, 255);
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 5, 'nama_organisasi' => 'KT 5', 'kode_pin' => '555', 'status_aktif' => 1]);
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 6, 'nama_organisasi' => 'KT 6', 'kode_pin' => '666', 'status_aktif' => 1]);

        $userModel = new UserModel();
        $memberModel = new OrganizationMemberModel();

        $userIdA = $userModel->insert(['karang_taruna_id' => 5, 'nama_lengkap' => 'A', 'username' => 'cross_user', 'password' => password_hash('passA', PASSWORD_BCRYPT), 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userIdA, 'karang_taruna_id' => 5, 'username' => 'cross_user', 'role_level' => 'anggota', 'status_aktif' => 1]);

        $userIdB = $userModel->insert(['karang_taruna_id' => 6, 'nama_lengkap' => 'B', 'username' => 'cross_user', 'password' => password_hash('passB', PASSWORD_BCRYPT), 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userIdB, 'karang_taruna_id' => 6, 'username' => 'cross_user', 'role_level' => 'anggota', 'status_aktif' => 1]);

        // Valid Logins
        $this->withHeaders(['X-Forwarded-For' => $uniqueIp])->post('api/login', ['karang_taruna_id' => 5, 'username' => 'cross_user', 'password' => 'passA'])->assertStatus(200);
        $this->withHeaders(['X-Forwarded-For' => $uniqueIp])->post('api/login', ['karang_taruna_id' => 6, 'username' => 'cross_user', 'password' => 'passB'])->assertStatus(200);

        // Invalid Cross Logins
        $this->withHeaders(['X-Forwarded-For' => $uniqueIp])->post('api/login', ['karang_taruna_id' => 5, 'username' => 'cross_user', 'password' => 'passB'])->assertStatus(401);
        $this->withHeaders(['X-Forwarded-For' => $uniqueIp])->post('api/login', ['karang_taruna_id' => 6, 'username' => 'cross_user', 'password' => 'passA'])->assertStatus(401);
    }

    public function testLoginRateLimiting()
    {
        $uniqueIp = '10.0.1.1';
        
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->ignore(true)->insert(['id' => 7, 'nama_organisasi' => 'KT 7', 'kode_pin' => '777', 'status_aktif' => 1]);
        
        $userModel = new UserModel();
        $memberModel = new OrganizationMemberModel();
        
        $userId = $userModel->insert(['karang_taruna_id' => 7, 'nama_lengkap' => 'Rate Limit User', 'username' => 'ratelimit', 'password' => password_hash('pass', PASSWORD_BCRYPT), 'role_level' => 'anggota', 'status_aktif' => 1]);
        $memberModel->insert(['user_id' => $userId, 'karang_taruna_id' => 7, 'username' => 'ratelimit', 'role_level' => 'anggota', 'status_aktif' => 1]);

        // 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => $uniqueIp, 'X-RateLimit-Test' => '1'])->post('api/login', [
                'karang_taruna_id' => 7,
                'username' => 'ratelimit',
                'password' => 'wrong'
            ])->assertStatus(401);
        }

        // 6th attempt should hit rate limit (429)
        $res = $this->withHeaders(['X-Forwarded-For' => $uniqueIp, 'X-RateLimit-Test' => '1'])->post('api/login', [
            'karang_taruna_id' => 7,
            'username' => 'ratelimit',
            'password' => 'wrong'
        ]);
        
        $res->assertStatus(429);
        $json = json_decode($res->getJSON(), true);
        $this->assertEquals('Terlalu banyak permintaan. Silakan coba lagi nanti.', $json['message']);
    }

    public function testLoginWrongPassword()
    {
        $result = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'tester',
            'password' => 'wrongpassword'
        ]);
        
        $result->assertStatus(401);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertEquals('Username atau password salah.', $json['message']);
    }

    public function testLoginWrongUsername()
    {
        $result = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'notexist',
            'password' => 'password123'
        ]);
        
        $result->assertStatus(401);
    }
    
    public function testLoginInactiveUser()
    {
        $uniqueIp = '10.0.1.4';
        $result = $this->withHeaders(['X-Forwarded-For' => $uniqueIp])->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'inactive',
            'password' => 'password123'
        ]);
        
        $result->assertStatus(401);
    }
    
    public function testMeWithoutToken()
    {
        $result = $this->get('api/me');
        $result->assertStatus(401);
    }

    public function testMeWithValidToken()
    {
        // Login first
        $login = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'tester',
            'password' => 'password123'
        ]);
        $token = json_decode($login->getJSON(), true)['data']['token'];
        
        // Access ME
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                       ->get('api/me');
                       
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['status']);
        $this->assertEquals('tester', $json['data']['username']);
        $this->assertArrayNotHasKey('password', $json['data']);
    }
    
    public function testMeWithInvalidToken()
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer randominvalidtoken123'])
                       ->get('api/me');
                       
        $result->assertStatus(401);
    }
    
    public function testLogoutInvalidatesToken()
    {
        // Login
        $login = $this->post('api/login', [
            'karang_taruna_id' => 1,
            'username' => 'tester',
            'password' => 'password123'
        ]);
        $token = json_decode($login->getJSON(), true)['data']['token'];
        
        // Logout
        $logout = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                       ->post('api/logout');
        $logout->assertStatus(200);
        
        // Try ME again
        $me = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                   ->get('api/me');
        $me->assertStatus(401);
    }
}
