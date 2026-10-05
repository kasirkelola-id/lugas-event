<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class DiscoveredDefectTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    private function browser(): array
    {
        $hash = password_hash('synthetic-private-passphrase', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['id' => 777, 'username' => 'synthetic-admin', 'nama_lengkap' => 'Synthetic Browser Author', 'password' => $hash]);
        \Config\Services::resetSingle('security');
        return ['is_superadmin_logged_in' => true, 'superadmin_id' => 777,
            'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('a', 64)];
    }

    public function test_web_announcement_records_actual_superadmin_and_outbox_and_api_displays_author(): void
    {
        $viewer = $this->createTestUser(101, 'anggota');
        $beforeUsers = $this->db->table('users')->get()->getResultArray();
        $browser = $this->browser();
        $result = $this->withSession($browser)->withHeaders([])->post('superadmin/manage/101/pengumuman/create', [
            'judul' => 'Synthetic announcement', 'isi' => 'Synthetic body', 'csrf_test_name' => str_repeat('a', 64),
            'dibuat_oleh' => $viewer['id'], 'dibuat_oleh_superadmin' => 999999, 'karang_taruna_id' => 102,
        ]);
        $result->assertRedirectTo('/superadmin/manage/101/pengumuman');
        $row = $this->db->table('pengumuman')->get()->getRowArray();
        $this->assertNotNull($row); $this->assertNull($row['dibuat_oleh']);
        $this->assertSame(777, (int)$row['dibuat_oleh_superadmin']); $this->assertSame(101, (int)$row['karang_taruna_id']);
        $this->assertSame($beforeUsers, $this->db->table('users')->get()->getResultArray());
        $this->assertSame(1, $this->db->table('notification_jobs')->where('kind', 'announcement')->where('entity_id', $row['id'])->countAllResults());
        $token = $this->generateTokenForUser($viewer);
        $response = $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'])->get('api/announcements');
        $response->assertStatus(200);
        $data = json_decode($response->getJSON(), true)['data'];
        $this->assertSame('Synthetic Browser Author', $data[0]['pembuat']);
    }

    public function test_legacy_users_redirects_to_scoped_membership_page_without_global_tenant_dependency(): void
    {
        $user = $this->createTestUser(101, 'anggota');
        $foreign = $this->createTestUser(102, 'anggota');
        $this->db->table('users')->where('id', $user['id'])->update(['karang_taruna_id' => null, 'nama_lengkap' => 'Synthetic Scoped Member']);
        $this->db->table('users')->where('id', $foreign['id'])->update(['nama_lengkap' => 'Synthetic Foreign Member']);
        $browser = $this->browser();
        $this->withSession($browser)->withHeaders([])->get('superadmin/karang_taruna/101/users')->assertRedirectTo('/superadmin/manage/101/users');
        $result = $this->withSession($browser)->withHeaders([])->get('superadmin/manage/101/users');
        $result->assertStatus(200);
        $this->assertStringContainsString('Synthetic Scoped Member', $result->getBody());
        $this->assertStringNotContainsString('Synthetic Foreign Member', $result->getBody());
    }

    public function test_failed_outbox_insert_cannot_report_success_or_leave_an_announcement(): void
    {
        $this->createTestUser(101);
        $browser = $this->browser();
        $this->db->query("CREATE TRIGGER synthetic_announcement_job_failure BEFORE INSERT ON notification_jobs BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        try {
            $this->withSession($browser)->withHeaders([])->post('superadmin/manage/101/pengumuman/create', [
                'judul' => 'Synthetic', 'isi' => 'Synthetic', 'csrf_test_name' => str_repeat('a', 64),
            ])->assertRedirectTo('/superadmin/manage/101/pengumuman');
            $this->assertSame(0, $this->db->table('pengumuman')->countAllResults());
            $this->assertSame(0, $this->db->table('notification_jobs')->countAllResults());
            $this->assertSame('Pengumuman gagal disimpan', session()->getFlashdata('error'));
        } finally { $this->db->query('DROP TRIGGER synthetic_announcement_job_failure'); $this->db->resetTransStatus(); }
    }

    public function test_past_active_event_read_state_agrees_with_existing_allowed_checkin_policy(): void
    {
        $host = $this->createTestUser(101, 'ketua'); $user = $this->createTestUser(101, 'anggota');
        $this->db->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Synthetic past active',
            'tanggal_acara' => date('Y-m-d', strtotime('-1 day')), 'waktu_mulai' => '00:00:00', 'waktu_selesai' => '00:00:01',
            'status_aktif' => 'aktif', 'require_gps' => 0, 'dibuat_oleh' => $host['id'], 'kode_qr' => 'synthetic-state']);
        $id = $this->db->insertID(); $token = $this->generateTokenForUser($user);
        $headers = $this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'];
        $response = $this->withHeaders($headers)->get('api/events'); $response->assertStatus(200);
        $this->assertSame('open', json_decode($response->getJSON(), true)['data'][0]['attendance_state']);
        $this->withHeaders($headers)->withBodyFormat('json')->post('api/absensi/checkin', ['event_id' => $id])->assertStatus(201);
    }
}
