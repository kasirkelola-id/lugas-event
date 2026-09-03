<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\EventModel;
use App\Models\AbsensiModel;
use App\Models\UserModel;
use App\Models\KarangTarunaModel;
use Tests\Support\AuthTrait;

class EventUxTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $migrateOnce = false;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        // Clear databases
        $db = \Config\Database::connect();
        $db->table('absensi')->emptyTable();
        $db->table('events')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
    }

    public function testCannotEditSensitiveFieldsIfAttendanceExists()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert([
            'id' => $tenantId,
            'nama_organisasi' => 'KT Test',
            'kode_pin' => '123123',
            'status_aktif' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $adminId = 100;
        $db->table('users')->insert([
            'id' => $adminId,
            'username' => 'admin_test',
            'password' => password_hash('123456', PASSWORD_BCRYPT),
            'nama_lengkap' => 'Admin Test',
            'role_level' => 'admin',
            'karang_taruna_id' => $tenantId,
        ]);
        $db->table('organization_members')->insert([
            'user_id' => $adminId,
            'karang_taruna_id' => $tenantId,
            'role_level' => 'admin',
            'status_aktif' => 1,
            'username' => 'admin_test'
        ]);

        $eventId = 200;
        $db->table('events')->insert([
            'id' => $eventId,
            'karang_taruna_id' => $tenantId,
            'nama_acara' => 'Acara Original',
            'tanggal_acara' => '2025-01-01',
            'waktu_mulai' => '08:00:00',
            'waktu_selesai' => '10:00:00',
            'status_aktif' => 'aktif',
            'dibuat_oleh' => $adminId,
            'require_gps' => 1,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 50,
            'kode_qr' => 'TESTQR'
        ]);

        // Add 1 attendance
        $db->table('absensi')->insert([
            'event_id' => $eventId,
            'user_id' => $adminId,
            'waktu_absen' => '2025-01-01 08:05:00',
            'latitude' => -6.2,
            'longitude' => 106.8
        ]);

        $adminUser = (new UserModel())->find($adminId);
        $token = $this->generateTokenForUser($adminUser);

        // Try to update radius
        $updateResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->put("api/events/$eventId", [
            'require_gps' => 1,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 100 // changed
        ]);

        $responseBody = json_decode($updateResult->getJSON(), true);
        if (!isset($responseBody['errors']['umum'])) {
            var_dump($responseBody);
        }
        
        // Should block
        $updateResult->assertStatus(422);
        $this->assertArrayHasKey('umum', $responseBody['errors'] ?? []);
        $this->assertStringContainsString('sudah ada absensi', $responseBody['errors']['umum']);

        // Try to update name only (should succeed if it's the only change, but if we send the same coords, they are unchanged)
        // Wait, if we send the exact same coords, it shouldn't block.
        $updateResult2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId
        ])->withBodyFormat('json')->put("api/events/$eventId", [
            'nama_acara' => 'Acara Baru',
            'require_gps' => 1,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 50 // unchanged
        ]);
        
        $responseBody2 = json_decode($updateResult2->getJSON(), true);
        
        // Wait, if it succeeds, status code should be 200
        $updateResult2->assertStatus(200);
    }
}
