<?php

namespace Tests\Support;

use Config\Database;

/** Explicit local-only opt-in. Creates/drops only a newly generated test schema. */
final class DisposableMySQL
{
    public readonly string $schema;
    public readonly string $version;
    private $admin;
    private bool $created = false;

    public static function configuration(string $schema = ''): array
    {
        if (ENVIRONMENT !== 'testing' || getenv('CI_ENVIRONMENT') !== 'testing'
            || getenv('KARTAR_MYSQL_TEST_ENABLE') !== '1') {
            throw new \RuntimeException('Disposable MySQL requires explicit testing opt-in');
        }
        if ($schema !== '' && !preg_match('/\Akartar_batch4_test_[a-f0-9]{16}\z/', $schema)) {
            throw new \RuntimeException('Refusing a schema outside the disposable test namespace');
        }
        $port = filter_var(getenv('KARTAR_MYSQL_TEST_PORT'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $user = getenv('KARTAR_MYSQL_TEST_USER');
        if ($port === false || !$user) {
            throw new \RuntimeException('Set an explicit local test port and test user');
        }
        return [
            'DSN' => '', 'hostname' => '127.0.0.1', 'port' => $port,
            'username' => $user, 'password' => getenv('KARTAR_MYSQL_TEST_PASSWORD') ?: '',
            'database' => $schema, 'DBDriver' => 'MySQLi', 'DBPrefix' => '',
            'pConnect' => false, 'DBDebug' => true, 'strictOn' => true,
            'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci',
        ];
    }

    public function __construct()
    {
        $this->admin = Database::connect(self::configuration(), false);
        $this->version = (string) $this->admin->query('SELECT VERSION() AS version')->getRow()->version;
        if (!preg_match('/\A8\./', $this->version) || stripos($this->version, 'MariaDB') !== false) {
            $this->admin->close();
            throw new \RuntimeException('This fixture requires MySQL 8, not MariaDB or SQLite');
        }
        $this->schema = 'kartar_batch4_test_' . bin2hex(random_bytes(8));
        // No IF NOT EXISTS: never adopt or erase an existing database.
        $this->admin->query('CREATE DATABASE `' . $this->schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        $this->created = true;
    }

    public function connect()
    {
        return Database::connect(self::configuration($this->schema), false);
    }

    public function close(): void
    {
        if ($this->created) {
            self::configuration($this->schema); // Revalidate before DROP.
            $this->admin->query('DROP DATABASE `' . $this->schema . '`');
            $this->created = false;
        }
        $this->admin->close();
    }
}
