<?php

namespace Tests;

use App\Services\CredentialPolicy;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

final class PasswordCompatibilityTest extends \Tests\Support\PasswordFixtureTest
{
    use FeatureTestTrait;
    use AuthTrait;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->getMockBuilder(\CodeIgniter\Log\Logger::class)
            ->disableOriginalConstructor()->onlyMethods(['log'])->getMock();
        $logger->method('log')->willReturnCallback(function ($level, $message, $context): void {
            $this->logs[] = [$level, (string) $message, $context];
        });
        \Config\Services::injectMock('logger', $logger);
    }

    public static function legacyCases(): array
    {
        $cases = [];
        foreach (['ketua', 'admin', 'sekretaris', 'wakil_ketua', 'bendahara', 'pengelola', 'anggota'] as $role) {
            foreach ([4, 6, 8, 12, 24] as $length) $cases[] = [$role, $length];
        }
        return $cases;
    }

    /** @dataProvider legacyCases */
    public function testExistingPasswordsLoginWithoutChangingStoredHash(string $role, int $length): void
    {
        $user = $this->createTestUser(101, $role, 'synthetic_user');
        $password = str_repeat('q', $length);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->db->table('users')->where('id', $user['id'])->update(['password' => $hash]);
        $result = $this->login('synthetic_user', $password);
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertTrue($data['user']['role_level'] === $role);
        $this->assertTrue($hash === $this->db->table('users')->where('id', $user['id'])->get()->getRowArray()['password']);
        $this->withHeaders($this->getAuthHeaders($data['token']))->get('api/me')->assertStatus(200);
        $this->login('synthetic_user', 'incorrect-fixture')->assertStatus(401);
    }

    private function login(string $username, string $password)
    {
        return $this->withHeaders([])->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 101, 'username' => $username, 'password' => $password]);
    }

    public function testUsernameAndFormerDenyListAreAcceptedForExistingHashes(): void
    {
        $user = $this->createTestUser(101, 'anggota', 'synthetic_user');
        foreach (['synthetic_user', 'superadmin123'] as $chosen) {
            $this->db->table('users')->where('id', $user['id'])->update([
                'password' => CredentialPolicy::hashNewPassword($chosen)]);
            $this->login('synthetic_user', $chosen)->assertStatus(200);
        }
    }

    public function testInactiveOrUnapprovedAccountsStillCannotLogin(): void
    {
        $user = $this->createTestUser(101, 'anggota', 'synthetic_user');
        foreach (['pending', 'rejected'] as $approval) {
            $this->db->table('organization_members')->where('user_id', $user['id'])->update(['approval_status' => $approval]);
            $this->login('synthetic_user', 'password123')->assertStatus(401);
        }
        $this->db->table('organization_members')->where('user_id', $user['id'])->update(['approval_status' => 'approved', 'status_aktif' => 0]);
        $this->login('synthetic_user', 'password123')->assertStatus(401);
        $this->db->table('organization_members')->where('user_id', $user['id'])->update(['status_aktif' => 1]);
        $this->db->table('users')->where('id', $user['id'])->update(['status_aktif' => 0]);
        $this->login('synthetic_user', 'password123')->assertStatus(401);
    }

    private function defaultSetting(): void
    {
        // Synthetic setting value, not a replacement for any real application's default.
        $this->db->table('settings')->insert(['karang_taruna_id' => 0,
            'setting_key' => 'temporary_reset_password', 'setting_value' => 'synthetic-default']);
        $this->db->table('settings')->insert(['karang_taruna_id' => 101,
            'setting_key' => 'temporary_reset_password', 'setting_value' => 'tenant-decoy']);
    }

    public function testResetUsesOriginalGlobalSettingOnlyAndRevokesTargetTokens(): void
    {
        $this->defaultSetting();
        $admin = $this->createTestUser(101, 'ketua');
        $target = $this->createTestUser(101, 'anggota', 'synthetic_target');
        $other = $this->createTestUser(101);
        $adminToken = $this->generateTokenForUser($admin);
        $targetToken = $this->generateTokenForUser($target);
        $otherToken = $this->generateTokenForUser($other);
        $settings = $this->db->table('settings')->get()->getResultArray();
        $result = $this->withHeaders($this->getAuthHeaders($adminToken))
            ->post('api/users/' . $target['id'] . '/reset-password');
        $result->assertStatus(200);
        $stored = $this->db->table('users')->where('id', $target['id'])->get()->getRowArray();
        $this->assertTrue(password_verify('synthetic-default', $stored['password']));
        $this->assertFalse(password_verify('password123', $stored['password']));
        $this->assertSame(1, (int) $stored['password_must_change']);
        foreach ($target as $field => $value) {
            if (in_array($field, ['password', 'password_must_change'], true)) continue;
            $this->assertEquals($value, $stored[$field]);
        }
        $this->assertTrue($other['password'] === $this->db->table('users')->where('id', $other['id'])->get()->getRowArray()['password']);
        $this->assertSame($settings, $this->db->table('settings')->get()->getResultArray());
        $this->assertFalse(str_contains($result->getJSON(), 'synthetic-default'));
        $this->assertFalse(str_contains(json_encode($this->logs), 'synthetic-default'));
        $this->assertFalse(str_contains(json_encode($this->logs), $stored['password']));
        $this->withHeaders($this->getAuthHeaders($targetToken))->get('api/me')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders($otherToken))->get('api/me')->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($adminToken))->get('api/me')->assertStatus(200);
        $this->login('synthetic_target', 'password123')->assertStatus(401);
        $this->login('synthetic_target', 'synthetic-default')->assertStatus(200);
    }

    public function testMissingDefaultFailsWithoutChangingAnyAccount(): void
    {
        $admin = $this->createTestUser(101, 'ketua');
        $target = $this->createTestUser(101);
        $token = $this->generateTokenForUser($admin);
        $before = $this->db->table('users')->get()->getResultArray();
        $this->withHeaders($this->getAuthHeaders($token))->post('api/users/' . $target['id'] . '/reset-password')->assertStatus(500);
        $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
    }

    public function testResetPermissionAndTenantBoundaryRemainEnforced(): void
    {
        $this->defaultSetting();
        $ordinary = $this->createTestUser(101, 'anggota');
        $admin = $this->createTestUser(101, 'ketua');
        $foreign = $this->createTestUser(202, 'anggota');
        $before = $this->db->table('users')->get()->getResultArray();
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($ordinary)))
            ->post('api/users/' . $admin['id'] . '/reset-password')->assertStatus(403);
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($admin)))
            ->post('api/users/' . $foreign['id'] . '/reset-password')->assertStatus(404);
        $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
    }

    public function testSelfChangeAcceptsShortPasswordAndKeepsOnlyCallingToken(): void
    {
        $user = $this->createTestUser(101, 'anggota', 'same');
        $one = $this->generateTokenForUser($user);
        $two = $this->generateTokenForUser($user);
        $this->withHeaders($this->getAuthHeaders($one))->withBodyFormat('json')
            ->post('api/profile/password', ['new_password' => 'same', 'confirm_password' => 'same'])->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($one))->get('api/me')->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($two))->get('api/me')->assertStatus(401);
        $this->login('same', 'same')->assertStatus(200);
    }

    public function testRegisterAndAdminCreationAcceptShortChosenPasswords(): void
    {
        $this->withHeaders([])->withBodyFormat('json')->post('api/register', [
            'karang_taruna_id' => 101, 'nama_lengkap' => 'Synthetic register', 'nama_panggilan' => 'Synthetic',
            'username' => 'abc', 'password' => 'abc', 'confirm_password' => 'abc',
            'no_whatsapp' => '08000000111'])->assertStatus(201);
        $registered = $this->db->table('users')->where('username', 'abc')->get()->getRowArray();
        $this->assertTrue(password_verify('abc', $registered['password']));
        $admin = $this->createTestUser(101, 'ketua');
        $token = $this->generateTokenForUser($admin);
        $result = $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->post('api/users', [
            'nama_lengkap' => 'Synthetic create', 'nama_panggilan' => 'Synthetic',
            'username' => 'created', 'password' => 'a', 'role_level' => 'anggota']);
        $result->assertStatus(201);
        $created = $this->db->table('users')->where('username', 'created')->get()->getRowArray();
        $this->assertTrue(password_verify('a', $created['password']));
        $this->assertFalse(str_contains($result->getJSON(), 'temporary_password'));
    }

    public function testBcryptInputBoundariesAreEnforcedOnlyForNewPasswords(): void
    {
        foreach (['', "ab\0cd", str_repeat('a', 73)] as $invalid) {
            $this->assertFalse(CredentialPolicy::validNewPassword($invalid));
        }
        foreach (['a', 'same', str_repeat('a', 72), str_repeat('界', 24)] as $valid) {
            $this->assertTrue(CredentialPolicy::validNewPassword($valid));
        }
    }

    public function testInvalidNewPasswordRequestsNeverChangeExistingCredential(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->generateTokenForUser($user);
        foreach (['', "ab\0cd", str_repeat('a', 73), str_repeat('界', 25)] as $invalid) {
            $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')
                ->post('api/profile/password', ['new_password' => $invalid, 'confirm_password' => $invalid])
                ->assertStatus(422);
        }
        $this->assertTrue($user['password'] === $this->db->table('users')->where('id', $user['id'])->get()->getRowArray()['password']);
    }

    public function testExistingCreationFallbackStillUsesUsernameWithoutRandomPassword(): void
    {
        $admin = $this->createTestUser(101, 'ketua');
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($admin)))
            ->withBodyFormat('json')->post('api/users', [
                'nama_lengkap' => 'Synthetic fallback', 'nama_panggilan' => 'Synthetic',
                'username' => 'abcd', 'role_level' => 'anggota'])->assertStatus(201);
        $row = $this->db->table('users')->where('username', 'abcd')->get()->getRowArray();
        $this->assertTrue(password_verify('abcd', $row['password']));
        $this->login('abcd', 'abcd')->assertStatus(200);
    }

    public function testResetFailureRollsBackPasswordAndRevocation(): void
    {
        $this->defaultSetting();
        $admin = $this->createTestUser(101, 'ketua');
        $target = $this->createTestUser(101);
        $token = $this->generateTokenForUser($admin);
        $this->generateTokenForUser($target);
        $before = $this->db->table('users')->get()->getResultArray();
        $tokens = $this->db->table('user_tokens')->get()->getResultArray();
        $this->db->query("CREATE TRIGGER reject_revocation BEFORE UPDATE ON user_tokens BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->withHeaders($this->getAuthHeaders($token))
            ->post('api/users/' . $target['id'] . '/reset-password')->assertStatus(500);
        $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
        $this->assertSame($tokens, $this->db->table('user_tokens')->get()->getResultArray());
    }

    public function testExpiredAndUnknownTokensStillFail(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->generateTokenForUser($user);
        $this->db->table('user_tokens')->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
        $this->withHeaders($this->getAuthHeaders('synthetic-unknown'))->get('api/me')->assertStatus(401);
    }

    public function testWebResetUsesOriginalSettingAndCsrf(): void
    {
        $this->defaultSetting();
        $user = $this->createTestUser(101);
        $hash = CredentialPolicy::hashNewPassword('synthetic-owner');
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic_owner',
            'password' => $hash, 'nama_lengkap' => 'Synthetic Owner']);
        $browser = ['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
            'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('a', 64)];
        $before = $this->db->table('users')->get()->getResultArray();
        \Config\Services::resetSingle('security');
        try {
            $this->withSession($browser)->withHeaders([])->post('superadmin/manage/101/users/' . $user['id'] . '/reset-password');
            $this->fail('CSRF missing token accepted');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame($before, $this->db->table('users')->get()->getResultArray());
        \Config\Services::resetSingle('security');
        $result = $this->withSession($browser)->withHeaders([])->post('superadmin/manage/101/users/' . $user['id'] . '/reset-password', ['csrf_test_name' => str_repeat('a', 64)]);
        $result->assertRedirectTo('/superadmin/manage/101/users');
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $stored = $this->db->table('users')->where('id', $user['id'])->get()->getRowArray();
        $this->assertTrue(password_verify('synthetic-default', $stored['password']));
        $this->assertFalse(str_contains($result->getBody(), 'synthetic-default'));
    }

    public function testLoginRateLimiterStillRejectsThrottledRequests(): void
    {
        $limiter = $this->getMockBuilder(\CodeIgniter\Throttle\Throttler::class)
            ->disableOriginalConstructor()->onlyMethods(['check'])->getMock();
        $limiter->expects($this->once())->method('check')->willReturn(false);
        \Config\Services::injectMock('throttler', $limiter);
        $this->withHeaders(['X-RateLimit-Test' => 'synthetic'])
            ->withBodyFormat('json')->post('api/login', [])->assertStatus(429);
    }
}
