<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class CollectionPaginationTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    public function test_invalid_bounds_are_rejected_by_all_collection_routes(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        foreach (['events', 'kas', 'announcements', 'votings', 'wheels', 'inventories', 'inventories/loans', 'absensi/my', 'users', 'chats/rooms'] as $route) {
            foreach (['limit=0', 'limit=-1', 'limit=101', 'limit[]=1', 'limit=1.5', 'page=0', 'page=10001', 'page[]=2'] as $query) {
                $this->withHeaders($headers)->get('api/' . $route . '?' . $query)->assertStatus(422);
            }
        }
    }

    public function test_event_pages_are_stable_scoped_and_metadata_matches_visible_rows(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        $rows = [];
        for ($i = 0; $i < 106; $i++) $rows[] = ['karang_taruna_id' => $i === 105 ? 102 : 101,
            'nama_acara' => 'Synthetic ' . $i, 'tanggal_acara' => gmdate('Y-m-d'), 'waktu_mulai' => '10:00:00',
            'kode_qr' => 'synthetic-page-' . $i, 'status_aktif' => 'aktif', 'dibuat_oleh' => $user['id']];
        $this->db->table('events')->insertBatch($rows);
        $ids = [];
        foreach ([1 => 100, 2 => 5, 3 => 0] as $page => $count) {
            $response = $this->withHeaders($headers)->get('api/events?limit=100&page=' . $page);
            $response->assertStatus(200); $body = json_decode($response->getJSON(), true);
            $this->assertCount($count, $body['data']);
            $this->assertSame(105, $body['pagination']['total']);
            $this->assertSame(2, $body['pagination']['total_pages']);
            $this->assertSame($page === 1, $body['pagination']['has_more']);
            $ids = array_merge($ids, array_column($body['data'], 'id'));
        }
        $this->assertCount(105, array_unique($ids));
        $response = $this->withHeaders($headers)->get('api/events');
        $this->assertCount(50, json_decode($response->getJSON(), true)['data']);
    }

    public function test_cash_page_preserves_full_balance_and_month_scope(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        foreach ([101, 101, 102] as $tenant) $this->db->table('kas')->insert([
            'karang_taruna_id' => $tenant, 'jenis' => 'pemasukan', 'nominal' => 100,
            'keterangan' => 'Synthetic', 'tanggal' => gmdate('Y-m-d'), 'dibuat_oleh' => $user['id']]);
        $response = $this->withHeaders($headers)->get('api/kas?limit=1&month=' . gmdate('Y-m'));
        $response->assertStatus(200); $body = json_decode($response->getJSON(), true);
        $this->assertCount(1, $body['data']['transaksi']);
        $this->assertSame(200, $body['data']['saldo']);
        $this->assertSame(2, $body['pagination']['total']);
    }

    public function test_announcement_total_excludes_foreign_inactive_and_wrong_role_rows(): void
    {
        $user = $this->createTestUser(101, 'anggota');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        foreach ([[101, 'semua', 1], [101, 'anggota', 1], [101, 'ketua', 1], [102, 'semua', 1], [101, 'semua', 0]] as [$tenant, $role, $active]) {
            $this->db->table('pengumuman')->insert(['karang_taruna_id' => $tenant, 'judul' => 'Synthetic', 'isi' => 'Synthetic',
                'target_role' => $role, 'status_aktif' => $active, 'dibuat_oleh' => $user['id']]);
        }
        $response = $this->withHeaders($headers)->get('api/announcements?limit=1&page=2');
        $response->assertStatus(200); $body = json_decode($response->getJSON(), true);
        $this->assertSame(2, $body['pagination']['total']);
        $this->assertCount(1, $body['data']);
        $this->assertSame('anggota', $body['data'][0]['target_role']);
    }

    public function test_browser_collection_navigation_renders_in_content_and_rejects_zero_limit(): void
    {
        $this->createTestUser(101, 'ketua');
        $hash = password_hash('synthetic-private-admin', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic-admin', 'nama_lengkap' => 'Synthetic', 'password' => $hash]);
        $session = ['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
            'superadmin_credential_version' => hash('sha256', $hash), 'superadmin_nama_lengkap' => 'Synthetic'];
        foreach (['superadmin/karang_taruna', 'superadmin/manage/101/users', 'superadmin/manage/101/events', 'superadmin/manage/101/pengumuman', 'superadmin/manage/101/kas'] as $route) {
            $response = $this->withSession($session)->get($route . '?limit=1');
            $response->assertStatus(200);
            $html = $response->getBody();
            $this->assertStringContainsString('aria-label="Halaman daftar"', $html);
            $this->assertStringNotContainsString('<title><nav', $html);
            $this->withSession($session)->get($route . '?limit=0')->assertStatus(422);
        }
    }

    public function test_wheel_result_pages_start_with_latest_results_and_retain_ascending_display_order(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        $this->db->table('wheel_sessions')->insert(['karang_taruna_id' => 101, 'created_by_user_id' => $user['id'],
            'title' => 'Synthetic', 'source_type' => 'custom', 'status' => 'closed', 'spin_duration_seconds' => 10]);
        $id = $this->db->insertID();
        $this->db->table('wheel_items')->insert(['session_id' => $id, 'label_snapshot' => 'Synthetic', 'is_active' => 1]);
        $item = $this->db->insertID(); $rows = [];
        for ($i = 1; $i <= 105; $i++) $rows[] = ['session_id' => $id, 'wheel_item_id' => $item,
            'result_label_snapshot' => 'Synthetic', 'spin_sequence' => $i, 'duration_seconds' => 10, 'started_at' => gmdate('Y-m-d H:i:s')];
        $this->db->table('wheel_results')->insertBatch($rows);
        foreach ([1 => [6, 105, 100], 2 => [1, 5, 5]] as $page => [$first, $last, $count]) {
            $response = $this->withHeaders($headers)->get('api/wheels/' . $id . '?page=' . $page);
            $response->assertStatus(200); $data = json_decode($response->getJSON(), true)['data'];
            $this->assertCount($count, $data['results']);
            $this->assertSame($first, (int)$data['results'][0]['spin_sequence']);
            $this->assertSame($last, (int)$data['results'][$count - 1]['spin_sequence']);
            $this->assertSame(105, $data['results_pagination']['total']);
            $this->assertCount(1, $data['items']);
        }
    }
}
