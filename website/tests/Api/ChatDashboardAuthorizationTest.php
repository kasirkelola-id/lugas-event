<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;

final class ChatDashboardAuthorizationTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';

    /** @dataProvider deniedRoles */
    public function testTenantMembershipDoesNotGrantChatPermission(string $role): void
    {
        $user = $this->createTestUser(101, $role);
        $other = $this->createTestUser(101);
        $headers = $this->getAuthHeaders($this->generateTokenForUser($user));
        foreach (['api/chats/rooms', 'api/chats/rooms/1/messages', 'api/chats/private-contacts',
            'api/chats/private/' . $other['id']] as $path) {
            $this->withHeaders($headers)->get($path)->assertStatus(403);
        }
        $this->withHeaders($headers)->withBodyFormat('json')->post('api/chats/messages',
            ['type' => 'private', 'receiver_id' => $other['id'], 'message' => 'Synthetic'])->assertStatus(403);
        $this->assertSame(0, \Config\Database::connect()->table('chats')->countAllResults());
    }

    public static function deniedRoles(): array
    {
        return [['wakil_ketua'], ['wakil_sekretaris'], ['wakil_bendahara'], ['seksi']];
    }

    public function testDashboardAnnouncementPreviewAndCountRespectRoleAndPermission(): void
    {
        $author = $this->createTestUser(101, 'ketua');
        $member = $this->createTestUser(101);
        $denied = $this->createTestUser(101, 'seksi');
        $db = \Config\Database::connect();
        foreach (['semua', 'ketua', 'ketua'] as $i => $role) {
            $db->table('pengumuman')->insert(['karang_taruna_id' => 101, 'dibuat_oleh' => $author['id'],
                'judul' => $role === 'semua' ? 'Public synthetic' : 'Restricted synthetic', 'isi' => 'Synthetic body',
                'target_role' => $role, 'status_aktif' => 1, 'dashboard_until' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => date('Y-m-d H:i:s', time() - 30 + $i)]);
        }
        foreach ([[$member, 'Public synthetic', 0], [$author, 'Restricted synthetic', 2], [$denied, null, 0]] as [$user, $title, $count]) {
            $result = $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($user)))->get('api/dashboard');
            $result->assertStatus(200);
            $activity = json_decode($result->getJSON(), true)['data']['community_activity']['announcement'];
            $this->assertSame($title, $activity['item']['title'] ?? null);
            $this->assertSame($count, $activity['additional_count']);
            if ($title !== 'Restricted synthetic') $this->assertStringNotContainsString('Restricted synthetic', $result->getJSON());
        }
    }
}
