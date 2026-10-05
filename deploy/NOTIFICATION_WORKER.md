# Durable notifications: rollout and worker

The new forward migration creates jobs, per-device delivery receipts, and four
database triggers. Chat inserts from either PHP or Node enqueue in the same
statement; active event/announcement inserts and loan status changes do likewise.
This avoids an application crash between domain commit and enqueue across two
independent writers. No provider send or recipient scan runs in a domain request.

Migration preflight requires InnoDB for all four MySQL domain tables. Jobs and
receipts also use InnoDB. Review existing triggers, schema, replication/binlog
policy, TRIGGER privilege and a persistent restricted definer before staging.
DDL can leave partial objects after a failure: inspect them and the migration
ledger; never automatically drop/adopt them or remove a live queue to retry.
Unsafe down intentionally refuses. No historical migration is edited.

MySQL documents [transactional trigger failure rollback](https://dev.mysql.com/doc/refman/8.0/en/trigger-syntax.html)
and [restoration of LAST_INSERT_ID after a trigger](https://dev.mysql.com/doc/refman/8.0/en/stored-routines-last-insert-id.html).
These explain the design; actual MySQL execution and deployed schema inspection
are separate gates. Preserve database-generated domain IDs; never recycle them
while historical job keys exist. No old domain rows are backfilled into the queue.

After reviewed migration/server rollout, run `php spark notifications:work` using
the private deployment environment. One invocation claims one job and exits.
It expands at most 100 currently eligible devices and sends at most five. Current
account, organization, approved membership, permission, role/custom-room and
live session binding are checked again immediately before each provider send.
Only IDs and registration hashes are persisted in receipts; no new token copies
or message-body copies are stored in queue tables.

Use a reviewed systemd service/timer or equivalent to invoke the finite command,
initially with small bounded concurrency. Install a hard process deadline, for
example 90 seconds, and monitor exit codes/backlog. The internal 30-second budget
stops starting further sends; it is not a hard wall-clock deadline for blocked DB
calls. Provider OAuth and send calls have bounded 5-second/3-second connection
timeouts; Node fanout has a 3-second timeout. Leases last 120 seconds and heartbeat
before/after delivery. An expired lease can be reclaimed after a crash.

Device attempts are recorded before I/O and stop at five, with exponential
backoff from 60 seconds to a maximum 3600 seconds. Success, invalid registration,
revocation/rebinding suppression and final failure are terminal with timestamps.
One device failing never resends already confirmed devices. Only typed provider
UNREGISTERED removes a registration; malformed-payload failures do not erase it.
FCM success followed by a process crash before its receipt is saved can deliver a
duplicate on retry. This is finite at-least-once dispatch, not exactly-once delivery.

Chat jobs also retry trusted loopback Node fanout independently, up to five
attempts. The request supplies only chat_id; Node reloads canonical content and
tenant, verifies recipient room/permission/current authorization and ignores
spoofed destinations. `NODE_INTERNAL_URL` defaults to http://127.0.0.1:3000 and
accepts only a loopback HTTP origin. Internal secrets remain external configuration.
Socket-originated immediate fanout may be repeated by the durable job; current
Flutter rendering deduplicates by stored row ID. Older client behavior needs QA.

Alert on oldest pending age, pending/processing/failed counts, expired leases,
terminal failure labels and worker exit errors. Inspect failed jobs before an
operator-directed retry; do not blindly reset attempt counters. Coordinate
terminal receipt retention with backup/recovery policy. Provider/device delivery,
worker installation, sustained throughput and distributed rollout are not proved
by local mocked tests. No production worker was installed or started here.
