<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class SessionRevocationTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
    }

    public function testSelfChangeRevokesOtherDevicesButKeepsCurrentToken(): void
    {
        $user = $this->createTestUser(101);
        $one = $this->generateTokenForUser($user);
        $two = $this->generateTokenForUser($user);
        $otherUser = $this->createTestUser(101);
        $otherToken = $this->generateTokenForUser($otherUser);
        $this->withHeaders($this->getAuthHeaders($one))->withBodyFormat('json')->post('api/profile/password',
            ['new_password' => 'private-new-passphrase', 'confirm_password' => 'private-new-passphrase'])->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($one))->get('api/me')->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($two))->get('api/me')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders($two) + ['X-Internal-Secret' => 'synthetic-only', 'X-Karang-Taruna-ID' => '101'])
            ->post('api/internal/socket-auth')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders($otherToken))->get('api/me')->assertStatus(200);
    }

    public function testAdminResetRevokesAllTargetDevicesAndLeavesAdminAlone(): void
    {
        $admin = $this->createTestUser(101, 'ketua');
        $target = $this->createTestUser(101);
        $adminToken = $this->generateTokenForUser($admin);
        $tokens = [$this->generateTokenForUser($target), $this->generateTokenForUser($target)];
        $this->withHeaders($this->getAuthHeaders($adminToken))->post('api/users/' . $target['id'] . '/reset-password')->assertStatus(200);
        foreach ($tokens as $token) $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders($adminToken))->get('api/me')->assertStatus(200);
    }

    public function testPrivilegedBearerAndBrowserSessionTrackCurrentCredential(): void
    {
        $this->createTestUser(101);
        $hash = password_hash('private-admin-passphrase', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['username' => 'synthetic_admin', 'nama_lengkap' => 'Synthetic Admin', 'password' => $hash]);
        $id = $this->db->insertID();
        $login = $this->withBodyFormat('json')->post('api/login', ['karang_taruna_id' => 101, 'username' => 'synthetic_admin', 'password' => 'private-admin-passphrase']);
        $login->assertStatus(200);
        $token = json_decode($login->getJSON(), true)['data']['token'];
        $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(200);
        $browser = ['is_superadmin_logged_in' => true, 'superadmin_id' => $id,
            'superadmin_credential_version' => hash('sha256', $hash)];
        $this->withSession($browser)->get('superadmin/dashboard')->assertStatus(200);
        $this->db->table('superadmins')->where('id', $id)->update(['password' => password_hash('changed-admin-passphrase', PASSWORD_BCRYPT)]);
        $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
        $this->withSession($browser)->get('superadmin/dashboard')->assertRedirectTo('/superadmin/login');
        $this->assertFalse((bool)session()->get('is_superadmin_logged_in'));
    }

    public function testUnboundLegacyPrivilegedTokenAndBrowserFlagFailClosed(): void
    {
        $this->createTestUser(101);
        $token = bin2hex(random_bytes(32));
        $this->db->table('user_tokens')->insert(['user_id' => null, 'karang_taruna_id' => 101,
            'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
        $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1])
            ->get('superadmin/dashboard')->assertRedirectTo('/superadmin/login');
    }

    public function testWebResetRevokesEveryTargetToken(): void
    {
        $user = $this->createTestUser(101);
        $tokens = [$this->generateTokenForUser($user), $this->generateTokenForUser($user)];
        $hash = password_hash('private-admin-passphrase', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['username' => 'synthetic_admin', 'nama_lengkap' => 'Synthetic Admin', 'password' => $hash]);
        $id = $this->db->insertID();
        \Config\Services::resetSingle('security');
        $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => $id,
            'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('d', 64)])
            ->withHeaders(['X-CSRF-TOKEN' => str_repeat('d', 64)])
            ->post('superadmin/manage/101/users/' . $user['id'] . '/reset-password')->assertStatus(200);
        foreach ($tokens as $token) $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
    }

    public function testRevocationFailureRollsBackCredentialAndFlag(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->generateTokenForUser($user);
        $this->generateTokenForUser($user);
        $before = $this->db->table('users')->where('id', $user['id'])->get()->getRowArray();
        $beforeTokens = $this->db->table('user_tokens')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER abort_token_revocation BEFORE UPDATE ON user_tokens BEGIN SELECT RAISE(ABORT, 'synthetic revocation failure'); END");
        try {
            $result = $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->post('api/profile/password',
                ['new_password' => 'private-new-passphrase', 'confirm_password' => 'private-new-passphrase']);
            $result->assertStatus(500);
            $this->assertStringNotContainsString('synthetic revocation failure', $result->getJSON());
            $this->assertSame($before, $this->db->table('users')->where('id', $user['id'])->get()->getRowArray());
            $this->assertSame($beforeTokens, $this->db->table('user_tokens')->get()->getResultArray());
        } finally {
            $this->db->query('DROP TRIGGER abort_token_revocation');
            $this->db->resetTransStatus();
        }
    }

    public function testStaleCredentialCannotOverwriteOrRevokeSessions(): void
    {
        $user = $this->createTestUser(101);
        $this->generateTokenForUser($user);
        $before = $this->db->table('users')->where('id', $user['id'])->get()->getRowArray();
        $this->assertFalse(\App\Services\CredentialSessionService::replace((int)$user['id'], 'stale hash', password_hash('new-passphrase', PASSWORD_BCRYPT), true));
        $this->assertSame($before, $this->db->table('users')->where('id', $user['id'])->get()->getRowArray());
        $this->assertSame(1, $this->db->table('user_tokens')->where('revoked_at', null)->countAllResults());
    }
}
