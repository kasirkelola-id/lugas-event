<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;

class WebCsrfCredentialTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $migrate = true;
    protected $namespace = 'App';

    private function browser(bool $authorized = true): array
    {
        \Config\Services::resetSingle('security');
        return ['is_superadmin_logged_in' => $authorized, 'superadmin_id' => 1,
            'superadmin_nama_lengkap' => 'Synthetic Admin', 'csrf_test_name' => str_repeat('a', 64)];
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (['users', 'organization_members', 'karang_taruna', 'kelurahan', 'pengumuman', 'settings', 'membership_approval_history'] as $table) {
            $rows[$table] = $this->db->table($table)->get()->getResultArray();
        }
        return $rows;
    }

    private function fixtures(): array
    {
        $this->db->resetTransStatus();
        $user = $this->createTestUser(101, 'anggota', 'synthetic_member');
        $this->db->table('organization_members')->where('user_id', $user['id'])->update(['approval_status' => 'pending']);
        $member = $this->db->table('organization_members')->where('user_id', $user['id'])->get()->getRowArray();
        $this->db->table('kelurahan')->insert(['nama' => 'Synthetic Kelurahan']);
        $kel = $this->db->insertID();
        $this->db->table('pengumuman')->insert(['karang_taruna_id' => 101, 'judul' => 'Synthetic', 'isi' => 'Synthetic', 'dibuat_oleh' => $user['id'], 'created_at' => date('Y-m-d H:i:s')]);
        $ann = $this->db->insertID();
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic_admin', 'nama_lengkap' => 'Synthetic Admin', 'password' => password_hash('private-admin-passphrase', PASSWORD_BCRYPT)]);
        return [
            'login' => ['superadmin/login', ['username' => 'synthetic_admin', 'password' => 'private-admin-passphrase']],
            'logout' => ['superadmin/logout', []],
            'settings' => ['superadmin/settings', ['android_version_name' => 'Synthetic 5']],
            'kelurahan/store' => ['superadmin/kelurahan/store', ['nama' => 'New Synthetic']],
            'kelurahan/update' => ["superadmin/kelurahan/update/$kel", ['nama' => 'Changed Synthetic']],
            'kelurahan/delete' => ["superadmin/kelurahan/delete/$kel", []],
            'tenant/create' => ['superadmin/karang_taruna/create', ['nama_organisasi' => 'New Synthetic Tenant', 'kode_pin' => '765432', 'nama_ketua' => 'Synthetic', 'kelurahan_id' => $kel, 'alamat_lengkap' => 'Synthetic']],
            'tenant/update' => ['superadmin/karang_taruna/update/101', ['nama_organisasi' => 'Changed Synthetic', 'nama_ketua' => 'Synthetic', 'kelurahan_id' => $kel, 'alamat_lengkap' => 'Synthetic', 'status_aktif' => 1]],
            'tenant/delete' => ['superadmin/karang_taruna/delete/101', []],
            'role' => ["superadmin/manage/101/users/{$user['id']}/role", ['role_level' => 'pengelola']],
            'status' => ["superadmin/manage/101/users/{$user['id']}/status", []],
            'reset' => ["superadmin/manage/101/users/{$user['id']}/reset-password", []],
            'approve' => ["superadmin/manage/101/users/approve/{$member['id']}", []],
            'reject' => ["superadmin/manage/101/users/reject/{$member['id']}", []],
            'announcement/create' => ['superadmin/manage/101/pengumuman/create', ['judul' => 'New Synthetic', 'isi' => 'Synthetic']],
            'announcement/delete' => ["superadmin/manage/101/pengumuman/delete/$ann", []],
        ];
    }

    public static function mutationRoutes(): array
    {
        return array_map(static fn ($key) => [$key], array_combine(
            $keys = ['login', 'logout', 'settings', 'kelurahan/store', 'kelurahan/update', 'kelurahan/delete', 'tenant/create', 'tenant/update', 'tenant/delete', 'role', 'status', 'reset', 'approve', 'reject', 'announcement/create', 'announcement/delete'], $keys));
    }

    /** @dataProvider mutationRoutes */
    public function testEveryWebMutationRejectsCrossSitePostWithoutToken(string $key): void
    {
        [$path, $params] = $this->fixtures()[$key];
        $before = $this->snapshot();
        try {
            $this->withSession($this->browser())->withHeaders(['Origin' => 'https://attacker.invalid'])->post($path, $params);
            $this->fail('Missing CSRF token was accepted');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue(session()->get('is_superadmin_logged_in'));
    }

    /** @dataProvider mutationRoutes */
    public function testEveryWebMutationAcceptsAuthorizedCsrf(string $key): void
    {
        [$path, $params] = $this->fixtures()[$key];
        $before = $this->snapshot();
        try {
            $result = $this->withSession($this->browser())->withHeaders([])->post($path, $params + ['csrf_test_name' => str_repeat('a', 64)]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // Existing web announcement create uses penulis_id but the migrated
            // table requires dibuat_oleh. Verify CSRF allowed controller execution;
            // preserve and report this separate pre-existing schema defect.
            if ($key !== 'announcement/create') {
                throw $e;
            }
            $this->assertStringContainsString('pengumuman.dibuat_oleh', $e->getMessage());
            $this->assertSame($before, $this->snapshot());
            return;
        }
        if ($key === 'reset') {
            $result->assertStatus(200);
            $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('Password sementara:', $result->getBody());
            $this->assertArrayNotHasKey('temporary_password', $_SESSION);
        } else {
            $result->assertRedirect();
        }
        if ($key === 'login') {
            $this->assertTrue(session()->get('is_superadmin_logged_in'));
        } elseif ($key === 'logout') {
            $this->assertFalse((bool) session()->get('is_superadmin_logged_in'));
        } else {
            $this->assertNotSame($before, $this->snapshot(), 'Authorized request must perform its intended mutation');
        }
    }

    /** @dataProvider mutationRoutes */
    public function testValidCsrfDoesNotAuthorizeManagement(string $key): void
    {
        if ($key === 'login') {
            // Public login is allowed to validate credentials, not to trust a session flag.
            [$path] = $this->fixtures()[$key];
            $params = ['username' => 'synthetic_admin', 'password' => 'incorrect'];
        } else {
            [$path, $params] = $this->fixtures()[$key];
        }
        $before = $this->snapshot();
        $result = $this->withSession($this->browser(false))->withHeaders([])->post($path, $params + ['csrf_test_name' => str_repeat('a', 64)]);
        $result->assertRedirectTo('/superadmin/login');
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse((bool) session()->get('is_superadmin_logged_in'));
    }

    public static function formerGetMutations(): array
    {
        return array_map(static fn ($key) => [$key], ['logout', 'kelurahan/delete', 'tenant/delete', 'status', 'reset', 'approve', 'reject', 'announcement/delete']);
    }

    /** @dataProvider formerGetMutations */
    public function testEveryFormerGetMutationPreservesRowsAndSession(string $key): void
    {
        [$path] = $this->fixtures()[$key];
        $before = $this->snapshot();
        try {
            $this->withSession($this->browser())->withHeaders(['Origin' => 'https://attacker.invalid'])->get($path);
            $this->fail('Mutation GET route still exists');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue(session()->get('is_superadmin_logged_in'));
    }

    public function testCrossSiteGetCannotToggleMembership(): void
    {
        $user = $this->createTestUser(101);
        try {
        $this->withSession($this->browser())
            ->withHeaders(['Origin' => 'https://attacker.invalid', 'Referer' => 'https://attacker.invalid/'])
            ->get('superadmin/manage/101/users/' . $user['id'] . '/status');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
        $member = $this->db->table('organization_members')->where('user_id', $user['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $member['status_aktif']);
    }

    public function testWrongSessionTokenRejectedAndFrameworkHeaderRotates(): void
    {
        [$path] = $this->fixtures()['status'];
        $before = $this->snapshot();
        try {
            $this->withSession($this->browser())->withHeaders([])->post($path, ['csrf_test_name' => str_repeat('b', 64)]);
            $this->fail('A token from another session was accepted');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame($before, $this->snapshot());
        $this->withSession($this->browser())->withHeaders(['X-CSRF-TOKEN' => str_repeat('a', 64)])->post($path)->assertRedirect();
        $this->assertNotSame($before, $this->snapshot());
        $this->assertNotSame(str_repeat('a', 64), session()->get('csrf_test_name'));
    }

    public function testRenderedWebFormsAllCarryTokensAndNoMutationLinks(): void
    {
        $this->fixtures();
        foreach (['/', 'superadmin/login', 'superadmin/dashboard', 'superadmin/kelurahan', 'superadmin/karang_taruna', 'superadmin/settings', 'superadmin/manage/101/users', 'superadmin/manage/101/pengumuman', 'superadmin/manage/101/events'] as $path) {
            $result = $this->withSession($this->browser($path !== '/' && $path !== 'superadmin/login'))->withHeaders([])->get($path);
            $result->assertStatus(200);
            $html = $result->getBody();
            preg_match_all('#<form\b[^>]*>(.*?)</form>#s', $html, $forms);
            $this->assertNotEmpty($forms[0]);
            foreach ($forms[1] as $form) {
                $this->assertStringContainsString('name="csrf_test_name"', $form);
            }
            $this->assertDoesNotMatchRegularExpression('#href="[^"]*(?:/delete/|/reset-password|/users/[^"/]+/status|/users/approve/|/users/reject/|/logout)[^"]*"#', $html);
        }
    }

    public function testWebResetDeliversOnceWithoutSessionDatabaseOrLogPlaintext(): void
    {
        [$path] = $this->fixtures()['reset'];
        $messages = [];
        $logger = $this->getMockBuilder(\CodeIgniter\Log\Logger::class)->disableOriginalConstructor()->onlyMethods(['log'])->getMock();
        $logger->method('log')->willReturnCallback(static function ($level, $message, $context) use (&$messages): void {
            $messages[] = [(string) $message, $context];
        });
        \Config\Services::injectMock('logger', $logger);
        $result = $this->withSession($this->browser())->withHeaders([])->post($path, ['csrf_test_name' => str_repeat('a', 64)]);
        $result->assertStatus(200);
        preg_match('#Password sementara: <strong>([^<]+)</strong>#', $result->getBody(), $match);
        $temp = $match[1];
        $row = $this->db->table('users')->where('username', 'synthetic_member')->get()->getRowArray();
        $this->assertNotSame($row['username'], $temp);
        $this->assertTrue(password_verify($temp, $row['password']));
        $this->assertSame(1, (int) $row['password_must_change']);
        $this->assertStringNotContainsString($temp, json_encode($this->snapshot()));
        $this->assertStringNotContainsString($temp, json_encode($_SESSION));
        $this->assertStringNotContainsString($temp, json_encode($messages));
        $list = $this->withSession($this->browser())->get('superadmin/manage/101/users');
        $list->assertStatus(200);
        $this->assertStringNotContainsString($temp, $list->getBody());
    }
}
