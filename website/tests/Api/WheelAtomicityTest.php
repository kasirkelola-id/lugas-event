<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class WheelAtomicityTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
    }

    public function test_auto_close_fanout_happens_only_after_successful_commit(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user));
        $response = $this->withHeaders($headers)->post('api/wheels', ['source_type' => 'custom',
            'title' => 'Synthetic', 'spin_duration_seconds' => 10, 'remove_winner_after_spin' => 1, 'items' => ['A', 'B']]);
        $response->assertStatus(201);
        $id = json_decode($response->getJSON(), true)['session_id'];
        $events = [];
        $client = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $client->method('request')->willReturnCallback(function($method, $url, $options) use (&$events, $id) {
            $this->assertSame('127.0.0.1', parse_url($url, PHP_URL_HOST));
            $events[] = ['event' => $options['json']['event'], 'depth' => $this->db->transDepth,
                'status' => $this->db->table('wheel_sessions')->where('id', $id)->get()->getRowArray()['status']];
            return (new \CodeIgniter\HTTP\Response(config('App')))->setJSON(['status' => true]);
        });
        \Config\Services::injectMock('curlrequest', $client);
        $old = getenv('NODE_SOCKET_URL');
        putenv('NODE_SOCKET_URL=http://127.0.0.1:3000');
        try {
            $this->withHeaders($headers)->post("api/wheels/$id/spin")->assertStatus(200);
            $this->assertCount(2, $events);
            $this->assertSame([0, 0], array_column($events, 'depth'));
            $this->assertSame(['closed', 'closed'], array_column($events, 'status'));
        } finally {
            putenv($old === false ? 'NODE_SOCKET_URL' : 'NODE_SOCKET_URL=' . $old);
        }
    }

    public function test_failed_wheel_items_roll_back_session_creation(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $this->db->query("CREATE TRIGGER wheel_item_failure BEFORE INSERT ON wheel_items BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))->post('api/wheels', [
                'source_type' => 'custom', 'title' => 'Synthetic', 'spin_duration_seconds' => 10, 'items' => ['A', 'B']])->assertStatus(500);
            $this->assertSame(0, $this->db->table('wheel_sessions')->countAllResults());
            $this->assertSame(0, $this->db->table('wheel_items')->countAllResults());
        } finally {
            $this->db->query('DROP TRIGGER wheel_item_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_failed_auto_close_rolls_back_result_and_winner_without_fanout(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user));
        $response = $this->withHeaders($headers)->post('api/wheels', ['source_type' => 'custom',
            'title' => 'Synthetic', 'spin_duration_seconds' => 10, 'remove_winner_after_spin' => 1, 'items' => ['A', 'B']]);
        $id = json_decode($response->getJSON(), true)['session_id'];
        $before = $this->db->table('wheel_items')->get()->getResultArray();
        $client = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $client->expects($this->never())->method('request');
        \Config\Services::injectMock('curlrequest', $client);
        $this->db->query("CREATE TRIGGER wheel_close_failure BEFORE UPDATE ON wheel_sessions WHEN NEW.status = 'closed' BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withHeaders($headers)->post("api/wheels/$id/spin")->assertStatus(500);
            $this->assertSame('active', $this->db->table('wheel_sessions')->where('id', $id)->get()->getRowArray()['status']);
            $this->assertSame($before, $this->db->table('wheel_items')->get()->getResultArray());
            $this->assertSame(0, $this->db->table('wheel_results')->countAllResults());
            $this->assertSame(0, $this->db->transDepth);
        } finally {
            $this->db->query('DROP TRIGGER wheel_close_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_close_is_idempotent_and_later_spin_cannot_mutate(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user));
        $response = $this->withHeaders($headers)->post('api/wheels', ['source_type' => 'custom',
            'title' => 'Synthetic', 'spin_duration_seconds' => 10, 'items' => ['A', 'B']]);
        $id = json_decode($response->getJSON(), true)['session_id'];
        $this->withHeaders($headers)->patch("api/wheels/$id/status")->assertStatus(200);
        $client = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $client->expects($this->never())->method('request');
        \Config\Services::injectMock('curlrequest', $client);
        $this->withHeaders($headers)->patch("api/wheels/$id/status")->assertStatus(200);
        $this->withHeaders($headers)->post("api/wheels/$id/spin")->assertStatus(400);
        $this->assertSame(0, $this->db->table('wheel_results')->countAllResults());
        $this->assertSame(0, $this->db->transDepth);
    }

    public function test_duplicate_failure_rolls_back_new_session_and_items(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user));
        $response = $this->withHeaders($headers)->post('api/wheels', ['source_type' => 'custom',
            'title' => 'Synthetic', 'spin_duration_seconds' => 10, 'items' => ['A', 'B']]);
        $id = json_decode($response->getJSON(), true)['session_id'];
        $beforeSession = $this->db->table('wheel_sessions')->get()->getResultArray();
        $beforeItems = $this->db->table('wheel_items')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER wheel_duplicate_failure BEFORE INSERT ON wheel_items BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withHeaders($headers)->post("api/wheels/$id/duplicate")->assertStatus(500);
            $this->assertSame($beforeSession, $this->db->table('wheel_sessions')->get()->getResultArray());
            $this->assertSame($beforeItems, $this->db->table('wheel_items')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER wheel_duplicate_failure');
            $this->db->resetTransStatus();
        }
    }
}
