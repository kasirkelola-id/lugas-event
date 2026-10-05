# Batch 4 disposable MySQL 8 checks

These six tests are **MySQL-only**. Default PHPUnit runs skip them. SQLite
serial tests do not prove the approval/return/decision races or MySQL DDL.
The guard tests run without opening a connection.

Use a dedicated local MySQL 8 instance, with no production credentials, proxy
or SSH tunnel. The hostname is fixed to `127.0.0.1`; MariaDB is rejected. Provide
an explicit port and test user through these environment variables:

```powershell
$env:CI_ENVIRONMENT = 'testing'
$env:FCM_MOCK = 'true'
$env:KARTAR_MYSQL_TEST_ENABLE = '1'
$env:KARTAR_MYSQL_TEST_PORT = '3307' # actual dedicated local MySQL port
$env:KARTAR_MYSQL_TEST_USER = 'kartar_local_test'
# Set KARTAR_MYSQL_TEST_PASSWORD privately if this test user requires it.
php vendor/bin/phpunit tests/MySQL/InventorySchemaMySQLTest.php --colors=never
```

Run from `website`. The local test account needs permission to create/drop its
disposable schemas, migrate tables, inspect DDL/indexes, and create a failure
trigger. Never supply an existing application schema. The fixture generates
`kartar_batch4_test_<random>` names and uses plain CREATE DATABASE, rejecting a
collision instead of adopting an existing database. It drops only schemas it
successfully created. No MySQL credentials are passed on the command line or
printed in the test evidence. Keep the opt-in unset during ordinary SQLite runs.

Cases:

1. Two real PHP/MySQL connections approve different loans against stock 1:
   one 200, one 409, one approved loan, stock 0.
2. Two returns of the same loan: both may return 200, exactly one mutation,
   status returned, stock restored once.
3. Approve/reject after both transactions observed pending: one 200 and one
   409, with stock matching the winning state. A later intentional rejection of
   an already-approved loan remains supported.
4. A MySQL BEFORE UPDATE trigger forces the second write to fail through the
   real controller: 500, original stock and loan intact, no notification.
5. The entire current migration chain on a newly empty disposable database;
   SHOW CREATE TABLE/SHOW INDEX evidence, InnoDB engines, global-user writes,
   global0/tenant101/tenant102 settings coexistence, and a child PHPUnit run of
   the existing application create/register and participant boundary cases.
6. Historical schema constructed only in a new empty fixture, then synthetic
   data inserted and **only the new migration** applied. Existing users must
   survive and the four relevant tables' DDL must match another fresh fixture
   after ignoring constraint/index names and AUTO_INCREMENT counters. The
   synthetic baseline explicitly makes users tenant NOT NULL to exercise the
   stricter expected old deployment; current Forge ADD COLUMN without an
   explicit NULL option actually produces a nullable column on MySQL.

The concurrency read barrier is test orchestration between independent worker
processes, not an application delay/retry mechanism. Application correctness
comes from the transaction, row locks, source-state comparison and write checks.
Races invoke the service directly. Rollback invokes the real controller with an
in-memory notification probe. Fresh-schema application cases use FeatureTest
without an HTTP listener and retain the existing cURL/FCM mocks. No real delivery
or external HTTP is performed. Failed query numeric codes are recorded, including
lock/deadlock errors; application retries are not added.

Completed race and schema evidence is written to local TEMP JSON files named
`kartar_batch4_barrier_*.json` and `kartar_batch4_test_*_ddl.json`; these contain
synthetic fixture data, versions, final stock/statuses and responses. Nothing is
written to the existing tools directories. A killed runner may leave a randomly
named disposable schema; inspect ownership before any manual cleanup.

On a dedicated disposable server with binary logging enabled, the failure
trigger needs either an appropriately privileged test account or the temporary
server setting `log_bin_trust_function_creators=1`. Never change an unrelated
server to enable the fixture. Batch 4.1 uses a portable MySQL 8.0.46 instance on
127.0.0.1:3308, separate from XAMPP MariaDB and Laragon data. See the canonical
audit's Batch 4.1 ledger for actual results, preserved evidence and cleanup.

Real MySQL revealed that the historical participant tenant ADD COLUMN is
nullable/default NULL. The fixture records that DDL and proves the application
always supplies the authenticated tenant; it does not change historical
migrations or invent a production NOT NULL guarantee. Production DDL remains
unverified.

## Additional overnight verification

`OvernightConcurrencyMySQLTest.php` adds ten opt-in cases using the same strict
new-schema guard: duplicate chat writers/atomic outbox, competing membership
decisions, competing identity creation, idempotent participant batches, concurrent
queue claims/one receipt, wheel spin versus close, forced outbox rollback, and
row-preserving measured-index reapplication/directions, and bounded maintenance
with current rows preserved/session settings restored, and legacy/browser
announcement authors with row-preserving upgrade/reapplication and outbox/FK
behavior. Each fresh fixture runs
the complete current migration chain. Numeric database failures are retained in
synthetic TEMP evidence; failed attempts do not count as final proof. Standalone
workers initialize the normal App timezone and keep all provider calls mocked.

Run `php vendor/bin/phpunit tests/MySQL` with the explicit local test environment
above to execute both classes. Without opt-in, all sixteen MySQL cases skip.
For the guarded 10/100-tenant query/dataset probe and exact EXPLAIN evidence, see
`deploy/MYSQL_QUERY_VERIFICATION.md` at the repository root. That probe verifies
controllers/database behavior without claiming authenticated HTTP load capacity.
