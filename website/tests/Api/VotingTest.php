<?php

namespace Tests\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

class VotingTest extends \Tests\Support\BaseTest
{
    protected $migrateOnce = true;
    protected $refresh = false;
    use FeatureTestTrait;
    use AuthTrait;
    
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        
        $db = \Config\Database::connect();
        if ($db->DBDriver === 'SQLite3') {
            try {
                $refClass = new \ReflectionClass($db);
                $prop = $refClass->getProperty('transFailure');
                $prop->setAccessible(true);
                $prop->setValue($db, false);

                $propStatus = $refClass->getProperty('transStatus');
                $propStatus->setAccessible(true);
                $propStatus->setValue($db, true);
            } catch (\Exception $e) {}

            if (!$db->tableExists('votings')) {
                echo "TABLE VOTINGS DOES NOT EXIST!\n";
            } else if (!$db->fieldExists('waktu_mulai', 'votings')) {
                $db->query("ALTER TABLE votings ADD COLUMN waktu_mulai DATETIME DEFAULT NULL");
                $db->query("ALTER TABLE votings ADD COLUMN waktu_selesai DATETIME DEFAULT NULL");
            }
        }
    }

    public function testVotingValidation()
    {
        $tenantId = 1;
        $ketua = $this->createTestUser($tenantId, 'ketua', 'ketuaVotingVal');
        $token = $this->generateTokenForUser($ketua);

        // Invalid: start >= end
        $payload = [
            'title' => 'Pemilihan Invalid',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'options' => ['A', 'B']
        ];

        $req = $this->withHeaders($this->getAuthHeaders($token))
                    ->withBodyFormat('json')
                    ->post('api/votings', $payload);
        $req->assertStatus(422);
        
        $json = json_decode($req->getJSON(), true);
        $this->assertArrayHasKey('waktu_selesai', $json['errors']);
    }

    public function testVotingLifecycle()
    {
        $tenantId = 1;
        $ketua = $this->createTestUser($tenantId, 'ketua', 'ketuaVotingLife');
        $token = $this->generateTokenForUser($ketua);

        $member = $this->createTestUser($tenantId, 'anggota', 'memberVotingLife');
        $memberToken = $this->generateTokenForUser($member);

        // Create Scheduled Voting
        $payloadScheduled = [
            'title' => 'Pemilihan Nanti',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'options' => ['Opsi 1', 'Opsi 2']
        ];
        $req = $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->post('api/votings', $payloadScheduled);
        $req->assertStatus(201);

        $votingModel = new \App\Models\VotingModel();
        $votingScheduledId = $votingModel->orderBy('id', 'DESC')->first()['id'];
        
        // Fetch and verify status = scheduled
        $reqGet = $this->withHeaders($this->getAuthHeaders($memberToken))->get('api/votings/' . $votingScheduledId);
        $json = json_decode($reqGet->getJSON(), true);
        $this->assertEquals('scheduled', $json['data']['status']);

        // Attempt to vote should fail
        $optionId = $json['data']['options'][0]['id'];
        $reqVote = $this->withHeaders($this->getAuthHeaders($memberToken))->withBodyFormat('json')->post('api/votings/' . $votingScheduledId . '/vote', ['option_id' => $optionId]);
        $reqVote->assertStatus(400);

        // Create Active Voting
        $payloadActive = [
            'title' => 'Pemilihan Sekarang',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'options' => ['Opsi A', 'Opsi B']
        ];
        $req = $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->post('api/votings', $payloadActive);
        $req->assertStatus(201);
        $votingActiveId = $votingModel->orderBy('id', 'DESC')->first()['id'];

        // Fetch active
        $reqGet = $this->withHeaders($this->getAuthHeaders($memberToken))->get('api/votings/' . $votingActiveId);
        $json = json_decode($reqGet->getJSON(), true);
        $this->assertEquals('active', $json['data']['status']);
        $this->assertNull($json['data']['total_votes']); // No tally

        // Vote successfully
        $optionIdActive = $json['data']['options'][0]['id'];
        $reqVote = $this->withHeaders($this->getAuthHeaders($memberToken))->withBodyFormat('json')->post('api/votings/' . $votingActiveId . '/vote', ['option_id' => $optionIdActive]);
        $reqVote->assertStatus(200);

        // Vote duplicate rejected
        $reqVote2 = $this->withHeaders($this->getAuthHeaders($memberToken))->withBodyFormat('json')->post('api/votings/' . $votingActiveId . '/vote', ['option_id' => $optionIdActive]);
        $reqVote2->assertStatus(400);

        // Create Ended Voting
        $payloadEnded = [
            'title' => 'Pemilihan Kemarin',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'options' => ['X', 'Y']
        ];
        $req = $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->post('api/votings', $payloadEnded);
        $req->assertStatus(201);
        $votingEndedId = $votingModel->orderBy('id', 'DESC')->first()['id'];

        $reqGet = $this->withHeaders($this->getAuthHeaders($memberToken))->get('api/votings/' . $votingEndedId);
        $json = json_decode($reqGet->getJSON(), true);
        $this->assertEquals('ended', $json['data']['status']);
        $this->assertNotNull($json['data']['total_votes']); // Tally visible

        // Vote on ended should fail
        $optionIdEnded = $json['data']['options'][0]['id'];
        $reqVote = $this->withHeaders($this->getAuthHeaders($memberToken))->withBodyFormat('json')->post('api/votings/' . $votingEndedId . '/vote', ['option_id' => $optionIdEnded]);
        $reqVote->assertStatus(400);
    }

    public function testOptionIsolation()
    {
        $tenantA = 101;
        $tenantB = 102;
        $ketuaA = $this->createTestUser($tenantA, 'ketua', 'ketuaVotingIsoA');
        $tokenA = $this->generateTokenForUser($ketuaA);
        
        $ketuaB = $this->createTestUser($tenantB, 'ketua', 'ketuaVotingIsoB');
        $tokenB = $this->generateTokenForUser($ketuaB);

        $reqA = $this->withHeaders($this->getAuthHeaders($tokenA))->withBodyFormat('json')->post('api/votings', [
            'title' => 'Pemilihan A',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'options' => ['A1', 'A2']
        ]);
        $reqA->assertStatus(201);
        
        $votingModel = new \App\Models\VotingModel();
        $votingA = $votingModel->orderBy('id', 'DESC')->first()['id'];

        // Voting Tenant B
        $reqB = $this->withHeaders($this->getAuthHeaders($tokenB))->withBodyFormat('json')->post('api/votings', [
            'title' => 'Pemilihan B',
            'waktu_mulai' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'options' => ['B1', 'B2']
        ]);
        $reqB->assertStatus(201);
        $votingB = $votingModel->orderBy('id', 'DESC')->first()['id'];

        // Get Option ID from Voting B
        $reqGetB = $this->withHeaders($this->getAuthHeaders($tokenB))->get('api/votings/' . $votingB);
        $jsonB = json_decode($reqGetB->getJSON(), true);
        $optionIdB = $jsonB['data']['options'][0]['id'];

        // Tenant A member tries to vote in Voting A using Option ID from Voting B
        $memberA = $this->createTestUser($tenantA, 'anggota', 'memberVotingIsoA');
        $tokenMemberA = $this->generateTokenForUser($memberA);

        $reqVote = $this->withHeaders($this->getAuthHeaders($tokenMemberA))
                        ->withBodyFormat('json')
                        ->post('api/votings/' . $votingA . '/vote', ['option_id' => $optionIdB]);
        $reqVote->assertStatus(404); // Not found because option_id doesn't belong to voting A
    }
}
