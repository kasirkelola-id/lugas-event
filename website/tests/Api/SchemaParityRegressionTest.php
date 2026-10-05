<?php

namespace Tests\Api;

use App\Database\Migrations\AlignGlobalIdentityAndSettingsSchema;
use App\Models\SettingModel;
use App\Services\SettingService;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Tests\Support\AuthTrait;

/** SQLite/application evidence only; real MySQL evidence lives in tests/MySQL. */
class SchemaParityRegressionTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetTransStatus();
        SettingService::clearCache();
        require_once APPPATH . 'Database/Migrations/2026-10-05-000001_AlignGlobalIdentityAndSettingsSchema.php';
    }

    public function testCreateAndRegisterOmitLegacyTenantWhileMembershipOwnsTenant(): void
    {
        $admin = $this->createTestUser(101, 'ketua');
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($admin)))
            ->withBodyFormat('json')->post('api/users', [
                'username' => 'batch4_created', 'nama_lengkap' => 'Created identity',
                'nama_panggilan' => 'Created', 'role_level' => 'anggota', 'rt' => 1,
            ])->assertStatus(201);
        $this->withBodyFormat('json')->post('api/register', [
            'karang_taruna_id' => 101, 'username' => 'batch4_registered',
            'nama_lengkap' => 'Registered identity', 'nama_panggilan' => 'Registered',
            'password' => 'synthetic-password', 'confirm_password' => 'synthetic-password',
            'no_whatsapp' => '08004440001', 'rt' => 1,
        ])->assertStatus(201);
        foreach (['batch4_created' => 'approved', 'batch4_registered' => 'pending'] as $name => $approval) {
            $user = $this->db->table('users')->where('username', $name)->get()->getRowArray();
            $member = $this->db->table('organization_members')->where('user_id', $user['id'])->get()->getRowArray();
            $this->assertNull($user['karang_taruna_id']);
            $this->assertSame(101, (int) $member['karang_taruna_id']);
            $this->assertSame($approval, $member['approval_status']);
        }
    }

    public function testMigrationsProvideSettingIdsAndTenantPairUniquenessIncludingZero(): void
    {
        // Use the migrated table directly, without dropping/recreating a fixture.
        $model = new SettingModel();
        $ids = [];
        foreach ([0, 101, 102] as $tenant) {
            $ids[$tenant] = $model->insert([
                'karang_taruna_id' => $tenant, 'setting_key' => 'batch4_same_key', 'setting_value' => (string) $tenant,
            ]);
            $this->assertGreaterThan(0, $ids[$tenant]);
            $this->assertSame((string) $tenant, SettingService::getSetting($tenant, 'batch4_same_key'));
        }
        $this->assertCount(3, array_unique($ids));
        $this->assertTrue($model->update($ids[101], ['setting_value' => 'changed']));
        $this->assertSame('changed', $model->find($ids[101])['setting_value']);
        $this->assertSame('0', $model->find($ids[0])['setting_value']);
        try {
            $this->assertFalse($this->db->table('settings')->insert([
                'karang_taruna_id' => 101, 'setting_key' => 'batch4_same_key', 'setting_value' => 'duplicate',
            ]));
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $error) {
            $this->assertStringContainsString('UNIQUE', $error->getMessage());
        }
    }

    private function oldSettings()
    {
        $db = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => '', 'DBDebug' => true], false);
        $db->query('CREATE TABLE settings (setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT, description VARCHAR(255), created_at DATETIME, updated_at DATETIME,
            karang_taruna_id INTEGER)');
        return $db;
    }

    public function testForwardSettingsUpgradePreservesExistingValuesAndSupportsTenantCopies(): void
    {
        $db = $this->oldSettings();
        try {
            $original = ['setting_key' => 'kept', 'setting_value' => 'value', 'description' => 'description',
                'karang_taruna_id' => 0, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-02 00:00:00'];
            $db->table('settings')->insert($original);
            $migration = new AlignGlobalIdentityAndSettingsSchema(Database::forge($db));
            $migration->up();
            $row = $db->table('settings')->get()->getRowArray();
            $this->assertGreaterThan(0, $row['id']);
            unset($row['id']);
            $this->assertEquals($original, $row);
            $this->assertTrue($db->table('settings')->insert(['setting_key' => 'kept', 'karang_taruna_id' => 101]));
            $migration->up(); // Already-aligned schema remains usable.
            $this->assertSame(2, $db->table('settings')->countAllResults());
        } finally {
            $db->close();
        }
    }

    public function testFailedSettingsCopyRollsBackWithoutDroppingOriginalData(): void
    {
        $db = $this->oldSettings();
        try {
            $db->table('settings')->insert(['setting_key' => 'invalid_null_tenant', 'setting_value' => 'keep', 'karang_taruna_id' => null]);
            try {
                (new AlignGlobalIdentityAndSettingsSchema(Database::forge($db)))->up();
                $this->fail('Invalid legacy data must stop schema alignment');
            } catch (\Throwable $error) {
                $this->assertTrue(str_contains($error->getMessage(), 'NOT NULL')
                    || str_contains($error->getMessage(), 'Settings schema rebuild failed'));
            }
            $this->assertFalse($db->fieldExists('id', 'settings'));
            $this->assertSame('keep', $db->table('settings')->get()->getRow()->setting_value);
            $this->assertFalse($db->tableExists('settings_batch4_new'));
            $this->assertSame(0, $db->transDepth);
        } finally {
            $db->close();
        }
    }

    public function testDownRefusesLossyLegacyReconstruction(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Forward-only');
        (new AlignGlobalIdentityAndSettingsSchema(Database::forge($this->db)))->down();
    }

    public function testParticipantInsertDerivesTenantAndGetRouteKeepsPhoneContract(): void
    {
        $admin = $this->createTestUser(101, 'ketua');
        $member = $this->createTestUser(101);
        $other = $this->createTestUser(102);
        $this->db->table('events')->insert([
            'karang_taruna_id' => 101, 'nama_acara' => 'Batch 4 event', 'tanggal_acara' => date('Y-m-d'),
            'dibuat_oleh' => $admin['id'], 'kode_qr' => 'BATCH4', 'status_aktif' => 'aktif',
        ]);
        $event = (int) $this->db->insertID();
        $headers = $this->getAuthHeaders($this->generateTokenForUser($admin));
        $this->withHeaders($headers)->withBodyFormat('json')->post('api/events/' . $event . '/participants', [
            'user_ids' => [$member['id'], $other['id']], 'karang_taruna_id' => 102,
        ])->assertStatus(200);
        $rows = $this->db->table('event_participants')->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertSame(101, (int) $rows[0]['karang_taruna_id']);
        $this->assertSame((int) $member['id'], (int) $rows[0]['user_id']);
        $response = $this->withHeaders($headers)->get('api/events/' . $event . '/participants');
        $response->assertStatus(200);
        $this->assertSame($member['no_whatsapp'], json_decode($response->getJSON(), true)['data'][0]['whatsapp']);
        $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($other)))
            ->get('api/events/' . $event . '/participants')->assertStatus(403);
        $this->db->table('events')->insert([
            'karang_taruna_id' => 102, 'nama_acara' => 'Foreign event', 'tanggal_acara' => date('Y-m-d'),
            'dibuat_oleh' => $other['id'], 'kode_qr' => 'BATCH4FOREIGN', 'status_aktif' => 'aktif',
        ]);
        $foreignEvent = (int) $this->db->insertID();
        $this->withHeaders($headers)->withBodyFormat('json')->post('api/events/' . $foreignEvent . '/participants', [
            'user_ids' => [$member['id']], 'karang_taruna_id' => 101,
        ])->assertStatus(403);
        $this->assertSame(0, $this->db->table('event_participants')->where('event_id', $foreignEvent)->countAllResults());
    }
}
