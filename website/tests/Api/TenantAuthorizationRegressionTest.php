<?php

namespace Tests\Api;

use App\Services\AuthService;
use CodeIgniter\Test\FeatureTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthTrait;

class TenantAuthorizationRegressionTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $namespace = 'App';

    private function addMembership(array $user, int $tenantId, string $approval = 'approved', int $active = 1): void
    {
        $this->createTestUser($tenantId);
        $this->db->table('organization_members')->insert([
            'user_id' => $user['id'], 'karang_taruna_id' => $tenantId,
            'username' => 'selected_' . $user['id'], 'role_level' => 'anggota',
            'approval_status' => $approval, 'status_aktif' => $active,
        ]);
    }

    private function tokenWithoutTenant(array $user): string
    {
        $token = $this->generateTokenForUser($user);
        $this->db->table('user_tokens')->where('token_hash', hash('sha256', $token))
            ->update(['karang_taruna_id' => null]);
        return $token;
    }

    public static function eligibilityCases(): array
    {
        return [
            'approved active' => ['approved', 1, 1, 1, true, 200],
            'pending active' => ['pending', 1, 1, 1, true, 403],
            'rejected active' => ['rejected', 1, 1, 1, true, 403],
            'inactive membership' => ['approved', 0, 1, 1, true, 403],
            'inactive organization' => ['approved', 1, 0, 1, true, 403],
            'inactive global user' => ['approved', 1, 1, 0, true, 401],
            'spoofed nonmember tenant' => ['approved', 1, 1, 1, false, 403],
        ];
    }

    #[DataProvider('eligibilityCases')]
    public function testSelectedMembershipEligibility(string $approval, int $memberActive, int $orgActive, int $userActive, bool $hasMembership, int $status): void
    {
        $user = $this->createTestUser(101);
        $this->addMembership($user, 102, $approval, $memberActive);
        if (!$hasMembership) {
            $this->db->table('organization_members')->where('user_id', $user['id'])
                ->where('karang_taruna_id', 102)->delete();
        }
        $this->db->table('karang_taruna')->where('id', 102)->update(['status_aktif' => $orgActive]);
        $this->db->table('users')->where('id', $user['id'])->update(['status_aktif' => $userActive]);
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->generateTokenForUser($user),
            'X-Karang-Taruna-ID' => '102',
        ])->get('api/inventories');
        $result->assertStatus($status);
        if ($status === 200) {
            $this->assertEquals(102, AuthService::getTenantId());
        } else {
            $this->assertNull(AuthService::getUser(), 'Denied requests cannot retain an earlier tenant context');
        }
    }

    public static function invalidHeaders(): array
    {
        return [['0'], ['-1'], ['101suffix'], ['1e2'], ['999999999999999999999']];
    }

    #[DataProvider('invalidHeaders')]
    public function testMalformedHeaderCannotFallBackToValidTokenTenant(string $header): void
    {
        $user = $this->createTestUser(101);
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->generateTokenForUser($user),
            'X-Karang-Taruna-ID' => $header,
        ])->get('api/inventories')->assertStatus(403);
        $this->assertNull(AuthService::getTenantId());
    }

    public static function invalidMembershipStates(): array
    {
        return [
            'missing' => [null, 1, 1],
            'pending' => ['pending', 1, 1],
            'rejected' => ['rejected', 1, 1],
            'inactive' => ['approved', 0, 1],
            'inactive organization' => ['approved', 1, 0],
        ];
    }

    #[DataProvider('invalidMembershipStates')]
    public function testLegacyUserTenantNeverGrantsAccess(?string $approval, int $active, int $orgActive): void
    {
        $user = $this->createTestUser(101, 'ketua');
        if ($approval === null) {
            $this->db->table('organization_members')->where('user_id', $user['id'])->delete();
        } else {
            $this->db->table('organization_members')->where('user_id', $user['id'])
                ->update(['approval_status' => $approval, 'status_aktif' => $active]);
        }
        $this->db->table('karang_taruna')->where('id', 101)->update(['status_aktif' => $orgActive]);
        $headers = ['Authorization' => 'Bearer ' . $this->tokenWithoutTenant($user)];
        foreach (['api/inventories', 'api/memberships/pending', 'api/memberships/filter-options'] as $path) {
            $this->withHeaders($headers)->get($path)->assertStatus(403);
            $this->assertNull(AuthService::getTenantId());
        }
    }

    #[DataProvider('invalidMembershipStates')]
    public function testImplicitTokenTenantRequiresEligibleMembership(?string $approval, int $active, int $orgActive): void
    {
        $user = $this->createTestUser(101);
        if ($approval === null) {
            $this->db->table('organization_members')->where('user_id', $user['id'])->delete();
        } else {
            $this->db->table('organization_members')->where('user_id', $user['id'])
                ->update(['approval_status' => $approval, 'status_aktif' => $active]);
        }
        $this->db->table('karang_taruna')->where('id', 101)->update(['status_aktif' => $orgActive]);
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))
            ->get('api/inventories')->assertStatus(403);
    }

    public function testImplicitSelectionIgnoresIneligibleOtherMembership(): void
    {
        $user = $this->createTestUser(101, 'bendahara');
        $this->addMembership($user, 102, 'pending');
        $this->db->table('users')->where('id', $user['id'])->update(['karang_taruna_id' => 102]);
        $result = $this->withHeaders($this->getAuthHeaders($this->tokenWithoutTenant($user)))
            ->get('api/me');
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true)['data'];
        $this->assertEquals(101, $data['karang_taruna']['id']);
        $this->assertEquals('bendahara', $data['role_level']);
    }

    public function testGlobalProfileAndLogoutDoNotRetainLegacyAuthority(): void
    {
        $user = $this->createTestUser(101, 'ketua');
        $this->db->table('organization_members')->where('user_id', $user['id'])->delete();
        $headers = $this->getAuthHeaders($this->tokenWithoutTenant($user));
        $result = $this->withHeaders($headers)->get('api/me');
        $result->assertStatus(200);
        $this->assertNull(AuthService::getTenantId());
        $this->assertNull(AuthService::getRole());
        $this->withHeaders($headers)->post('api/logout')->assertStatus(200);
    }

    public function testIntentionallyGlobalSuperadminIsUnchanged(): void
    {
        $this->createTestUser(101);
        $token = bin2hex(random_bytes(32));
        $hash = password_hash('private-admin-passphrase', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['username' => 'synthetic_admin', 'nama_lengkap' => 'Synthetic Admin', 'password' => $hash]);
        $adminId = $this->db->insertID();
        $this->db->table('user_tokens')->insert([
            'user_id' => null, 'karang_taruna_id' => 101,
            'superadmin_id' => $adminId, 'credential_version' => hash('sha256', $hash),
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $this->db->table('karang_taruna')->where('id', 101)->update(['status_aktif' => 0]);
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '102',
        ])->get('api/me');
        $result->assertStatus(200);
        $this->assertEquals('superadmin', AuthService::getRole());
        $this->assertEquals(101, AuthService::getTenantId(), 'Global identity retains the existing token context');
    }

    public function testInternalSocketAuthInheritsMembershipDenial(): void
    {
        $user = $this->createTestUser(101);
        $this->addMembership($user, 102, 'pending');
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->generateTokenForUser($user),
            'X-Karang-Taruna-ID' => '102',
            'X-Internal-Secret' => getenv('INTERNAL_API_SECRET') ?: 'default_internal_secret_for_dev',
        ])->post('api/internal/socket-auth')->assertStatus(403);
    }

    private function event(array $user, int $tenant): int
    {
        $this->db->table('events')->insert([
            'karang_taruna_id' => $tenant, 'nama_acara' => 'Same event details',
            'tanggal_acara' => date('Y-m-d'), 'kode_qr' => uniqid('REG-'),
            'dibuat_oleh' => $user['id'], 'status_aktif' => 'aktif',
        ]);
        return (int)$this->db->insertID();
    }

    private function attendance(array $user, int $event, int $tenant, ?string $checkout = null, ?string $checkin = null): void
    {
        $this->db->table('absensi')->insert([
            'user_id' => $user['id'], 'event_id' => $event, 'karang_taruna_id' => $tenant,
            'waktu_absen' => $checkin ?? date('Y-m-d H:i:s'), 'waktu_checkout' => $checkout,
        ]);
    }

    private function statusIds(string $token, int $tenant): array
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => (string)$tenant,
        ])->get('api/absensi/status');
        $result->assertStatus(200);
        return array_map('intval', json_decode($result->getJSON(), true)['data']['active_event_ids']);
    }

    public function testAttendanceOnlyInOtherApprovedTenantIsAbsent(): void
    {
        $user = $this->createTestUser(101);
        $this->addMembership($user, 102);
        $this->attendance($user, $this->event($user, 102), 102);
        $this->assertSame([], $this->statusIds($this->generateTokenForUser($user), 101));
    }

    public function testAttendanceWithIdenticalDetailsSwitchesOnlyBetweenAuthorizedTenants(): void
    {
        $user = $this->createTestUser(101);
        $this->addMembership($user, 102);
        $this->createTestUser(103);
        $eventA = $this->event($user, 101);
        $eventB = $this->event($user, 102);
        $this->attendance($user, $eventA, 101);
        $this->attendance($user, $eventB, 102);
        $token = $this->generateTokenForUser($user);
        $this->assertSame([$eventA], $this->statusIds($token, 101));
        $this->assertSame([$eventB], $this->statusIds($token, 102));
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '103',
        ])->get('api/absensi/status')->assertStatus(403);
    }

    public function testAttendanceRequiresBothRowAndParentEventTenantToMatch(): void
    {
        $user = $this->createTestUser(101);
        $this->addMembership($user, 102);
        $this->attendance($user, $this->event($user, 101), 102);
        $this->attendance($user, $this->event($user, 102), 101);
        $this->assertSame([], $this->statusIds($this->generateTokenForUser($user), 101));
    }

    public function testAttendancePreservesCurrentUserOpenTodayBehavior(): void
    {
        $user = $this->createTestUser(101);
        $other = $this->createTestUser(101);
        $open = $this->event($user, 101);
        $this->attendance($user, $open, 101);
        $this->attendance($user, $this->event($user, 101), 101, date('Y-m-d H:i:s'));
        $this->attendance($user, $this->event($user, 101), 101, null, date('Y-m-d H:i:s', strtotime('-1 day')));
        $this->attendance($other, $this->event($other, 101), 101);
        $this->assertSame([$open], $this->statusIds($this->generateTokenForUser($user), 101));
    }
}
