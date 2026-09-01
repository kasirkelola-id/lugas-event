<?php

namespace App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
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
        
        $userModel = new UserModel();
        $userModel->ignore(true)->insert([
            'nama_lengkap'   => 'Tester User',
            'nama_panggilan' => 'Test',
            'username'       => 'tester',
            'password'       => password_hash('password123', PASSWORD_BCRYPT),
            'role_level'     => 'pengelola',
            'status_aktif'   => 1,
        ]);
        
        $userModel->ignore(true)->insert([
            'nama_lengkap'   => 'Inactive User',
            'nama_panggilan' => 'Inactive',
            'username'       => 'inactive',
            'password'       => password_hash('password123', PASSWORD_BCRYPT),
            'role_level'     => 'anggota',
            'status_aktif'   => 0,
        ]);
    }

    public function testLoginSuccess()
    {
        $result = $this->post('api/login', [
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
    }

    public function testLoginWrongPassword()
    {
        $result = $this->post('api/login', [
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
            'username' => 'notexist',
            'password' => 'password123'
        ]);
        
        $result->assertStatus(401);
    }
    
    public function testLoginInactiveUser()
    {
        $result = $this->post('api/login', [
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
