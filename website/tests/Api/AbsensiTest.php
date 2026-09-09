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

    public function testEventBeforeWindowRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 3,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Belum Mulai',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('+2 hours')), // > 30 mins from now
            'waktu_selesai' => date('H:i:s', strtotime('+4 hours')),
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
    
    public function testEventAfterWindowRejected()
    {
        $token = $this->anggotaToken;
        
        $this->db->table('events')->insert([
            'id' => 4,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Sudah Lewat',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-4 hours')),
            'waktu_selesai' => date('H:i:s', strtotime('-61 minutes')), // > 30 mins past
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR4'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 4
                         ]);

        $response->assertStatus(422);
        $json = json_decode($response->getJSON(), true);
        $this->assertFalse($json['status']);
        $this->assertEquals('Waktu Habis', $json['message']);
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

    public function testEventTimeWindowBoundaries()
    {
        $token = $this->anggotaToken;
        
        // Exact 30 minutes before should be allowed
        $this->db->table('events')->insert([
            'id' => 7,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Boundary',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('+29 minutes')), // Within 30 mins
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
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

    public function testTenantSettingsIsolation()
    {
        // Clear static cache in Testing environment
        \App\Services\SettingService::clearCache();

        // Add custom settings for Tenant 1
        $this->db->table('settings')->insert([
            'karang_taruna_id' => 1,
            'setting_key' => 'attendance_before_minutes',
            'setting_value' => '15'
        ]);
        $this->db->table('settings')->insert([
            'karang_taruna_id' => 1,
            'setting_key' => 'attendance_after_minutes',
            'setting_value' => '0'
        ]);

        $token = $this->anggotaToken;

        // Tenant 1 test: should reject 20 mins early (setting is 15)
        $this->db->table('events')->insert([
            'id' => 8,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event Before Isolation T1',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('+20 minutes')),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR8'
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 8
                         ]);
        $response->assertStatus(422);

        // Tenant 1 test: should reject 10 mins late (setting is 0)
        $this->db->table('events')->insert([
            'id' => 9,
            'karang_taruna_id' => 1,
            'nama_acara' => 'Event After Isolation T1',
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s', strtotime('-2 hours')),
            'waktu_selesai' => date('H:i:s', strtotime('-10 minutes')),
            'status_aktif' => 'aktif',
            'require_gps' => 0,
            'dibuat_oleh' => $this->ketuaUser['id'],
            'kode_qr' => 'TESTQR9'
        ]);

        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->withBodyFormat('json')
                         ->post('api/absensi/checkin', [
                             'event_id' => 9
                         ]);
        $response2->assertStatus(422);
    }
}
