<?php

namespace Tests\Api;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class QueryBatchingTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    public static function collections(): array { return [['events', 'absensi'], ['votings', 'voting_votes'], ['wheels', 'wheel_items']]; }

    #[DataProvider('collections')]
    public function test_child_query_count_does_not_grow_with_page_size(string $route, string $child): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        for ($i = 0; $i < 20; $i++) {
            if ($route === 'events') {
                $this->db->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Synthetic', 'tanggal_acara' => gmdate('Y-m-d'),
                    'kode_qr' => 'batch-' . $i, 'dibuat_oleh' => $user['id'], 'status_aktif' => 'aktif']);
                $id = $this->db->insertID();
                $this->db->table('absensi')->insert(['karang_taruna_id' => 101, 'event_id' => $id, 'user_id' => $user['id'], 'waktu_absen' => gmdate('Y-m-d H:i:s')]);
            } elseif ($route === 'votings') {
                $this->db->table('votings')->insert(['karang_taruna_id' => 101, 'title' => 'Synthetic', 'created_by' => $user['id'], 'status' => 'closed',
                    'waktu_mulai' => '2000-01-01 00:00:00', 'waktu_selesai' => '2000-01-02 00:00:00']);
                $id = $this->db->insertID();
                $this->db->table('voting_options')->insert(['voting_id' => $id, 'option_name' => 'Synthetic']); $option = $this->db->insertID();
                $this->db->table('voting_votes')->insert(['voting_id' => $id, 'option_id' => $option, 'user_id' => $user['id']]);
            } else {
                $this->db->table('wheel_sessions')->insert(['karang_taruna_id' => 101, 'title' => 'Synthetic', 'created_by_user_id' => $user['id'],
                    'source_type' => 'custom', 'status' => 'closed', 'spin_duration_seconds' => 10]);
                $id = $this->db->insertID();
                $this->db->table('wheel_items')->insert(['session_id' => $id, 'label_snapshot' => 'Synthetic', 'is_active' => 1]);
            }
        }
        $counts = [];
        foreach ([1, 20] as $limit) {
            $count = 0;
            $listener = static function ($query) use (&$count, $child): void {
                if (preg_match('/\bFROM\s+[`"]?' . $child . '\b/i', $query->getOriginalQuery())) $count++;
            };
            Events::on('DBQuery', $listener);
            try {
                $response = $this->withHeaders($headers)->get('api/' . $route . '?limit=' . $limit);
                $response->assertStatus(200); $rows = json_decode($response->getJSON(), true)['data'];
            } finally { Events::removeListener('DBQuery', $listener); }
            $this->assertCount($limit, $rows);
            foreach ($rows as $row) {
                if ($route === 'events') $this->assertSame(1, $row['jumlah_hadir']);
                elseif ($route === 'votings') { $this->assertTrue($row['has_voted']); $this->assertSame(1, $row['total_votes']); }
                else $this->assertSame(1, $row['item_count']);
            }
            $counts[] = $count;
        }
        $this->assertGreaterThan(0, $counts[0], 'The query listener must observe the real child reads');
        $this->assertLessThanOrEqual($counts[0] + 1, $counts[1], 'Child query count grew with collection size');
    }

    public function test_voting_result_counts_use_one_group_and_preserve_percentages(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user)) + ['X-Karang-Taruna-ID' => '101'];
        $this->db->table('votings')->insert(['karang_taruna_id' => 101, 'title' => 'Synthetic', 'created_by' => $user['id'], 'status' => 'closed',
            'waktu_mulai' => '2000-01-01 00:00:00', 'waktu_selesai' => '2000-01-02 00:00:00']);
        $id = $this->db->insertID();
        for ($i = 0; $i < 20; $i++) {
            $this->db->table('voting_options')->insert(['voting_id' => $id, 'option_name' => 'Synthetic ' . $i]);
            if ($i === 0) $option = $this->db->insertID();
        }
        $this->db->table('voting_votes')->insert(['voting_id' => $id, 'option_id' => $option, 'user_id' => $user['id']]);
        $count = 0;
        $listener = static function ($query) use (&$count): void {
            if (preg_match('/\bFROM\s+[`"]?voting_votes\b/i', $query->getOriginalQuery())) $count++;
        };
        Events::on('DBQuery', $listener);
        try { $response = $this->withHeaders($headers)->get('api/votings/' . $id); }
        finally { Events::removeListener('DBQuery', $listener); }
        $response->assertStatus(200); $data = json_decode($response->getJSON(), true)['data'];
        $this->assertSame(2, $count);
        $this->assertSame(1, $data['total_votes']);
        $this->assertSame(100, $data['options'][0]['percentage']);
        $this->assertSame(0, $data['options'][19]['vote_count']);
    }

    private function participantFixture(): array
    {
        $admin = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($admin)) + ['X-Karang-Taruna-ID' => '101'];
        $this->db->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Synthetic', 'tanggal_acara' => gmdate('Y-m-d'),
            'kode_qr' => 'synthetic-batch', 'dibuat_oleh' => $admin['id'], 'status_aktif' => 'aktif']);
        $event = $this->db->insertID(); $ids = [];
        for ($i = 0; $i < 50; $i++) {
            $this->db->table('users')->insert(['username' => 'synthetic-batch-' . $i, 'nama_lengkap' => 'Synthetic', 'password' => '', 'status_aktif' => 1]);
            $id = $this->db->insertID(); $ids[] = $id;
            $this->db->table('organization_members')->insert(['user_id' => $id, 'karang_taruna_id' => $i === 49 ? 102 : 101,
                'username' => 'synthetic-batch-' . $i, 'role_level' => 'anggota', 'status_aktif' => 1, 'approval_status' => $i === 48 ? 'pending' : 'approved']);
        }
        return [$headers, $event, $ids];
    }

    public function test_participant_batch_is_scoped_deduplicated_and_uses_bounded_queries(): void
    {
        [$headers, $event, $ids] = $this->participantFixture();
        $count = 0;
        $listener = static function ($query) use (&$count): void {
            if (preg_match('/\bFROM\s+[`"]?(organization_members|event_participants)\b/i', $query->getOriginalQuery())) $count++;
        };
        Events::on('DBQuery', $listener);
        try {
            $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/events/' . $event . '/participants', ['user_ids' => [...$ids, $ids[0]], 'karang_taruna_id' => 102]);
        } finally { Events::removeListener('DBQuery', $listener); }
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $count);
        $this->assertSame(48, $this->db->table('event_participants')->where('karang_taruna_id', 101)->countAllResults());
        $this->assertSame(0, $this->db->table('event_participants')->where('karang_taruna_id', 102)->countAllResults());
        $this->withHeaders($headers)->withBodyFormat('json')->post('api/events/' . $event . '/participants', ['user_ids' => $ids])->assertStatus(200);
        $this->assertSame(48, $this->db->table('event_participants')->countAllResults());
    }

    public function test_participant_batch_failure_restores_all_rows_and_returns_controlled_error(): void
    {
        [$headers, $event, $ids] = $this->participantFixture();
        $this->db->query('CREATE TRIGGER fail_participant_batch BEFORE INSERT ON event_participants WHEN NEW.user_id = ' . (int)$ids[10] . " BEGIN SELECT RAISE(ABORT, 'synthetic-private'); END");
        try {
            $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/events/' . $event . '/participants', ['user_ids' => $ids]);
            $response->assertStatus(500);
            $this->assertStringNotContainsString('synthetic-private', $response->getJSON());
            $this->assertSame(0, $this->db->table('event_participants')->countAllResults());
            $this->assertSame(0, $this->db->transDepth);
        } finally { $this->db->query('DROP TRIGGER fail_participant_batch'); $this->db->resetTransStatus(); }
    }
}
