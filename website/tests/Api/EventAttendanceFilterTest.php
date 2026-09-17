<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class EventAttendanceFilterTest extends \Tests\Support\BaseTest
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthTrait;

    protected $migrateOnce = true;
    protected $refresh = false;
    protected $migrate = true;
    protected $namespace = 'App';

    protected $userA;
    protected $userB;
    protected $userTenant2;
    protected $tokenA;
    protected $tokenB;
    protected $tokenTenant2;

    protected function setUp(): void
    {
        parent::setUp();

        // Tenant 1 users
        $this->userA = $this->createTestUser(1, 'anggota', 'userA@test.com');
        $this->userB = $this->createTestUser(1, 'anggota', 'userB@test.com');
        $this->tokenA = $this->generateTokenForUser($this->userA);
        $this->tokenB = $this->generateTokenForUser($this->userB);

        // Tenant 2 user
        $this->userTenant2 = $this->createTestUser(2, 'anggota', 'tenant2@test.com');
        $this->tokenTenant2 = $this->generateTokenForUser($this->userTenant2);
    }

    private function createEvent($id, $tenantId, $nama)
    {
        $this->db->table('events')->insert([
            'id' => $id,
            'karang_taruna_id' => $tenantId,
            'nama_acara' => $nama,
            'tanggal_acara' => date('Y-m-d'),
            'waktu_mulai' => date('H:i:s'),
            'waktu_selesai' => date('H:i:s', strtotime('+2 hours')),
            'status_aktif' => 'aktif',
            'dibuat_oleh' => $this->userA['id'],
            'kode_qr' => 'TESTQR_' . $id
        ]);
    }

    private function createAttendance($eventId, $userId, $waktuCheckout = null)
    {
        $this->db->table('absensi')->insert([
            'karang_taruna_id' => 1,
            'event_id' => $eventId,
            'user_id' => $userId,
            'waktu_absen' => date('Y-m-d H:i:s'),
            'waktu_checkout' => $waktuCheckout
        ]);
    }

    public function testScenarioA_NoAttendanceReturned()
    {
        $this->createEvent(101, 1, 'Event A');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events?attendance_only=1');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 101) $found = true;
        }
        $this->assertTrue($found, 'Event should be returned if user has no attendance');
    }

    public function testScenarioB_CheckedInReturned()
    {
        $this->createEvent(102, 1, 'Event B');
        $this->createAttendance(102, $this->userA['id'], null); // Checked in only

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events?attendance_only=1');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 102) $found = true;
        }
        $this->assertTrue($found, 'Event should be returned if user is checked in but not checked out');
    }

    public function testScenarioC_CompletedCheckoutNotReturned()
    {
        $this->createEvent(103, 1, 'Event C');
        $this->createAttendance(103, $this->userA['id'], date('Y-m-d H:i:s')); // Completed

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events?attendance_only=1');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 103) $found = true;
        }
        $this->assertFalse($found, 'Event should NOT be returned if user has checked out');
    }

    public function testScenarioD_DifferentUserCompletedReturned()
    {
        $this->createEvent(104, 1, 'Event D');
        $this->createAttendance(104, $this->userB['id'], date('Y-m-d H:i:s')); // User B completed

        // User A fetches
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events?attendance_only=1');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 104) $found = true;
        }
        $this->assertTrue($found, 'Event should be returned for User A even if User B completed it');
    }

    public function testScenarioE_CompletedAttendanceInAnotherTenant()
    {
        // Tenant 2 Event
        $this->createEvent(105, 2, 'Event E Tenant 2');

        // Tenant 2 user completes attendance in Tenant 2 event
        $this->db->table('absensi')->insert([
            'karang_taruna_id' => 2,
            'event_id' => 105,
            'user_id' => $this->userTenant2['id'],
            'waktu_absen' => date('Y-m-d H:i:s'),
            'waktu_checkout' => date('Y-m-d H:i:s')
        ]);

        // Tenant 1 Event (User A hasn't attended)
        $this->createEvent(106, 1, 'Event E Tenant 1');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events?attendance_only=1');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 106) $found = true;
            if ($event['id'] == 105) $this->fail('Event from tenant 2 leaked into tenant 1');
        }
        $this->assertTrue($found, 'Event in Tenant 1 should be unaffected by Tenant 2 attendance');
    }

    public function testScenarioF_AttendanceOnlyAbsentReturnsCompletedEvent()
    {
        $this->createEvent(107, 1, 'Event F');
        $this->createAttendance(107, $this->userA['id'], date('Y-m-d H:i:s')); // Completed

        // Fetch without attendance_only=1
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->tokenA])
                         ->get('api/events');

        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true);

        $found = false;
        foreach ($data['data'] as $event) {
            if ($event['id'] == 107) $found = true;
        }
        $this->assertTrue($found, 'Event should be returned if attendance_only flag is omitted');
    }
}
