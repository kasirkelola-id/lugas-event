<?php

namespace Tests\Api;

use App\Services\NotificationWorker;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class WorkerTransportProbe extends \App\Services\PushTransport
{
    public array $calls = [];
    public $handler;
    public function send($tokens, $title, $body, $data = [])
    {
        $this->calls[] = ['tokens' => $tokens, 'data' => $data, 'depth' => \Config\Database::connect()->transDepth];
        return $this->handler ? ($this->handler)($tokens[0]) : true;
    }
}

final class NotificationWorkerTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    private function fixture(int $devices = 1): array
    {
        $sender = $this->createTestUser(101, 'ketua');
        $receiver = $this->createTestUser(101);
        $token = $this->generateTokenForUser($receiver);
        $tokenId = $this->db->table('user_tokens')->where('token_hash', hash('sha256', $token))->get()->getRowArray()['id'];
        for ($i = 1; $i <= $devices; $i++) $this->db->table('user_devices')->insert([
            'user_id' => $receiver['id'], 'user_token_id' => $tokenId, 'fcm_token' => 'synthetic-device-' . $i,
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->table('chats')->insert(['karang_taruna_id' => 101, 'sender_id' => $sender['id'], 'receiver_id' => $receiver['id'],
            'type' => 'private', 'message' => 'Synthetic', 'created_at' => gmdate('Y-m-d H:i:s')]);
        return [$sender, $receiver];
    }

    private function due(): void
    {
        $this->db->table('notification_jobs')->where('status', 'pending')->update(['next_attempt_at' => '2000-01-01 00:00:00']);
        $this->db->table('notification_deliveries')->where('status', 'pending')->update(['next_attempt_at' => '2000-01-01 00:00:00']);
    }

    public function test_job_is_atomic_with_domain_write_and_rollback(): void
    {
        [$sender, $receiver] = $this->fixture();
        $before = $this->db->table('notification_jobs')->countAllResults();
        $this->db->transBegin();
        $this->db->table('chats')->insert(['karang_taruna_id' => 101, 'sender_id' => $sender['id'], 'receiver_id' => $receiver['id'],
            'type' => 'private', 'message' => 'Rollback', 'created_at' => gmdate('Y-m-d H:i:s')]);
        $this->assertSame($before + 1, $this->db->table('notification_jobs')->countAllResults());
        $this->db->transRollback();
        $this->assertSame($before, $this->db->table('notification_jobs')->countAllResults());
        $this->assertSame(1, $this->db->table('chats')->countAllResults());
    }

    public function test_enqueue_failure_rolls_back_chat_insert_and_returns_controlled_error(): void
    {
        [$sender, $receiver] = $this->fixture();
        $token = $this->generateTokenForUser($sender);
        $before = $this->db->table('chats')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER queue_failure BEFORE INSERT ON notification_jobs BEGIN SELECT RAISE(ABORT, 'synthetic-private'); END");
        try {
            $response = $this->withHeaders($this->getAuthHeaders($token) + ['X-Karang-Taruna-ID' => '101'])->withBodyFormat('json')
                ->post('api/chats/messages', ['type' => 'private', 'receiver_id' => $receiver['id'], 'message' => 'Not stored']);
            $response->assertStatus(500);
            $this->assertSame($before, $this->db->table('chats')->get()->getResultArray());
            $this->assertStringNotContainsString('synthetic-private', $response->getJSON());
        } finally {
            $this->db->query('DROP TRIGGER queue_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_worker_sends_after_commit_and_completed_job_is_not_reprocessed(): void
    {
        $this->fixture(); $probe = new WorkerTransportProbe();
        $result = (new NotificationWorker($probe))->runOne();
        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $probe->calls[0]['depth']);
        $this->assertSame('101', $probe->calls[0]['data']['tenant_id']);
        $this->assertSame('completed', $this->db->table('notification_jobs')->get()->getRowArray()['status']);
        $this->assertSame(0, (new NotificationWorker($probe))->runOne()['claimed']);
        $this->assertCount(1, $probe->calls);
    }

    public function test_partial_failure_retries_only_failed_device_and_terminates_after_five(): void
    {
        $this->fixture(2); $probe = new WorkerTransportProbe();
        $probe->handler = static fn($token) => $token === 'synthetic-device-1';
        $worker = new NotificationWorker($probe);
        $worker->runOne();
        for ($i = 1; $i < 5; $i++) { $this->due(); $worker->runOne(); }
        $deliveries = $this->db->table('notification_deliveries')->orderBy('device_id')->get()->getResultArray();
        $this->assertSame(['sent', 'failed'], array_column($deliveries, 'status'));
        $this->assertSame([1, 5], array_map('intval', array_column($deliveries, 'attempts')));
        $this->assertSame('failed', $this->db->table('notification_jobs')->get()->getRowArray()['status']);
        $this->assertCount(6, $probe->calls);
        $this->assertNotNull($deliveries[1]['completed_at']);
    }

    public function test_invalid_registration_is_terminal_and_not_retried(): void
    {
        $this->fixture(); $probe = new WorkerTransportProbe();
        $probe->handler = function ($token) { $this->db->table('user_devices')->where('fcm_token', $token)->delete(); return false; };
        (new NotificationWorker($probe))->runOne();
        $this->assertSame('invalid', $this->db->table('notification_deliveries')->get()->getRowArray()['status']);
        $this->assertSame('completed', $this->db->table('notification_jobs')->get()->getRowArray()['status']);
        $this->assertSame(0, (new NotificationWorker($probe))->runOne()['claimed']);
    }

    public function test_recipient_revocation_between_jobs_suppresses_pending_delivery(): void
    {
        [, $receiver] = $this->fixture(2); $probe = new WorkerTransportProbe();
        (new NotificationWorker($probe))->runOne(1);
        $this->db->table('organization_members')->where('user_id', $receiver['id'])->update(['approval_status' => 'rejected']);
        (new NotificationWorker($probe))->runOne();
        $this->assertCount(1, $probe->calls);
        $this->assertSame(['sent', 'suppressed'], array_column($this->db->table('notification_deliveries')->orderBy('id')->get()->getResultArray(), 'status'));
    }

    public function test_batches_expand_at_most_one_hundred_and_send_at_most_five(): void
    {
        $this->fixture(101); $probe = new WorkerTransportProbe();
        $worker = new NotificationWorker($probe);
        $worker->runOne(999);
        $this->assertCount(5, $probe->calls);
        $this->assertSame(100, $this->db->table('notification_deliveries')->countAllResults());
        $this->assertSame(0, (int)$this->db->table('notification_jobs')->get()->getRowArray()['expanded']);
        $worker->runOne();
        $this->assertCount(10, $probe->calls);
        $this->assertSame(101, $this->db->table('notification_deliveries')->countAllResults());
    }

    public function test_live_lease_is_not_stolen_and_expired_lease_is_recovered(): void
    {
        $this->fixture(); $probe = new WorkerTransportProbe();
        $this->db->table('notification_jobs')->update(['status' => 'processing', 'lease_token' => str_repeat('a', 32), 'lease_expires_at' => '2099-01-01 00:00:00']);
        $worker = new NotificationWorker($probe);
        $this->assertSame(0, $worker->runOne()['claimed']);
        $this->db->table('notification_jobs')->update(['lease_expires_at' => '2000-01-01 00:00:00']);
        $this->assertSame(1, $worker->runOne()['sent']);
        $this->assertCount(1, $probe->calls);
    }

    public function test_fanout_retries_are_finite_without_resending_successful_fcm(): void
    {
        $this->fixture(); $probe = new WorkerTransportProbe();
        $fanout = $this->getMockBuilder(\App\Services\ChatFanout::class)->onlyMethods(['send'])->getMock();
        $fanout->expects($this->exactly(5))->method('send')->willReturn(false);
        $worker = new NotificationWorker($probe, $fanout);
        for ($i = 0; $i < 5; $i++) {
            $this->due();
            $this->db->table('notification_jobs')->update(['fanout_next_attempt_at' => '2000-01-01 00:00:00']);
            $worker->runOne();
        }
        $job = $this->db->table('notification_jobs')->get()->getRowArray();
        $this->assertSame('failed', $job['status']); $this->assertSame(5, (int)$job['fanout_attempts']);
        $this->assertCount(1, $probe->calls);
    }

    public function test_crash_recorded_fifth_attempt_does_not_send_a_sixth_time(): void
    {
        $this->fixture(2); $probe = new WorkerTransportProbe();
        $worker = new NotificationWorker($probe);
        $worker->runOne(1);
        $this->db->table('notification_deliveries')->where('status', 'pending')->update(['attempts' => 5]);
        $worker->runOne();
        $this->assertCount(1, $probe->calls);
        $this->assertSame('failed', $this->db->table('notification_jobs')->get()->getRowArray()['status']);
    }

    public function test_event_and_announcement_envelopes_retain_permission_and_role_scope(): void
    {
        [$sender] = $this->fixture();
        $this->db->table('events')->insert(['karang_taruna_id' => 101, 'nama_acara' => 'Synthetic event', 'tanggal_acara' => gmdate('Y-m-d'),
            'kode_qr' => 'synthetic-event', 'dibuat_oleh' => $sender['id'], 'status_aktif' => 'aktif']);
        $this->db->table('pengumuman')->insert(['karang_taruna_id' => 101, 'judul' => 'Synthetic notice', 'isi' => 'Synthetic',
            'dibuat_oleh' => $sender['id'], 'status_aktif' => 1, 'target_role' => 'anggota']);
        $jobs = $this->db->table('notification_jobs')->orderBy('id')->get()->getResultArray();
        $event = \App\Services\DomainNotification::envelope($jobs[1]);
        $notice = \App\Services\DomainNotification::envelope($jobs[2]);
        $this->assertSame('event.view', $event['selector']['permission']);
        $this->assertSame('announcement.view', $notice['selector']['permission']);
        $this->assertSame('anggota', $notice['selector']['role']);
        $this->assertSame('101', $notice['data']['tenant_id']);
        $this->db->table('pengumuman')->where('id', $jobs[2]['entity_id'])->update(['status_aktif' => 0]);
        $this->assertNull(\App\Services\DomainNotification::envelope($jobs[2]));
    }
}
