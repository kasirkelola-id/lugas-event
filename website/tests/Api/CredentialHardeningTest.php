<?php

namespace Tests\Api;

use App\Services\CredentialPolicy;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

class CredentialHardeningTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $migrate = true;
    protected $namespace = 'App';
    private array $capturedLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
        $logger = $this->getMockBuilder(\CodeIgniter\Log\Logger::class)->disableOriginalConstructor()->onlyMethods(['log'])->getMock();
        $logger->method('log')->willReturnCallback(function ($level, $message, $context): void {
            $this->capturedLogs[] = [(string) $message, $context];
        });
        \Config\Services::injectMock('logger', $logger);
    }

    private function createMember(string $username = 'created_member'): array
    {
        $admin = $this->createTestUser(101, 'ketua');
        $adminToken = $this->generateTokenForUser($admin);
        $result = $this->withHeaders($this->getAuthHeaders($adminToken))->withBodyFormat('json')->post('api/users', [
            'nama_lengkap' => 'Synthetic Member', 'nama_panggilan' => 'Synthetic', 'username' => $username,
            'role_level' => 'anggota', 'no_whatsapp' => '08000000000', 'rt' => 1]);
        $result->assertStatus(201);
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $data = json_decode($result->getJSON(), true)['data'];
        $row = $this->db->table('users')->where('id', $data['id'])->get()->getRowArray();
        return [$data, $row, $adminToken];
    }

    private function assertNotPersistedOrLogged(string $secret): void
    {
        foreach ($this->db->listTables() as $table) {
            $this->assertStringNotContainsString($secret, json_encode($this->db->table($table)->get()->getResultArray()), 'Secret persisted in ' . $table);
        }
        $this->assertStringNotContainsString($secret, json_encode($_SESSION));
        $this->assertStringNotContainsString($secret, json_encode($this->capturedLogs));
    }

    public function testCreationReturnsIndependentHashedTemporaryCredentialOnce(): void
    {
        [$first, $row, $adminToken] = $this->createMember();
        $this->assertSame(24, strlen($first['temporary_password']));
        $this->assertNotSame($first['username'], $first['temporary_password']);
        $this->assertFalse(password_verify($first['username'], $row['password']));
        $this->assertTrue(password_verify($first['temporary_password'], $row['password']));
        $this->assertSame(1, (int) $row['password_must_change']);
        $this->assertArrayNotHasKey('password', $first);
        $this->withHeaders($this->getAuthHeaders($adminToken))->withBodyFormat('json')->post('api/users', [
            'nama_lengkap' => 'Synthetic Member', 'nama_panggilan' => 'Synthetic', 'username' => 'equivalent_member', 'role_level' => 'anggota'])->assertStatus(201);
        $second = $this->db->table('users')->where('username', 'equivalent_member')->get()->getRowArray();
        $this->assertFalse(password_verify($first['temporary_password'], $second['password']));
        $list = $this->withHeaders($this->getAuthHeaders($adminToken))->get('api/users');
        $list->assertStatus(200);
        $this->assertStringNotContainsString('temporary_password', $list->getJSON());
        $this->assertStringNotContainsString($first['temporary_password'], $list->getJSON());
        $this->assertNotPersistedOrLogged($first['temporary_password']);
    }

    public function testTemporaryLoginForcedChangeAndExistingTokenSemanticsEndToEnd(): void
    {
        [$data, $row] = $this->createMember();
        $login = $this->withHeaders([])->withBodyFormat('json')->post('api/login', [
            'karang_taruna_id' => 101, 'username' => $data['username'], 'password' => $data['temporary_password']]);
        $login->assertStatus(200);
        $auth = json_decode($login->getJSON(), true)['data'];
        $this->assertTrue($auth['user']['password_must_change']);
        $headers = $this->getAuthHeaders($auth['token']);
        $this->withHeaders($headers)->get('api/me')->assertStatus(200);
        foreach (['api/inventories', 'api/memberships', 'api/users', 'api/dashboard'] as $path) {
            $this->withHeaders($headers)->get($path)->assertStatus(403);
        }
        $this->withHeaders($headers)->withBodyFormat('json')->put('api/profile', ['nama_lengkap' => 'Bypass'])->assertStatus(403);
        $this->withHeaders($headers)->post('api/fcm-token', ['fcm_token' => 'synthetic'])->assertStatus(403);
        $this->withHeaders($headers)->post('api/profile/password', ['new_password' => $data['temporary_password'], 'confirm_password' => $data['temporary_password']])->assertStatus(422);
        $private = 'new private passphrase';
        $this->withHeaders($headers)->post('api/profile/password', ['new_password' => $private, 'confirm_password' => $private])->assertStatus(200);
        $changed = $this->db->table('users')->where('id', $row['id'])->get()->getRowArray();
        $this->assertSame(0, (int) $changed['password_must_change']);
        $this->assertTrue(password_verify($private, $changed['password']));
        $this->assertFalse(password_verify($data['temporary_password'], $changed['password']));
        $this->withHeaders($headers)->get('api/inventories')->assertStatus(200);
        $this->withHeaders($headers)->get('api/me')->assertStatus(200);
        $this->assertNotPersistedOrLogged($data['temporary_password']);
        $this->assertNotPersistedOrLogged($private);
        $this->assertNotPersistedOrLogged($auth['token']);
    }

    public function testForcedChangeStillAllowsLogout(): void
    {
        [$data, $row] = $this->createMember();
        $row['karang_taruna_id'] = 101;
        $token = $this->generateTokenForUser($row);
        $this->withHeaders($this->getAuthHeaders($token))->post('api/logout')->assertStatus(200);
        $this->withHeaders($this->getAuthHeaders($token))->get('api/me')->assertStatus(401);
    }

    public function testResetNeverReusesSharedSettingUsernameOrOldPassword(): void
    {
        [$data, $row, $adminToken] = $this->createMember();
        $this->db->table('users')->where('id', $row['id'])->update(['password_must_change' => 0]);
        $this->db->table('settings')->insert(['karang_taruna_id' => 0, 'setting_key' => 'temporary_reset_password', 'setting_value' => 'legacy-shared-value']);
        $headers = $this->getAuthHeaders($adminToken);
        $reset = $this->withHeaders($headers)->post("api/users/{$row['id']}/reset-password");
        $reset->assertStatus(200);
        $temp = json_decode($reset->getJSON(), true)['data']['temporary_password'];
        $this->assertSame(24, strlen($temp));
        $this->assertNotContains($temp, [$data['username'], $data['temporary_password'], 'legacy-shared-value']);
        $changed = $this->db->table('users')->where('id', $row['id'])->get()->getRowArray();
        $this->assertTrue(password_verify($temp, $changed['password']));
        $this->assertSame(1, (int) $changed['password_must_change']);
        $this->withHeaders($headers)->post("api/users/{$row['id']}/reset-password")->assertStatus(200);
        $again = $this->db->table('users')->where('id', $row['id'])->get()->getRowArray();
        $this->assertFalse(password_verify($temp, $again['password']));
        $this->assertNotPersistedOrLogged($temp);
    }

    public static function invalidPasswords(): array
    {
        return [['short'], ['elevenchars'], [str_repeat('x', 73)], [str_repeat('界', 25)], ['superadmin123']];
    }

    /** @dataProvider invalidPasswords */
    public function testPasswordChangeRejectsWeakOrTruncatedPasswords(string $password): void
    {
        $user = $this->createTestUser(101);
        $before = $this->db->table('users')->where('id', $user['id'])->get()->getRowArray();
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))->withBodyFormat('json')
            ->post('api/profile/password', ['new_password' => $password, 'confirm_password' => $password])->assertStatus(422);
        $this->assertSame($before, $this->db->table('users')->where('id', $user['id'])->get()->getRowArray());
    }

    public function testNewRegistrationPolicyAndLegacyShortLogin(): void
    {
        $user = $this->createTestUser(101);
        $this->withHeaders([])->withBodyFormat('json')->post('api/login', ['karang_taruna_id' => 101, 'username' => $user['username'], 'password' => 'password123'])->assertStatus(200);
        $body = ['karang_taruna_id' => 101, 'nama_lengkap' => 'Synthetic', 'nama_panggilan' => 'Synthetic', 'username' => 'registered_member', 'no_whatsapp' => '08011111111'];
        $this->post('api/register', $body + ['password' => 'short', 'confirm_password' => 'short'])->assertStatus(422);
        $this->post('api/register', $body + ['password' => 'private member passphrase', 'confirm_password' => 'private member passphrase'])->assertStatus(201);
        $row = $this->db->table('users')->where('username', 'registered_member')->get()->getRowArray();
        $this->assertTrue(password_verify('private member passphrase', $row['password']));
        $this->assertSame(0, (int) $row['password_must_change']);
        $this->assertNotPersistedOrLogged('private member passphrase');
    }

    public function testProductionDefaultsDeniedAndTestFixturesExplicitlyAllowed(): void
    {
        foreach (['superadmin123', 'lugasjosjis', 'kartarjosjis', 'seed_username'] as $password) {
            $account = ['username' => 'seed_username', 'password' => password_hash($password, PASSWORD_BCRYPT)];
            $this->assertFalse(CredentialPolicy::verify($password, $account, 'production'));
            $this->assertFalse(CredentialPolicy::verify($password, $account, 'development'));
            $this->assertTrue(CredentialPolicy::verify($password, $account, 'testing'));
        }
        $this->assertTrue(CredentialPolicy::verify('old-short', ['username' => 'ordinary', 'password' => password_hash('old-short', PASSWORD_BCRYPT)], 'production'));
        CredentialPolicy::requireTestingSeeds('testing');
        foreach (['production', 'development'] as $environment) {
            try {
                CredentialPolicy::requireTestingSeeds($environment);
                $this->fail('Demo seeding accepted outside testing');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('only be seeded in testing', $e->getMessage());
            }
        }
    }

    public function testBearerApiAndPublicVersionDoNotRequireBrowserCsrf(): void
    {
        [$data, $row, $token] = $this->createMember(); // POST create succeeds without CSRF.
        $this->withHeaders($this->getAuthHeaders($token))->withBodyFormat('json')->put("api/users/{$row['id']}", ['nama_lengkap' => 'Changed'])->assertStatus(200);
        $this->withHeaders([])->get('api/app-version')->assertStatus(200);
    }

    public function testActualProductionEnvironmentDefaultGateAndSeederGuard(): void
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/_support/production_credential_probe.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        $this->assertSame(['default_denied' => true, 'private_accepted' => true, 'seed_blocked' => true], json_decode($output, true));
    }

    public function testActualTestSeederStillUsableOnlyUnderTestPolicy(): void
    {
        \Config\Database::seeder('tests')->call(\App\Database\Seeds\UserSeeder::class);
        foreach (['admin', 'pengelola'] as $username) {
            $row = $this->db->table('users')->where('username', $username)->get()->getRowArray();
            $this->assertTrue(CredentialPolicy::verify('lugasjosjis', $row));
            $this->assertFalse(CredentialPolicy::verify('lugasjosjis', $row, 'production'));
        }
    }

    public function testCredentialAndBrowserRequestsNeverReachDebugToolbarStorage(): void
    {
        $toolbar = $this->getMockBuilder(\CodeIgniter\Debug\Toolbar::class)->disableOriginalConstructor()->onlyMethods(['prepare'])->getMock();
        $toolbar->expects($this->never())->method('prepare');
        \Config\Services::injectMock('toolbar', $toolbar);
        $this->assertSame(\App\Filters\CredentialSafeToolbar::class, config('Filters')->aliases['toolbar']);
        $filter = new \App\Filters\CredentialSafeToolbar();
        foreach (['/', '/superadmin/login', '/superadmin/manage/101/users/1/reset-password', '/superadmin/settings', '/api/login', '/api/register', '/api/me', '/api/logout', '/api/profile/password', '/api/users', '/api/users/1/reset-password', '/index.php/api/users'] as $path) {
            $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\URI('http://localhost' . $path), null, new \CodeIgniter\HTTP\UserAgent());
            $this->assertNull($filter->after($request, service('response')));
        }
    }

    public function testToolbarStillHandlesUnrelatedRoutes(): void
    {
        $toolbar = $this->getMockBuilder(\CodeIgniter\Debug\Toolbar::class)->disableOriginalConstructor()->onlyMethods(['prepare'])->getMock();
        $toolbar->expects($this->once())->method('prepare');
        \Config\Services::injectMock('toolbar', $toolbar);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\URI('http://localhost/api/app-version'), null, new \CodeIgniter\HTTP\UserAgent());
        $this->assertNull((new \App\Filters\CredentialSafeToolbar())->after($request, service('response')));
    }
}
