<?php

namespace Tests\Api;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTrait;
use Tests\Support\BaseTest;
use App\Services\ManagedImageService;

final class ManagedImageOrderingTest extends BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;
    protected $namespace = 'App';
    private array $files = [];

    private function file(string $directory): string
    {
        $path = $directory . 'ordering_' . bin2hex(random_bytes(12)) . '.png';
        if (!is_dir(FCPATH . $directory)) mkdir(FCPATH . $directory, 0750, true);
        file_put_contents(FCPATH . $path, 'synthetic-owned-file');
        $this->files[] = FCPATH . $path;
        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    public function test_failed_logo_write_keeps_old_file_and_database(): void
    {
        $this->createTestUser(101);
        $logo = $this->file('uploads/karang_taruna/logos/');
        $this->db->table('karang_taruna')->where('id', 101)->update(['logo_path' => $logo]);
        $before = $this->db->table('karang_taruna')->where('id', 101)->get()->getRowArray();
        $this->db->table('kelurahan')->insert(['nama' => 'Synthetic']);
        $kel = $this->db->insertID();
        $hash = password_hash('synthetic-private-admin', PASSWORD_BCRYPT);
        $this->db->table('superadmins')->insert(['id' => 1, 'username' => 'synthetic', 'nama_lengkap' => 'Synthetic', 'password' => $hash]);
        \Config\Services::resetSingle('security');
        $this->db->query("CREATE TRIGGER logo_write_failure BEFORE UPDATE ON karang_taruna BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $this->withSession(['is_superadmin_logged_in' => true, 'superadmin_id' => 1,
                    'superadmin_credential_version' => hash('sha256', $hash), 'csrf_test_name' => str_repeat('a', 64)])
                    ->withHeaders(['X-CSRF-TOKEN' => str_repeat('a', 64)])->post('superadmin/karang_taruna/update/101', [
                        'nama_organisasi' => 'Synthetic', 'nama_ketua' => '', 'alamat_lengkap' => '',
                        'status_aktif' => '1', 'kelurahan_id' => $kel, 'remove_logo' => '1'])->assertRedirect();
            $this->assertSame($before, $this->db->table('karang_taruna')->where('id', 101)->get()->getRowArray());
            $this->assertFileExists(FCPATH . $logo);
        } finally {
            $this->db->query('DROP TRIGGER logo_write_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_failed_profile_delete_returns_controlled_failure_and_keeps_file(): void
    {
        $user = $this->createTestUser(101);
        $token = $this->generateTokenForUser($user);
        $path = $this->file('uploads/users/profile/');
        $this->db->table('users')->where('id', $user['id'])->update(['profile_photo' => $path]);
        $this->db->query("CREATE TRIGGER profile_write_failure BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'synthetic-private-detail'); END");
        try {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'X-Karang-Taruna-ID' => '101'])->delete('api/profile/photo');
            $response->assertStatus(500);
            $this->assertStringNotContainsString('synthetic-private-detail', $response->getJSON());
            $this->assertSame($path, $this->db->table('users')->where('id', $user['id'])->get()->getRowArray()['profile_photo']);
            $this->assertFileExists(FCPATH . $path);
        } finally {
            $this->db->query('DROP TRIGGER profile_write_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_shared_legacy_photo_is_retired_only_after_last_reference(): void
    {
        $first = $this->createTestUser(101, 'anggota', 'first');
        $second = $this->createTestUser(101, 'anggota', 'second');
        $path = $this->file('uploads/users/profile/');
        $this->db->table('users')->whereIn('id', [$first['id'], $second['id']])->update(['profile_photo' => $path]);
        ManagedImageService::change('users', (int)$first['id'], ['profile_photo' => null]);
        $this->assertFileExists(FCPATH . $path);
        ManagedImageService::change('users', (int)$second['id'], ['profile_photo' => null]);
        $this->assertFileDoesNotExist(FCPATH . $path);
        $this->assertSame(0, $this->db->transDepth);
    }

    public function test_failed_replacement_preserves_old_file_and_cleans_unreferenced_new_file(): void
    {
        $user = $this->createTestUser(101);
        $old = $this->file('uploads/users/profile/');
        $new = $this->file('uploads/users/profile/');
        $this->db->table('users')->where('id', $user['id'])->update(['profile_photo' => $old]);
        $this->db->query("CREATE TRIGGER replacement_failure BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        try {
            try {
                ManagedImageService::change('users', (int)$user['id'], ['profile_photo' => $new]);
                $this->fail('Failed update must throw');
            } catch (\RuntimeException $error) {
                ManagedImageService::retire($new);
            }
            $this->assertFileExists(FCPATH . $old);
            $this->assertFileDoesNotExist(FCPATH . $new);
            $this->assertSame($old, $this->db->table('users')->where('id', $user['id'])->get()->getRowArray()['profile_photo']);
        } finally {
            $this->db->query('DROP TRIGGER replacement_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_failed_default_room_does_not_leave_organization(): void
    {
        $this->db->query("CREATE TRIGGER room_write_failure BEFORE INSERT ON chat_rooms BEGIN SELECT RAISE(ABORT, 'synthetic'); END");
        try {
            try {
                ManagedImageService::createOrganization(['nama_organisasi' => 'Synthetic', 'kode_pin' => '999991', 'status_aktif' => 1]);
                $this->fail('Failed room insert must throw');
            } catch (\RuntimeException $error) {
                $this->assertSame(0, $this->db->table('karang_taruna')->where('kode_pin', '999991')->countAllResults());
                $this->assertSame(0, $this->db->transDepth);
            }
        } finally {
            $this->db->query('DROP TRIGGER room_write_failure');
            $this->db->resetTransStatus();
        }
    }

    public function test_unmanaged_and_nested_paths_are_never_retired(): void
    {
        $path = $this->file('uploads/users/profile/');
        ManagedImageService::retire('uploads/users/profile/../profile/' . basename($path));
        $this->assertFileExists(FCPATH . $path);
        ManagedImageService::retire($path);
        $this->assertFileDoesNotExist(FCPATH . $path);
    }
}
