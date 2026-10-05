<?php

namespace Tests\Api;

use App\Services\ChatCleanupService;
use App\Services\MaintenanceLock;
use App\Services\OrphanUploadReport;
use App\Services\SessionCleanupService;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class MaintenanceSafetyTest extends BaseTest
{
    use AuthTrait;
    protected $namespace = 'App';

    private function chats(int $count): void
    {
        $user = $this->createTestUser(101);
        for ($i = 0; $i < $count; $i++) $this->db->table('chats')->insert(['karang_taruna_id' => 101,
            'sender_id' => $user['id'], 'type' => 'group', 'message' => 'Synthetic', 'created_at' => '2000-01-01 00:00:00']);
    }

    public function test_chat_lock_excludes_overlap_and_bounded_pass_can_resume(): void
    {
        $this->chats(25);
        $lock = MaintenanceLock::acquire('chat:' . $this->db->DBDriver . ':' . $this->db->getDatabase());
        $this->assertNotNull($lock);
        try {
            $result = (new ChatCleanupService($this->db))->deleteExpired(null, 2);
            $this->assertTrue($result['busy']);
            $this->assertSame(25, $this->db->table('chats')->countAllResults());
        } finally { $lock->release(); }
        $first = (new ChatCleanupService($this->db))->deleteExpired(null, 2);
        $this->assertSame(20, $first['deleted']); $this->assertTrue($first['bounded_stop']);
        $second = (new ChatCleanupService($this->db))->deleteExpired(null, 2);
        $this->assertSame(5, $second['deleted']); $this->assertFalse($second['bounded_stop']);
        $this->assertSame(0, $this->db->table('chats')->countAllResults());
    }

    public function test_chat_write_failure_preserves_rows_and_releases_lock(): void
    {
        $this->chats(1);
        $this->db->query("CREATE TRIGGER synthetic_cleanup_failure BEFORE DELETE ON chats BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        try {
            try { (new ChatCleanupService($this->db))->deleteExpired(); $this->fail('Failed DELETE was ignored'); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) { $this->assertSame(1, $this->db->table('chats')->countAllResults()); }
        } finally { $this->db->query('DROP TRIGGER synthetic_cleanup_failure'); $this->db->resetTransStatus(); }
        $this->assertSame(1, (new ChatCleanupService($this->db))->deleteExpired()['deleted']);
    }

    public function test_session_dry_run_and_apply_preserve_live_recent_and_unknown_devices(): void
    {
        $user = $this->createTestUser(101);
        $old = gmdate('Y-m-d H:i:s', time() - 40 * 86400);
        $recent = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $future = gmdate('Y-m-d H:i:s', time() + 86400);
        $ids = [];
        foreach ([[$old, null], [$future, null], [$recent, null], [$future, $old]] as $i => [$expires, $revoked]) {
            $this->db->table('user_tokens')->insert(['user_id' => $user['id'], 'token_hash' => hash('sha256', 'synthetic-' . $i), 'expires_at' => $expires, 'revoked_at' => $revoked]);
            $ids[] = $this->db->insertID();
        }
        foreach ([[$ids[0], $old], [$ids[1], $old], [null, $old], [$ids[2], gmdate('Y-m-d H:i:s')], [$ids[0], null], [999999, $old]] as $i => [$binding, $updated]) {
            $this->db->table('user_devices')->insert(['user_id' => $user['id'], 'fcm_token' => 'synthetic-device-' . $i, 'user_token_id' => $binding, 'updated_at' => $updated]);
        }
        $beforeTokens = $this->db->table('user_tokens')->get()->getResultArray();
        $beforeDevices = $this->db->table('user_devices')->get()->getResultArray();
        $dry = (new SessionCleanupService())->run();
        $this->assertSame(2, $dry['tokens_selected']); $this->assertSame(2, $dry['devices_selected']);
        $this->assertSame(0, $dry['tokens_deleted']); $this->assertSame(0, $dry['devices_deleted']);
        $this->assertSame($beforeTokens, $this->db->table('user_tokens')->get()->getResultArray());
        $this->assertSame($beforeDevices, $this->db->table('user_devices')->get()->getResultArray());
        $applied = (new SessionCleanupService())->run(true);
        $this->assertSame(2, $applied['tokens_deleted']); $this->assertSame(2, $applied['devices_deleted']);
        $this->assertSame(['synthetic-device-1', 'synthetic-device-2', 'synthetic-device-3', 'synthetic-device-4'],
            array_column($this->db->table('user_devices')->orderBy('id')->get()->getResultArray(), 'fcm_token'));
        $this->assertSame(0, (new SessionCleanupService())->run(true)['tokens_deleted']);
    }

    public function test_orphan_report_is_read_only_bounded_and_preserves_any_database_reference(): void
    {
        $user = $this->createTestUser(101);
        $directory = FCPATH . 'uploads/users/profile';
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        $paths = [];
        try {
            foreach (['referenced', 'unreferenced', 'recent'] as $label) {
                $relative = 'uploads/users/profile/' . bin2hex(random_bytes(16)) . '.png';
                file_put_contents(FCPATH . $relative, 'Synthetic');
                touch(FCPATH . $relative, time() - ($label === 'recent' ? 0 : 2 * 86400));
                $paths[$label] = $relative;
            }
            $this->db->table('users')->where('id', $user['id'])->update(['profile_photo' => $paths['referenced']]);
            $report = (new OrphanUploadReport())->run();
            $this->assertTrue($report['read_only']); $this->assertSame(0, $report['deleted']);
            $this->assertContains($paths['unreferenced'], $report['candidates']);
            $this->assertNotContains($paths['referenced'], $report['candidates']);
            $this->assertNotContains($paths['recent'], $report['candidates']);
            $this->assertLessThanOrEqual(1, (new OrphanUploadReport())->run(1)['visited']);
            foreach ($paths as $relative) $this->assertFileExists(FCPATH . $relative);
        } finally { foreach ($paths as $relative) if (is_file(FCPATH . $relative)) unlink(FCPATH . $relative); }
    }
}
