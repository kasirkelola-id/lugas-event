<?php

namespace Tests;

use App\Services\CredentialPolicy;
use App\Services\SuperadminCredentialPolicy;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class SuperadminCredentialPolicyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testOwnerChosenPasswordsAreAcceptedWithoutMemberCreationRules(): void
    {
        // Synthetic fixtures only; no application or operator credential is used.
        foreach (['x', 'synthetic_owner', 'superadmin123', str_repeat('z', 72)] as $chosen) {
            $hash = SuperadminCredentialPolicy::hashNewPassword($chosen);
            $account = ['username' => 'synthetic_owner', 'password' => $hash];
            $this->assertSame('bcrypt', password_get_info($hash)['algoName']);
            $this->assertTrue(SuperadminCredentialPolicy::verify($chosen, $account));
            $this->assertFalse(SuperadminCredentialPolicy::verify('incorrect-fixture', $account));
        }
        $this->assertTrue(CredentialPolicy::validNewPassword('x'));
    }

    public function testAllLoginPoliciesAcceptOwnerChoices(): void
    {
        foreach (['synthetic_owner', 'superadmin123'] as $chosen) {
            $account = ['username' => 'synthetic_owner',
                'password' => SuperadminCredentialPolicy::hashNewPassword($chosen)];
            $this->assertTrue(CredentialPolicy::verify($chosen, $account, 'production'));
            $this->assertTrue(SuperadminCredentialPolicy::verify($chosen, $account));
        }
    }

    public function testMissingOrInvalidStoredHashCannotAuthenticate(): void
    {
        $this->assertFalse(SuperadminCredentialPolicy::verify('synthetic', []));
        $this->assertFalse(SuperadminCredentialPolicy::verify('synthetic', ['password' => 'invalid-hash']));
    }

    public function testHashingRejectsOnlyEmptyOrBcryptIncompatibleInput(): void
    {
        foreach (['', str_repeat('z', 73), "synthetic\0input"] as $chosen) {
            try {
                SuperadminCredentialPolicy::hashNewPassword($chosen);
                $this->fail('Invalid bcrypt input accepted');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function testLoginVerifiesExistingHashWithoutApplyingCreationByteLimit(): void
    {
        $chosen = str_repeat('z', 73);
        $account = ['password' => password_hash(substr($chosen, 0, 72), PASSWORD_BCRYPT)];
        $this->assertSame(password_verify($chosen, $account['password']),
            SuperadminCredentialPolicy::verify($chosen, $account));
    }

    public static function ownerChoices(): array
    {
        return [[0], [1], [2]];
    }

    /** @dataProvider ownerChoices */
    public function testWebLoginAcceptsOwnerPasswordAndPreservesCredentialBinding(int $choice): void
    {
        // Minimal in-memory fixture, without running migrations or seeders.
        $db = \Config\Database::connect();
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('SQLite3', $db->DBDriver);
        $this->assertSame(':memory:', $db->database);
        $db->transBegin();
        try {
            $db->query('CREATE TABLE IF NOT EXISTS superadmins (
                id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT,
                nama_lengkap TEXT, created_at TEXT, updated_at TEXT)');
            $chosen = ['x', 'synthetic_owner_login', 'superadmin123'][$choice];
            $hash = SuperadminCredentialPolicy::hashNewPassword($chosen);
            $db->table('superadmins')->insert(['username' => 'synthetic_owner_login',
                'nama_lengkap' => 'Synthetic Owner', 'password' => $hash]);
            $id = $db->insertID();
            $before = $db->table('superadmins')->where('id', $id)->get()->getRowArray();
            if ($choice === 0) {
                \Config\Services::resetSingle('security');
                $csrf = str_repeat('a', 64);
                $this->withSession(['csrf_test_name' => $csrf])
                    ->post('superadmin/login', ['username' => $before['username'],
                        'password' => 'incorrect-fixture', 'csrf_test_name' => $csrf])
                    ->assertRedirectTo('/superadmin/login');
                $this->assertFalse((bool) session()->get('is_superadmin_logged_in'));
            }
            \Config\Services::resetSingle('security');
            $csrf = str_repeat('a', 64);
            $result = $this->withSession(['csrf_test_name' => $csrf])
                ->post('superadmin/login', ['username' => $before['username'],
                    'password' => $chosen, 'csrf_test_name' => $csrf]);
            $result->assertRedirectTo('/superadmin/dashboard');
            $this->assertTrue(session()->get('is_superadmin_logged_in'));
            $this->assertSame((int) $id, (int) session()->get('superadmin_id'));
            $this->assertTrue(hash_equals(hash('sha256', $hash),
                (string) session()->get('superadmin_credential_version')));
            $this->assertSame($before, $db->table('superadmins')->where('id', $id)->get()->getRowArray());

            $db->table('superadmins')->where('id', $id)->update([
                'password' => SuperadminCredentialPolicy::hashNewPassword('y')]);
            $filter = new \App\Filters\SuperadminFilter();
            $this->assertNotNull($filter->before(service('request')));
            $this->assertFalse((bool) session()->get('is_superadmin_logged_in'));
        } finally {
            $db->transRollback();
        }
    }

    public function testApiSuperadminLoginUsesOwnerPolicyAndKeepsTokenBinding(): void
    {
        $db = \Config\Database::connect();
        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('SQLite3', $db->DBDriver);
        $this->assertSame(':memory:', $db->database);
        $db->transBegin();
        try {
            // Only the columns exercised by login; no application migration is run.
            $db->query('CREATE TABLE IF NOT EXISTS superadmins (
                id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT,
                nama_lengkap TEXT, created_at TEXT, updated_at TEXT)');
            $db->query('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY)');
            $db->query('CREATE TABLE IF NOT EXISTS organization_members (
                id INTEGER PRIMARY KEY, user_id INTEGER, username TEXT,
                karang_taruna_id INTEGER, role_level TEXT, status_aktif INTEGER,
                approval_status TEXT)');
            $db->query('CREATE TABLE IF NOT EXISTS user_tokens (
                id INTEGER PRIMARY KEY, karang_taruna_id INTEGER, user_id INTEGER,
                superadmin_id INTEGER, credential_version TEXT, token_hash TEXT,
                expires_at TEXT, created_at TEXT)');
            $chosen = 'synthetic_api_owner';
            $hash = SuperadminCredentialPolicy::hashNewPassword($chosen);
            $db->table('superadmins')->insert(['username' => $chosen,
                'nama_lengkap' => 'Synthetic API Owner', 'password' => $hash]);
            $id = $db->insertID();
            $before = $db->table('superadmins')->where('id', $id)->get()->getRowArray();
            $result = $this->withBodyFormat('json')->post('api/login', [
                'username' => $chosen, 'password' => $chosen, 'karang_taruna_id' => 101]);
            $result->assertStatus(200);
            $data = json_decode($result->getJSON(), true)['data'];
            $this->assertTrue($data['user']['role_level'] === 'superadmin');
            $this->assertTrue((int) $data['user']['id'] === -1 * (int) $id);
            $token = $db->table('user_tokens')->where('superadmin_id', $id)->get()->getRowArray();
            $this->assertTrue(hash_equals(hash('sha256', $hash), $token['credential_version']));
            $this->assertTrue(hash_equals(hash('sha256', $data['token']), $token['token_hash']));
            $this->assertSame($before, $db->table('superadmins')->where('id', $id)->get()->getRowArray());
            $this->withBodyFormat('json')->post('api/login', [
                'username' => $chosen, 'password' => 'incorrect-fixture', 'karang_taruna_id' => 101])
                ->assertStatus(401);
            $this->assertSame(1, $db->table('user_tokens')->where('superadmin_id', $id)->countAllResults());
        } finally {
            $db->transRollback();
        }
    }
}
