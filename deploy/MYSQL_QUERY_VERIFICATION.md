# Local MySQL query verification and index rollout

The explicit opt-in probe `website/tests/_support/mysql_query_probe.php` accepts
only 10 or 100 tenants. `DisposableMySQL` checks both testing environments, an
explicit local port/user and MySQL 8 before creating a random new schema. Host is
hardcoded to loopback; application credentials are never used. Historical
migrations run only in the newly empty synthetic database, and the owned schema
is dropped in `finally`. Providers remain mocked. Never run this against an
application database or import real data into this fixture.

For 100 tenants it creates 10,000 users/memberships, 10,000 events, and 100,000
rows each in attendance, cash, chat and the transactionally triggered outbox.
It also seeds loans, wheel histories and voting results. Passwords are empty,
non-authenticatable fixture values. The ten collection/detail
probes invoke the actual controllers with synthetic AuthService context; this
is database/controller verification, **not authenticated HTTP load testing**.
They verify response bounds, tenant scope and full cash balance. SQL and both
normal and JSON EXPLAIN plans contain synthetic data only. EXPLAIN `rows` is an
optimizer estimate, not a measured count of rows read. Individual timings are
local observations, not percentiles or production capacity.

The 100-tenant baseline selected a full table scan/filesort for the queue's OR
claim and filesort for cash pages. Forward migration
`2026-10-06-000006_AddMeasuredQueryIndexes.php` adds only:

* Cash `(karang_taruna_id ASC, tanggal DESC, created_at DESC, id ASC)` for the
  actual page order and its ID tie breaker.
* Jobs `(status, lease_expires_at, id)` for expired-owner recovery.
* Wheel results `(session_id ASC, spin_sequence DESC, id ASC)` for the actual
  bounded history order. The 1,000-result/session baseline used filesort.

The existing jobs `(status, next_attempt_at, id)` index handles due pending jobs.
The worker first reads one expired-lease candidate using an ordered index range,
then one due pending candidate if no lease needs recovery, inside its existing
owned transaction. These candidate reads do not take secondary-index gap locks.
It locks just the selected primary-key row and rechecks current eligibility; a
competing winner returns no work. It retains conditional lease ownership, checked commit, finite
attempts and provider I/O outside transactions. Recovery therefore has priority
over fresh work; monitor stuck processing jobs and backlog before adding workers.

The migration preflights conflicting names and reuses full-column equivalent
indexes with identical directions. It preserves all rows and historical
migrations. Removal requires explicit ownership/query-plan review, because an
operator-created equivalent index may have been reused. MySQL DDL is not one
rollbackable transaction: inspect actual indexes and migration history after any
partial failure, then rerun this idempotent forward migration under maintenance
control. Do not drop an existing index to bypass a conflict.

Before staging/production rollout, inspect exact server version, engines,
existing indexes and migration history, measure index-build time/disk/write cost
on a restored representative staging copy, and review locking/maintenance
requirements. Local MySQL 8.0.46 evidence does not establish production DDL,
multi-worker throughput, real FCM latency, or production scheduler installation.
Attendance/event ordering and private-chat plans already used existing scoped
indexes on this fixture; no speculative indexes were added to them. Unmeasured
query variants remain separate verification work. Loan pages still sort the
scoped join result; wheel/voting lists and grouped counts retain small scoped
sort/temporary operations. These plans are disclosed, not claimed eliminated.
