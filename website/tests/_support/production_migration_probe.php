<?php

// Standalone policy probe: no app bootstrap, dotenv, database or network.
namespace CodeIgniter\Database {
    class MigrationRunner {
        public int $executed = 0;
        protected function migrate($direction, $migration): bool { $this->executed++; return true; }
    }
}
namespace {
    define('ENVIRONMENT', 'production');
    require __DIR__ . '/../../app/Database/SafeMigrationRunner.php';
    $runner = new class extends \App\Database\SafeMigrationRunner {
        public function probe($direction, $class): bool { return $this->migrate($direction, (object)['class' => $class]); }
    };
    $result = [];
    foreach (['up', 'down'] as $direction) {
        try {
            $runner->probe($direction, '\\App\\Database\\Migrations\\AddTenantIdToAllTables');
            $result[$direction . '_blocked'] = false;
        } catch (\RuntimeException $error) {
            $result[$direction . '_blocked'] = true;
        }
    }
    $result['destructive_executions'] = $runner->executed;
    $result['forward_allowed'] = $runner->probe('up', 'App\\Database\\Migrations\\AlignGlobalIdentityAndSettingsSchema');
    echo json_encode($result);
}
