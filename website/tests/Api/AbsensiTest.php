<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class AbsensiTest extends \Tests\Support\BaseTest
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthTrait;

    protected $migrateOnce = true;
    protected $refresh = false;
    protected $migrate = true;
    protected $namespace = 'App';

    protected $ketuaUser;
    protected $anggotaUser;
    protected $ketuaToken;
    protected $anggotaToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->ketuaUser = $this->createTestUser(1, 'ketua');
        $this->anggotaUser = $this->createTestUser(1, 'anggota');

        $this->ketuaToken = $this->generateTokenForUser($this->ketuaUser);
        $this->anggotaToken = $this->generateTokenForUser($this->anggotaUser);
    }

    public function testAbsensiInsideRadiusAccepted()
    {
        $token = $this->anggotaToken;
        
        // Monas coordinates: -6.175392, 106.827153
        $this->db->table('events')->insert([
            'id' => 1,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Valid',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-10 minutes')),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'require_gps' => 1,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius' => 100, // 100 meters
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR1'
        ]);

        // Same coordinate
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 1,
                             'user_lat' => -6.175392,
                             'user_lng' => 106.827153,
                             'accuracy' => 10
                         ]);

        $response->assertStatus(201);
        $json = json_decode($response->getJSON(), true);
        $this->assertTrue($json['status']);
    }

    public function testAbsensiOutsideRadiusRejected()
    {
        $token = $this->anggotaToken;
        
        // Monas coordinates
        $this->db->table('events')->insert([
            'id' => 2,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event GPS Jauh',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-10 minutes')),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'require_gps' => 1,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'radius' => 50, // 50 meters
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR2'
        ]);

        // Bundaran HI coordinates (approx 2km away): -6.195048, 106.823015
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 2,
                             'user_lat' => -6.195048,
                             'user_lng' => 106.823015,
                             'accuracy' => 10
                         ]);

        $response->assertStatus(422);
        $json = json_decode($response->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertStringContainsString('Di Luar Jangkauan', $json['message']);
    }

    public function testEventFutureRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 3,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Belum Mulai',
            'tanggal_acara' => date('Y-m-d', strtotime('+1 day')),
            'waktu_mulai' => '00:00:00',
            'waktu_selesai' => '23:59:59',
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR3'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 3
                         ]);

        $response->assertStatus(422);
        $json = json_decode($response->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertEquals('Belum Waktunya', $json['message']);
    }
    
    public function testEventPastAllowed()
    {
        // Event kemarin
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 4,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Kemarin',
            'tanggal_acara' => date('Y-m-d', strtotime('-1 day')),
            'waktu_mulai' => date('H:i:s', strtotime('-25 hours')),
            'waktu_selesai' => date('H:i:s', strtotime('-23 hours')),
            'status_aktif' => 1,
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR4'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 4
                         ]);

        $response->assertStatus(201);
        $json = json_decode($response->getJSON(), true);
        $this->assertTrue($json['status']);
        $this->assertEquals('Check-in berhasil', $json['message']);
    }

    public function testEventManualCloseRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 6,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Valid But Closed',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-10 minutes')),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'selesai',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR6'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 6
                         ]);

        $response->assertStatus(422);
        $json = json_decode($response->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertStringContainsString('ditutup', strtolower($json['message']));
    }

    public function testEventTodayAccepted()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 7,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Today',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => '00:00:00',
            'waktu_selesai' => '23:59:59',
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR7'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 7
                         ]);

        $response->assertStatus(201);
    }

    public function testDuplicateAttendanceRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 5,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Valid Dup',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-10 minutes')),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR5'
        ]);

        // Insert first attendance
        $this->db->table('absensi')->insert([
            'karang_taruna_id' => 1,
            'event_id' => 5,
            'user_id' => $this->anggotaUser['id'],
            'waktu_absen' => date('Y-m-d H:i:s')
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 5
                         ]);

        $response->assertStatus(409);
        $json = json_decode($response->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertStringContainsString('sudah', $json['message']);
    }

    public function testRadiusIsolationAndDynamicUpdate()
    {
        // Clear static cache in Testing environment
        \App\Services\SettingService::clearCache();

        // Hack for SQLite: Recreate settings table to allow multiple tenant settings
        // because historical migration made setting_key the primary key and we can't change it.
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query("CREATE TABLE IF NOT EXISTS settings_new (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key VARCHAR(100), setting_value TEXT, description VARCHAR(255), created_at DATETIME, updated_at DATETIME, karang_taruna_id INT)");
            $this->db->query("INSERT INTO settings_new (setting_key, setting_value, description, created_at, updated_at, karang_taruna_id) SELECT setting_key, setting_value, description, created_at, updated_at, karang_taruna_id FROM settings");
            $this->db->query("DROP TABLE settings");
            $this->db->query("ALTER TABLE settings_new RENAME TO settings");
            $this->db->query("CREATE UNIQUE INDEX IF NOT EXISTS unique_setting_tenant ON settings (setting_key, karang_taruna_id)");
        }

        // Create Tenant 2
        $this->db->table('karang_taruna')->insert([
            'id' => 2,
            'nama_organisasi' => 'Tenant 2',
            'kode_pin' => '222222',
            'status_aktif' => 1
        ]);

        $userModel = new \App\Models\UserModel();
        $memberModel = new \App\Models\OrganizationMemberModel();

        // Add user to Tenant 2
        $userId2 = $userModel->insert([
            'karang_taruna_id' => 2,
            'nama_lengkap' => 'Anggota T2',
            'username' => 'anggota_t2',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        $memberModel->insert([
            'karang_taruna_id' => 2,
            'user_id' => $userId2,
            'username' => 'anggota_t2',
            'role_level' => 'anggota',
            'status_aktif' => 1
        ]);
        $user2 = $userModel->find($userId2);
        $token2 = $this->generateTokenForUser($user2);

        // Set Radius Tenant 1 = 50, Tenant 2 = 200
        $this->db->table('settings')->insert([
            'karang_taruna_id' => 1,
            'setting_key' => 'default_geofence_radius',
            'setting_value' => '50'
        ]);
        $this->db->table('settings')->insert([
            'karang_taruna_id' => 2,
            'setting_key' => 'default_geofence_radius',
            'setting_value' => '200'
        ]);

        // Setup Event Tenant 1
        $this->db->table('events')->insert([
            'id' => 10,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event T1',
            'tanggal_acara' => date('Y-m-d'),
            'require_gps' => 1,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'status_aktif' => 1,
            'kode_qr' => 'TESTQR10',
            'radius' => 10, // Legacy radius (should be ignored)
            'dibuat_oleh' => $this->ketuaUser['id']
        ]);

        // Setup Event Tenant 2
        $this->db->table('events')->insert([
            'id' => 20,
            'karang_taruna_id' => 2,
            'nama_acara' => 'Event T2',
            'tanggal_acara' => date('Y-m-d'),
            'require_gps' => 1,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
            'status_aktif' => 1,
            'kode_qr' => 'TESTQR20',
            'radius' => 10, // Legacy radius
            'dibuat_oleh' => $userId2
        ]);

        // Test 1: Check-in T1 with distance 100m -> Fails because radius is 50
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->anggotaToken])
                          ->withBodyFormat('json')
                          ->post('api/absensi/checkin', [
                              'event_id' => 10,
                              'user_lat' => -6.176290, // approx 100m away
                              'user_lng' => 106.827153
                          ]);
        $response1->assertStatus(422);
        
        // Test 2: Check-in T2 with distance 100m -> Success because radius is 200
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token2])
                          ->withBodyFormat('json')
                          ->post('api/absensi/checkin', [
                              'event_id' => 20,
                              'user_lat' => -6.176290, // approx 100m away
                              'user_lng' => 106.827153
                          ]);
        $response2->assertStatus(201);

        // Dynamic Update: Change Tenant 1 radius to 150
        $this->db->table('settings')->where('karang_taruna_id', 1)->where('setting_key', 'default_geofence_radius')->update(['setting_value' => '150']);
        \App\Services\SettingService::clearCache();

        // Test 3: Check-in T1 again with distance 100m -> Success because radius is now 150
        $response3 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->anggotaToken])
                          ->withBodyFormat('json')
                          ->post('api/absensi/checkin', [
                              'event_id' => 10,
                              'user_lat' => -6.176290,
                              'user_lng' => 106.827153
                          ]);
        $response3->assertStatus(201);
    }
}
