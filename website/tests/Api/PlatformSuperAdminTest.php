<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class PlatformSuperAdminTest extends \Tests\Support\BaseTest
{
    protected $migrateOnce = true;
    protected $refresh = false;
    use FeatureTestTrait;

    protected $migrate = true;
    
    protected $namespace = 'App';

    public function testSuperadminLoginAndProfile()
    {
        $db = \Config\Database::connect();
        
        $db->table('karang_taruna')->ignore(true)->insert([
            'id' => 100,
            'nama_organisasi' => 'KT Superadmin Test',
            'kode_pin' => '000000',
            'status_aktif' => 1
        ]);

        $db->table('superadmins')->ignore(true)->insert([
            'username' => 'superadmin',
            'password' => password_hash('superadmin123', PASSWORD_BCRYPT),
            'nama_lengkap' => 'Administrator Sistem',
        ]);

        $resLogin = $this->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 100,
            'username' => 'superadmin',
            'password' => 'superadmin123'
        ]);

        $resLogin->assertStatus(200);
        $json = json_decode($resLogin->getJSON(), true);

        $this->assertEquals(100, $json['data']['user']['karang_taruna_id']);
        $this->assertEquals('superadmin', $json['data']['user']['role_level']);
        
        $token = $json['data']['token'];
        
        $resMe = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('api/me');
        $resMe->assertStatus(200);
        $jsonMe = json_decode($resMe->getJSON(), true);
        
        $this->assertEquals(100, $jsonMe['data']['karang_taruna']['id']);
        $this->assertEquals('superadmin', $jsonMe['data']['role_level']);
    }
}
