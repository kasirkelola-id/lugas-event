<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;
use Tests\Support\AuthTrait;

class DashboardTest extends CIUnitTestCase
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
        $db->table('inventory_loans')->emptyTable();
        $db->table('inventories')->emptyTable();
        $db->table('events')->emptyTable();
        $db->table('pengumuman')->emptyTable();
        $db->table('votings')->emptyTable();
        $db->table('users')->emptyTable();
        $db->table('karang_taruna')->emptyTable();
        $db->table('organization_members')->emptyTable();
    }

    public function testDashboardTenantIsolationAndRbac()
    {
        $tenantA = 1;
        $tenantB = 2;
        $db = \Config\Database::connect();
        
        $db->table('karang_taruna')->insertBatch([
            ['id' => $tenantA, 'nama_organisasi' => 'KT A', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')],
            ['id' => $tenantB, 'nama_organisasi' => 'KT B', 'kode_pin' => '222222', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]
        ]);

        $adminIdA = 100;
        $memberIdA = 101;
        $adminIdB = 200;

        $db->table('users')->insertBatch([
            ['id' => $adminIdA, 'username' => 'admin_a', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin A', 'role_level' => 'admin', 'karang_taruna_id' => $tenantA],
            ['id' => $memberIdA, 'username' => 'member_a', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Member A', 'role_level' => 'anggota', 'karang_taruna_id' => $tenantA],
            ['id' => $adminIdB, 'username' => 'admin_b', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin B', 'role_level' => 'admin', 'karang_taruna_id' => $tenantB],
        ]);

        $db->table('organization_members')->insertBatch([
            ['user_id' => $adminIdA, 'karang_taruna_id' => $tenantA, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_a'],
            ['user_id' => $memberIdA, 'karang_taruna_id' => $tenantA, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'member_a'],
            ['user_id' => $adminIdB, 'karang_taruna_id' => $tenantB, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin_b'],
        ]);

        // Events
        $db->table('events')->insertBatch([
            ['karang_taruna_id' => $tenantA, 'nama_acara' => 'Event A', 'tanggal_acara' => date('Y-m-d', strtotime('+1 day')), 'waktu_mulai' => '08:00:00', 'waktu_selesai' => '10:00:00', 'status_aktif' => 1, 'dibuat_oleh' => $adminIdA, 'kode_qr' => 'A1'],
            ['karang_taruna_id' => $tenantB, 'nama_acara' => 'Event B', 'tanggal_acara' => date('Y-m-d', strtotime('+1 day')), 'waktu_mulai' => '08:00:00', 'waktu_selesai' => '10:00:00', 'status_aktif' => 1, 'dibuat_oleh' => $adminIdB, 'kode_qr' => 'B1'],
        ]);

        // Announcement
        $db->table('pengumuman')->insertBatch([
            ['karang_taruna_id' => $tenantA, 'judul' => 'Pengumuman A', 'isi' => 'Isi', 'status_aktif' => 1, 'dibuat_oleh' => $adminIdA],
            ['karang_taruna_id' => $tenantB, 'judul' => 'Pengumuman B', 'isi' => 'Isi', 'status_aktif' => 1, 'dibuat_oleh' => $adminIdB],
        ]);

        // Voting
        $db->table('votings')->insertBatch([
            ['karang_taruna_id' => $tenantA, 'title' => 'Voting A', 'description' => 'Isi', 'status' => 'active', 'created_by' => $adminIdA],
            ['karang_taruna_id' => $tenantB, 'title' => 'Voting B', 'description' => 'Isi', 'status' => 'active', 'created_by' => $adminIdB],
        ]);

        // Inventory
        $db->table('inventories')->insertBatch([
            ['id' => 1, 'karang_taruna_id' => $tenantA, 'name' => 'Item A', 'total_quantity' => 10, 'available_quantity' => 0, 'condition' => 'Baik'],
            ['id' => 2, 'karang_taruna_id' => $tenantB, 'name' => 'Item B', 'total_quantity' => 10, 'available_quantity' => 0, 'condition' => 'Baik'],
        ]);

        // Member A in Tenant A borrows Item A
        $db->table('inventory_loans')->insertBatch([
            ['id' => 1, 'inventory_id' => 1, 'user_id' => $memberIdA, 'quantity' => 2, 'borrow_date' => date('Y-m-d'), 'return_date' => date('Y-m-d'), 'status' => 'pending'],
            ['id' => 2, 'inventory_id' => 2, 'user_id' => $adminIdB, 'quantity' => 2, 'borrow_date' => date('Y-m-d'), 'return_date' => date('Y-m-d'), 'status' => 'pending'],
        ]);

        // TEST 1: Tenant Isolation & RBAC for Member A
        $memberUser = (new UserModel())->find($memberIdA);
        $memberToken = $this->generateTokenForUser($memberUser);
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $memberToken, 'X-Tenant-ID' => $tenantA])->get("api/dashboard");
        $result->assertStatus(200);
        $responseBody = json_decode($result->getJSON(), true);

        // Ordinary member tidak menerima `management_metrics`
        $this->assertNull($responseBody['data']['management']);
        
        // Tenant isolation: should see Event A, Pengumuman A, Voting A, Loan A
        $this->assertEquals('Event A', $responseBody['data']['upcoming_event']['title']);
        $this->assertEquals('Pengumuman A', $responseBody['data']['latest_announcement']['title']);
        $this->assertEquals('Voting A', $responseBody['data']['active_voting']['title']);
        $this->assertEquals('Item A', $responseBody['data']['my_active_loan']['inventory_name']);

        // TEST 2: Tenant Isolation & RBAC for Admin A
        $adminUser = (new UserModel())->find($adminIdA);
        $adminToken = $this->generateTokenForUser($adminUser);
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken, 'X-Tenant-ID' => $tenantA])->get("api/dashboard");
        $result2->assertStatus(200);
        $responseBody2 = json_decode($result2->getJSON(), true);

        // Authorized management user menerima metrics yang sesuai, tenant-scoped
        $this->assertNotNull($responseBody2['data']['management']);
        $this->assertEquals(1, $responseBody2['data']['management']['pending_loans']); // only 1 in Tenant A
        $this->assertEquals(2, $responseBody2['data']['management']['active_members']); // 2 in Tenant A
        $this->assertEquals(1, $responseBody2['data']['management']['out_of_stock']); // 1 in Tenant A

        // my_active_loan hanya milik authenticated user. Admin A did not borrow anything.
        $this->assertNull($responseBody2['data']['my_active_loan']);
    }

    public function testDashboardEventSelection()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => $tenantId, 'nama_organisasi' => 'KT Test', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        
        $adminId = 100;
        $db->table('users')->insert(['id' => $adminId, 'username' => 'admin', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin', 'role_level' => 'admin', 'karang_taruna_id' => $tenantId]);
        $db->table('organization_members')->insert(['user_id' => $adminId, 'karang_taruna_id' => $tenantId, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin']);

        // Insert events
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $nowTime = date('H:i:s');
        $pastTime = date('H:i:s', strtotime('-2 hours'));
        $futureTime = date('H:i:s', strtotime('+2 hours'));

        $db->table('events')->insertBatch([
            ['id' => 1, 'karang_taruna_id' => $tenantId, 'nama_acara' => 'Event Kemarin', 'tanggal_acara' => $yesterday, 'waktu_mulai' => '08:00:00', 'waktu_selesai' => '10:00:00', 'status_aktif' => 1, 'dibuat_oleh' => $adminId, 'kode_qr' => 'A1'],
            ['id' => 2, 'karang_taruna_id' => $tenantId, 'nama_acara' => 'Event Besok', 'tanggal_acara' => $tomorrow, 'waktu_mulai' => '08:00:00', 'waktu_selesai' => '10:00:00', 'status_aktif' => 1, 'dibuat_oleh' => $adminId, 'kode_qr' => 'A2'],
        ]);

        $adminUser = (new UserModel())->find($adminId);
        $adminToken = $this->generateTokenForUser($adminUser);
        
        // Cek jika hanya ada event kemarin dan besok, maka pilih besok
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken, 'X-Tenant-ID' => $tenantId])->get("api/dashboard");
        $body = json_decode($result->getJSON(), true);
        $this->assertEquals('Event Besok', $body['data']['upcoming_event']['title']);

        // Insert event hari ini tapi sedang berlangsung
        $db->table('events')->insert([
            'id' => 3, 'karang_taruna_id' => $tenantId, 'nama_acara' => 'Event Sedang Berlangsung', 
            'tanggal_acara' => $today, 'waktu_mulai' => $pastTime, 'waktu_selesai' => $futureTime, 
            'status_aktif' => 1, 'dibuat_oleh' => $adminId, 'kode_qr' => 'A3'
        ]);

        // Cek jika ada event berlangsung, dia yang dipilih walau ada event besok
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken, 'X-Tenant-ID' => $tenantId])->get("api/dashboard");
        $body2 = json_decode($result2->getJSON(), true);
        $this->assertEquals('Event Sedang Berlangsung', $body2['data']['upcoming_event']['title']);
    }

    public function testDashboardAnnouncementAndVotingSelection()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => $tenantId, 'nama_organisasi' => 'KT Test', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        
        $adminId = 100;
        $db->table('users')->insert(['id' => $adminId, 'username' => 'admin', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Admin', 'role_level' => 'admin', 'karang_taruna_id' => $tenantId]);
        $db->table('organization_members')->insert(['user_id' => $adminId, 'karang_taruna_id' => $tenantId, 'role_level' => 'admin', 'status_aktif' => 1, 'username' => 'admin']);

        // Announcements: A lama aktif, B terbaru nonaktif, C terbaru aktif
        $db->table('pengumuman')->insertBatch([
            ['id' => 1, 'karang_taruna_id' => $tenantId, 'judul' => 'Pengumuman A', 'isi' => 'A', 'status_aktif' => 1, 'dibuat_oleh' => $adminId, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
            ['id' => 2, 'karang_taruna_id' => $tenantId, 'judul' => 'Pengumuman B', 'isi' => 'B', 'status_aktif' => 0, 'dibuat_oleh' => $adminId, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))],
            ['id' => 3, 'karang_taruna_id' => $tenantId, 'judul' => 'Pengumuman C', 'isi' => 'C', 'status_aktif' => 1, 'dibuat_oleh' => $adminId, 'created_at' => date('Y-m-d H:i:s')],
        ]);

        // Votings: A completed, B draft, C active
        $db->table('votings')->insertBatch([
            ['id' => 1, 'karang_taruna_id' => $tenantId, 'title' => 'Voting A', 'description' => 'A', 'status' => 'completed', 'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
            ['id' => 2, 'karang_taruna_id' => $tenantId, 'title' => 'Voting B', 'description' => 'B', 'status' => 'draft', 'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))],
            ['id' => 3, 'karang_taruna_id' => $tenantId, 'title' => 'Voting C', 'description' => 'C', 'status' => 'active', 'created_by' => $adminId, 'created_at' => date('Y-m-d H:i:s')],
        ]);

        $adminUser = (new UserModel())->find($adminId);
        $adminToken = $this->generateTokenForUser($adminUser);
        
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken, 'X-Tenant-ID' => $tenantId])->get("api/dashboard");
        $body = json_decode($result->getJSON(), true);

        // Announcement Selection: should be C
        $this->assertEquals('Pengumuman C', $body['data']['latest_announcement']['title']);

        // Voting Selection: should be C
        $this->assertEquals('Voting C', $body['data']['active_voting']['title']);
    }

    public function testDashboardLoanSelection()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => $tenantId, 'nama_organisasi' => 'KT Test', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        
        $memberId = 100;
        $db->table('users')->insert(['id' => $memberId, 'username' => 'member', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Member', 'role_level' => 'anggota', 'karang_taruna_id' => $tenantId]);
        $db->table('organization_members')->insert(['user_id' => $memberId, 'karang_taruna_id' => $tenantId, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'member']);

        $db->table('inventories')->insert(['id' => 1, 'karang_taruna_id' => $tenantId, 'name' => 'Item X', 'total_quantity' => 10, 'available_quantity' => 10, 'condition' => 'Baik']);

        // Loan history for User A: rejected, returned, pending
        $db->table('inventory_loans')->insertBatch([
            ['id' => 1, 'inventory_id' => 1, 'user_id' => $memberId, 'quantity' => 1, 'status' => 'rejected', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))],
            ['id' => 2, 'inventory_id' => 1, 'user_id' => $memberId, 'quantity' => 1, 'status' => 'returned', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))],
            ['id' => 3, 'inventory_id' => 1, 'user_id' => $memberId, 'quantity' => 1, 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ]);

        $memberUser = (new UserModel())->find($memberId);
        $memberToken = $this->generateTokenForUser($memberUser);
        
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $memberToken, 'X-Tenant-ID' => $tenantId])->get("api/dashboard");
        $body = json_decode($result->getJSON(), true);

        // my_active_loan should be pending loan (ID 3), ignoring rejected/returned
        $this->assertNotNull($body['data']['my_active_loan']);
        $this->assertEquals('pending', $body['data']['my_active_loan']['status']);
    }

    public function testDashboardEmptyTenant()
    {
        $tenantId = 1;
        $db = \Config\Database::connect();
        $db->table('karang_taruna')->insert(['id' => $tenantId, 'nama_organisasi' => 'KT Test', 'kode_pin' => '111111', 'status_aktif' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        
        $memberId = 100;
        $db->table('users')->insert(['id' => $memberId, 'username' => 'member', 'password' => password_hash('123', PASSWORD_BCRYPT), 'nama_lengkap' => 'Member', 'role_level' => 'anggota', 'karang_taruna_id' => $tenantId]);
        $db->table('organization_members')->insert(['user_id' => $memberId, 'karang_taruna_id' => $tenantId, 'role_level' => 'anggota', 'status_aktif' => 1, 'username' => 'member']);

        // Segala table lain kosong
        $memberUser = (new UserModel())->find($memberId);
        $memberToken = $this->generateTokenForUser($memberUser);
        
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $memberToken, 'X-Tenant-ID' => $tenantId])->get("api/dashboard");
        $body = json_decode($result->getJSON(), true);

        $this->assertNull($body['data']['upcoming_event']);
        $this->assertNull($body['data']['latest_announcement']);
        $this->assertNull($body['data']['active_voting']);
        $this->assertNull($body['data']['my_active_loan']);
        $this->assertNull($body['data']['management']);
    }
}
