<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

/** SEC-01/03 enforce safe behavior; the remaining characterizations are open defects. */
class ProductionReadinessAuditTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $migrate = true;
    protected $namespace = 'App';

    public function testAuditAttendanceStatusExcludesOtherTenant(): void
    {
        $user = $this->createTestUser(101);
        $owner = $this->createTestUser(102, 'ketua');
        $event = $this->db->table('events');
        $event->insert(['karang_taruna_id' => 102, 'nama_acara' => 'Audit B',
            'tanggal_acara' => date('Y-m-d'), 'kode_qr' => 'AUDIT-B',
            'dibuat_oleh' => $owner['id'], 'status_aktif' => 'aktif']);
        $eventId = $this->db->insertID();
        $this->db->table('absensi')->insert(['karang_taruna_id' => 102,
            'event_id' => $eventId, 'user_id' => $user['id'], 'waktu_absen' => date('Y-m-d H:i:s')]);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->generateTokenForUser($user),
            'X-Karang-Taruna-ID' => '101'])->get('api/absensi/status');
        $result->assertStatus(200);
        $this->assertNotContains($eventId, json_decode($result->getJSON(), true)['data']['active_event_ids']);
    }

    public function testAuditPendingMembershipDeniedUsingAnotherTenantToken(): void
    {
        $user = $this->createTestUser(101);
        $this->createTestUser(102);
        $this->db->table('organization_members')->insert(['user_id' => $user['id'],
            'karang_taruna_id' => 102, 'username' => 'audit_pending', 'role_level' => 'anggota',
            'status_aktif' => 1, 'approval_status' => 'pending']);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $this->generateTokenForUser($user),
            'X-Karang-Taruna-ID' => '102'])->get('api/inventories');
        $result->assertStatus(403);
    }

    public function testAuditPastActiveEventAllowsCheckin(): void
    {
        $user = $this->createTestUser(101);
        $this->db->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Audit past',
            'tanggal_acara' => date('Y-m-d', strtotime('-7 days')), 'kode_qr' => 'AUDIT-PAST',
            'dibuat_oleh' => $user['id'], 'status_aktif' => 'aktif', 'require_gps' => 0]);
        $eventId = $this->db->insertID();
        $result = $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))
            ->withBodyFormat('json')->post('api/absensi/checkin', ['event_id' => $eventId]);
        $result->assertStatus(201);
    }

    public function testAuditCreatedUserHasPredictablePasswordWithoutForcedChange(): void
    {
        // SQLite migration compatibility suppresses DDL errors but leaves transaction
        // status dirty. Reset test setup state before exercising a transactional API.
        $this->db->resetTransStatus();
        $user = $this->createTestUser(101, 'ketua');
        $result = $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))
            ->withBodyFormat('json')->post('api/users', ['nama_lengkap' => 'Audit user',
                'nama_panggilan' => 'Audit', 'username' => 'audit_new', 'role_level' => 'anggota',
                'no_whatsapp' => '08000000000', 'rt' => 1]);
        $this->assertEquals(201, $result->response()->getStatusCode(), $result->getJSON());
        $created = $this->db->table('users')->where('username', 'audit_new')->get()->getRowArray();
        $this->assertTrue(password_verify('audit_new', $created['password']));
        $this->assertEquals(0, $created['password_must_change']);
    }
}
