<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

class AbuseControlTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    public function testRegistrationQuotaAppliesBeforeValidation(): void
    {
        $namespace = bin2hex(random_bytes(16));
        for ($i = 0; $i < 6; $i++) {
            $this->withHeaders(['X-RateLimit-Test' => $namespace])->withBodyFormat('json')
                ->post('api/register', [])->assertStatus($i < 5 ? 422 : 429);
        }
    }

    public function testPasswordQuotaIsSharedAcrossDevicesAndMethods(): void
    {
        $user = $this->createTestUser(101);
        $tokens = [$this->generateTokenForUser($user), $this->generateTokenForUser($user)];
        $namespace = bin2hex(random_bytes(16));
        for ($i = 0; $i < 6; $i++) {
            $this->withHeaders($this->getAuthHeaders($tokens[$i % 2]) + ['X-RateLimit-Test' => $namespace])
                ->withBodyFormat('json')->call($i % 2 ? 'PATCH' : 'POST', 'api/profile/password', [])
                ->assertStatus($i < 5 ? 422 : 429);
        }
    }

    public function testWheelRejectsOversizedAndNonArrayInputsBeforeWrite(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $token = $this->generateTokenForUser($user);
        foreach ([array_fill(0, 1001, 'Synthetic'), 'invalid'] as $items) {
            $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')
                ->post('api/wheels', ['title' => 'Synthetic', 'source_type' => 'custom', 'items' => $items])
                ->assertStatus(422);
        }
        $this->assertSame(0, \Config\Database::connect()->table('wheel_sessions')->countAllResults());
    }

    public function testRepeatedMemberIdsCannotBypassRawArrayCap(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $token = $this->generateTokenForUser($user);
        $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')
            ->post('api/chats/rooms', ['name' => 'Synthetic', 'members' => array_fill(0, 101, $user['id'])])
            ->assertStatus(422);
    }

    public function testVotingOptionsAreBoundedBeforeWrite(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $token = $this->generateTokenForUser($user);
        $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')
            ->post('api/votings', ['title' => 'Synthetic', 'options' => array_fill(0, 51, 'Choice'),
                'waktu_mulai' => date('Y-m-d H:i:s'), 'waktu_selesai' => date('Y-m-d H:i:s', time() + 3600)])
            ->assertStatus(422);
        $this->assertSame(0, \Config\Database::connect()->table('votings')->countAllResults());
    }
}
