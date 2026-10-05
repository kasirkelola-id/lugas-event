<?php

namespace Tests\Api;

use App\Controllers\Api\InventoryController;
use App\Services\AuthService;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthTrait;

/** Serial SQLite regressions; these do not prove MySQL row-lock concurrency. */
class InventoryAtomicityTest extends \Tests\Support\BaseTest
{
    use FeatureTestTrait;
    use AuthTrait;

    protected $migrate = true;
    protected $namespace = 'App';
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Historical SQLite DDL compatibility leaves a dirty test status.
        $this->db->resetTransStatus();
        $this->admin = $this->createTestUser(101, 'ketua');
        InventoryNotificationProbe::$notifications = [];
    }

    protected function tearDown(): void
    {
        AuthService::setUser(null);
        // A deliberately failed DB write must not poison the next test.
        $this->db->resetTransStatus();
        parent::tearDown();
    }

    private function seedLoan(string $state, int $available, int $tenant = 101, int $quantity = 1): array
    {
        if ($tenant !== 101) {
            $this->createTestUser($tenant);
        }
        $this->db->table('inventories')->insert([
            'karang_taruna_id' => $tenant, 'name' => 'Batch 4 inventory',
            'total_quantity' => 2, 'available_quantity' => $available,
        ]);
        $inventory = (int) $this->db->insertID();
        $this->db->table('inventory_loans')->insert([
            'inventory_id' => $inventory, 'user_id' => $this->admin['id'],
            'quantity' => $quantity, 'status' => $state,
            'borrow_date' => date('Y-m-d'), 'return_date' => date('Y-m-d'),
        ]);
        return [$inventory, (int) $this->db->insertID()];
    }

    private function request(int $loan, string $status)
    {
        return $this->withHeaders($this->getAuthHeaders($this->generateTokenForUser($this->admin)))
            ->withBodyFormat('json')->patch('api/inventories/loans/' . $loan . '/status', ['status' => $status]);
    }

    private function probe(int $loan, string $status)
    {
        // Execute the real controller, substituting only its postcommit notifier.
        AuthService::setUser($this->admin);
        $request = Services::request()->setMethod('patch')->setBody(json_encode(['status' => $status]));
        $controller = new InventoryNotificationProbe();
        $controller->initController($request, Services::response(), Services::logger());
        return $controller->changeLoanStatus($loan);
    }

    public static function sameStatuses(): array
    {
        return [['approved'], ['returned'], ['rejected']];
    }

    #[DataProvider('sameStatuses')]
    public function testForeignSameStatusNeverReturnsSuccess(string $status): void
    {
        [$inventory, $loan] = $this->seedLoan($status, 1, 102);
        $this->request($loan, $status)->assertStatus(404);
        $this->assertSame(1, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
    }

    public static function transitions(): array
    {
        return [
            ['pending', 'approved', 2, 1],
            ['pending', 'rejected', 2, 2],
            ['approved', 'returned', 1, 2],
            ['approved', 'rejected', 1, 2],
        ];
    }

    #[DataProvider('transitions')]
    public function testExistingTransitionCommitsBothWritesBeforeNotification(string $from, string $to, int $before, int $after): void
    {
        [$inventory, $loan] = $this->seedLoan($from, $before);
        $this->assertSame(200, $this->probe($loan, $to)->getStatusCode());
        $this->assertSame($to, $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
        $this->assertSame($after, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
        $this->assertSame([['depth' => 0, 'status' => $to, 'stock' => $after]], InventoryNotificationProbe::$notifications);
    }

    #[DataProvider('sameStatuses')]
    public function testOwnedSameStatusIsSuccessWithoutWritesOrNotification(string $status): void
    {
        [$inventory, $loan] = $this->seedLoan($status, 1);
        $this->assertSame(200, $this->probe($loan, $status)->getStatusCode());
        $this->assertSame([], InventoryNotificationProbe::$notifications);
        $this->assertSame(1, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
        $this->assertSame(0, $this->db->transDepth);
    }

    public function testLastStockCannotBeConsumedByTwoSerialApprovals(): void
    {
        [$inventory, $first] = $this->seedLoan('pending', 1);
        $this->db->table('inventory_loans')->insert([
            'inventory_id' => $inventory, 'user_id' => $this->admin['id'], 'quantity' => 1, 'status' => 'pending',
        ]);
        $second = (int) $this->db->insertID();
        $this->request($first, 'approved')->assertStatus(200);
        $this->request($second, 'approved')->assertStatus(409);
        $this->assertSame(0, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
        $this->assertSame('pending', $this->db->table('inventory_loans')->where('id', $second)->get()->getRow()->status);
    }

    public function testRepeatedReturnRestoresStockExactlyOnce(): void
    {
        [$inventory, $loan] = $this->seedLoan('approved', 1);
        $this->request($loan, 'returned')->assertStatus(200);
        $this->request($loan, 'returned')->assertStatus(200);
        $this->assertSame(2, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
    }

    public function testInsufficientStockAndInvalidTransitionDoNotNotify(): void
    {
        [$inventory, $loan] = $this->seedLoan('pending', 0);
        $this->assertSame(409, $this->probe($loan, 'approved')->getStatusCode());
        $this->assertSame(409, $this->probe($loan, 'returned')->getStatusCode());
        $this->assertSame([], InventoryNotificationProbe::$notifications);
        $this->assertSame('pending', $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
        $this->assertSame(0, $this->db->transDepth);
    }

    public function testCorruptStockBoundsCannotBeMadeWorse(): void
    {
        [$inventory, $loan] = $this->seedLoan('approved', 2);
        $this->assertSame(409, $this->probe($loan, 'returned')->getStatusCode());
        $this->assertSame(2, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
        $this->assertSame('approved', $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
    }

    public function testSecondWriteFailureRollsBackStockAndLoanAndNeverNotifies(): void
    {
        [$inventory, $loan] = $this->seedLoan('pending', 2);
        $this->db->query("CREATE TRIGGER batch4_fail_loan BEFORE UPDATE ON inventory_loans BEGIN SELECT RAISE(ABORT, 'forced second write failure'); END");
        try {
            $this->assertSame(500, $this->probe($loan, 'approved')->getStatusCode());
            $this->assertSame(2, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
            $this->assertSame('pending', $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
            $this->assertSame([], InventoryNotificationProbe::$notifications);
            $this->assertSame(0, $this->db->transDepth);
        } finally {
            $this->db->query('DROP TRIGGER batch4_fail_loan');
        }
    }

    public static function ignoredWriteTables(): array
    {
        return [['inventories'], ['inventory_loans']];
    }

    #[DataProvider('ignoredWriteTables')]
    public function testSuccessfulQueryWithZeroAffectedRowsIsStillRolledBack(string $table): void
    {
        [$inventory, $loan] = $this->seedLoan('pending', 2);
        $this->db->query("CREATE TRIGGER batch4_ignore_write BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(IGNORE); END");
        try {
            $this->assertSame(500, $this->probe($loan, 'approved')->getStatusCode());
            $this->assertSame(2, (int) $this->db->table('inventories')->where('id', $inventory)->get()->getRow()->available_quantity);
            $this->assertSame('pending', $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
            $this->assertSame([], InventoryNotificationProbe::$notifications);
            $this->assertSame(0, $this->db->transDepth);
        } finally {
            $this->db->query('DROP TRIGGER batch4_ignore_write');
        }
    }

    public function testExistingOuterTransactionCannotCausePrematureNotification(): void
    {
        [$inventory, $loan] = $this->seedLoan('pending', 2);
        $this->db->transBegin();
        try {
            $this->assertSame(500, $this->probe($loan, 'approved')->getStatusCode());
            $this->assertSame(1, $this->db->transDepth);
            $this->assertSame([], InventoryNotificationProbe::$notifications);
            $this->assertSame('pending', $this->db->table('inventory_loans')->where('id', $loan)->get()->getRow()->status);
        } finally {
            $this->db->transRollback();
        }
    }
}

class InventoryNotificationProbe extends InventoryController
{
    public static array $notifications = [];

    protected function notifyLoanTransition(array $loan, array $inventory, int $tenantId, string $status): void
    {
        $db = \Config\Database::connect();
        self::$notifications[] = [
            'depth' => $db->transDepth,
            'status' => $db->table('inventory_loans')->where('id', $loan['id'])->get()->getRow()->status,
            'stock' => (int) $db->table('inventories')->where('id', $inventory['id'])->get()->getRow()->available_quantity,
        ];
    }
}
