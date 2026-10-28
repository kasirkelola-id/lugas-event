<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** Dedicated SQLite memory connection: never runs migrations or seeders. */
abstract class PasswordFixtureTest extends CIUnitTestCase
{
    protected $db;
    private array $previousConnections;

    protected function setUp(): void
    {
        parent::setUp();
        if (ENVIRONMENT !== 'testing') throw new \RuntimeException('Testing only');
        $this->db = Database::connect([
            'DSN' => '', 'DBDriver' => 'SQLite3', 'database' => ':memory:',
            'DBPrefix' => '', 'DBDebug' => true, 'foreignKeys' => true,
            'busyTimeout' => 1000, 'dateFormat' => [
                'date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
        ], false);
        // Controllers/models resolve the normal tests group to this isolated connection.
        $property = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
        $property->setAccessible(true);
        $this->previousConnections = $property->getValue();
        $property->setValue(null, array_replace($this->previousConnections, ['tests' => $this->db]));
        $tables = [
            'users' => 'id INTEGER PRIMARY KEY, karang_taruna_id INTEGER, username TEXT,
                password TEXT, nama_lengkap TEXT, nama_panggilan TEXT, no_whatsapp TEXT,
                rt INTEGER DEFAULT 1, role_level TEXT, status_aktif INTEGER DEFAULT 1,
                password_must_change INTEGER DEFAULT 0, profile_photo TEXT,
                created_at TEXT, updated_at TEXT',
            'karang_taruna' => 'id INTEGER PRIMARY KEY, nama_organisasi TEXT, kode_pin TEXT,
                alamat_lengkap TEXT, status_aktif INTEGER DEFAULT 1, logo_path TEXT',
            'organization_members' => 'id INTEGER PRIMARY KEY, user_id INTEGER,
                karang_taruna_id INTEGER, username TEXT, role_level TEXT,
                status_aktif INTEGER DEFAULT 1, approval_status TEXT DEFAULT \'approved\',
                joined_at TEXT, created_at TEXT, updated_at TEXT',
            'user_tokens' => 'id INTEGER PRIMARY KEY, user_id INTEGER, superadmin_id INTEGER,
                credential_version TEXT, karang_taruna_id INTEGER, token_hash TEXT UNIQUE,
                revoked_at TEXT, expires_at TEXT, created_at TEXT',
            'superadmins' => 'id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT,
                nama_lengkap TEXT, created_at TEXT, updated_at TEXT',
            'settings' => 'id INTEGER PRIMARY KEY, karang_taruna_id INTEGER,
                setting_key TEXT, setting_value TEXT, description TEXT,
                created_at TEXT, updated_at TEXT',
        ];
        foreach ($tables as $table => $columns) $this->db->query("CREATE TABLE $table ($columns)");
        $client = $this->getMockBuilder(\CodeIgniter\HTTP\CURLRequest::class)
            ->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $client->expects($this->never())->method('request');
        \Config\Services::injectMock('curlrequest', $client);
        $this->db->table('karang_taruna')->insert([
            'id' => 101, 'nama_organisasi' => 'Synthetic tenant', 'kode_pin' => '123456']);
    }

    protected function tearDown(): void
    {
        try {
            $this->db->close();
            $property = new \ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
            $property->setAccessible(true);
            $property->setValue(null, $this->previousConnections);
            \App\Services\AuthService::setUser(null);
            \App\Services\AuthService::setToken(null);
        } finally {
            parent::tearDown();
        }
    }
}
