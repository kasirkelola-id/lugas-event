<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class MembershipAtomicityTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
    }

    public function test_failed_registration_membership_leaves_no_global_identity(): void
    {
        $this->createTestUser(101);
        $before = $this->db->table('users')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER registration_failure BEFORE INSERT ON organization_members BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withBodyFormat('json')->post('api/register', ['karang_taruna_id' => 101,
                'username' => 'synthetic_register', 'nama_lengkap' => 'Synthetic', 'nama_panggilan' => 'Synthetic',
                'password' => 'private-registration-123', 'confirm_password' => 'private-registration-123', 'no_whatsapp' => '081299998888'])->assertStatus(500);
            $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER registration_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_failed_approval_history_leaves_pending_membership(): void
    {
        $manager = $this->createTestUser(101, 'ketua');
        $member = $this->createTestUser(101);
        $this->db->table('organization_members')->where('user_id', $member['id'])->update(['approval_status' => 'pending']);
        $row = $this->db->table('organization_members')->where('user_id', $member['id'])->get()->getRowArray();
        $this->db->query("CREATE TRIGGER approval_history_failure BEFORE INSERT ON membership_approval_history BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($manager)))->post("api/memberships/{$row['id']}/approve")->assertStatus(500);
            $this->assertSame($row, $this->db->table('organization_members')->where('id', $row['id'])->get()->getRowArray());
        } finally {
            $this->db->query('DROP TRIGGER approval_history_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_manager_creation_failure_returns_no_credential_and_no_identity(): void
    {
        $manager = $this->createTestUser(101, 'ketua');
        $beforeUsers = $this->db->table('users')->get()->getResultArray();
        $beforeMembers = $this->db->table('organization_members')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER manager_creation_failure BEFORE INSERT ON organization_members BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $response = $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($manager)))->withBodyFormat('json')
                ->post('api/users', ['username' => 'synthetic_created', 'nama_lengkap' => 'Synthetic', 'nama_panggilan' => 'Synthetic', 'role_level' => 'anggota']);
            $response->assertStatus(500);
            $this->assertStringNotContainsString('temporary_password', $response->getJSON());
            $this->assertStringNotContainsString('synthetic-private-detail', $response->getJSON());
            $this->assertSame($beforeUsers, $this->db->table('users')->get()->getResultArray());
            $this->assertSame($beforeMembers, $this->db->table('organization_members')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER manager_creation_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_only_pending_decision_writes_one_history_and_foreign_scope_denied(): void
    {
        $manager = $this->createTestUser(101, 'ketua');
        $member = $this->createTestUser(101);
        $this->db->table('organization_members')->where('user_id', $member['id'])->update(['approval_status' => 'pending']);
        $row = $this->db->table('organization_members')->where('user_id', $member['id'])->get()->getRowArray();
        $this->assertFalse(\App\Services\MembershipApprovalService::decide(102, $row['id'], 'approved', $manager['id'], 'user'));
        $this->assertSame('pending', $this->db->table('organization_members')->where('id', $row['id'])->get()->getRowArray()['approval_status']);
        $this->assertTrue(\App\Services\MembershipApprovalService::decide(101, $row['id'], 'approved', $manager['id'], 'user'));
        $this->assertFalse(\App\Services\MembershipApprovalService::decide(101, $row['id'], 'rejected', $manager['id'], 'user'));
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($manager)))->post("api/memberships/{$row['id']}/reject")->assertStatus(422);
        $this->assertSame(1, $this->db->table('membership_approval_history')->where('organization_member_id', $row['id'])->countAllResults());
        $this->assertSame('approved', $this->db->table('organization_members')->where('id', $row['id'])->get()->getRowArray()['approval_status']);
    }

    public function test_browser_approval_history_failure_rolls_back_membership(): void
    {
        $member = $this->createTestUser(101);
        $hash = password_hash('synthetic-private-admin', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic', 'nama_lengkap' => 'Synthetic', 'password' => $hash]);
        $this->db->table('organization_members')->where('user_id', $member['id'])->update(['approval_status' => 'pending']);
        $row = $this->db->table('organization_members')->where('user_id', $member['id'])->get()->getRowArray();
        \Config\Services::resetSingle('security');
        $this->db->query("CREATE TRIGGER browser_history_failure BEFORE INSERT ON membership_approval_history BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
                'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('f', 64)])
                ->withHeaders(['X-CSRF-TOKEN' => str_repeat('f', 64)])
                ->post("superadmin/manage/101/users/approve/{$row['id']}")->assertRedirect();
            $this->assertSame('Gagal memproses approval.', session('error'));
            $this->assertSame($row, $this->db->table('organization_members')->where('id', $row['id'])->get()->getRowArray());
            $this->assertSame(0, $this->db->table('membership_approval_history')->countAllResults());
        } finally {
            $this->db->query('DROP TRIGGER browser_history_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_inactive_tenant_registration_leaves_users_unchanged(): void
    {
        $this->createTestUser(101);
        $this->db->table('karang_taruna')->where('id', 101)->update(['status_aktif' => 0]);
        $before = $this->db->table('users')->get()->getResultArray();
        $this->withBodyFormat('json')->post('api/register', ['karang_taruna_id' => 101,
            'username' => 'synthetic_inactive', 'nama_lengkap' => 'Synthetic', 'nama_panggilan' => 'Synthetic',
            'password' => 'private-registration-123', 'confirm_password' => 'private-registration-123', 'no_whatsapp' => '081299998888'])->assertStatus(422);
        $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
    }
}
