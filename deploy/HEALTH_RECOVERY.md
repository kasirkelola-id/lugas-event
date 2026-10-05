# Single-instance health and recovery

REL-09 remains **PARTIAL / STAGING AND PRODUCTION EVIDENCE REQUIRED**. These
surfaces report local process/database observations. They do not provide
failover, prove alarms/service supervision, or remove single-host/disk failures.

Node binds127.0.0.1. `GET /internal/health` requires X-Internal-Secret and returns
403 before SQL for missing/wrong values; no-store responses must remain private.
The operator's proxy must deny public `/internal/*` (see SERVER_SECURITY.md).
Do not put the secret in a URL, command-line argument, process dump or access log.
Use an allowlisted local agent with a restricted header/secret file. The health
route has a separate30/minute per transport-IP cap; sample at most every5 seconds.

Valid calls return200/ready or503/degraded, DB connectivity, last DB-check UTC,
uptime, RSS, authenticated/transport socket counts, numeric pool counts and
event-loop delay p50/p95/p99/max in milliseconds. The delay histogram has20ms
resolution and resets after each sample; it measures event-loop scheduling
delay, not HTTP latency. Before samples exist it is null. A DB SELECT1 has a
2-second HTTP wait, one shared underlying in-flight operation and5-second
completed-result cache. A timed-out driver operation may still exist; repeated
health checks do not enqueue another one. This is not proof of all app workflows
or immediate detection within5 seconds.

Pool counts use a read-only adapter for installed mysql2 PromisePool internals,
inspected against the installed source: total, free/in-use and queued requests.
If that layout changes, gauges become null, not invented zeros; verify the
adapter after dependency upgrades. In-use includes a connecting/acquired
connection, not a count of executing SQL statements. Queue cap100 and configured
connection cap remain unchanged. Secret, DB host/user/config, user identities,
SQL/error bodies and messages are excluded from the JSON.

`php spark health:report` is a **CLI-only read-only** DB/notification aggregate:
status counts, due/oldest due, expired processing leases and last completed job
timestamp. It selects no body/token/device registration and sends no provider
traffic. It is not a tenant-user/public HTTP route. Completion is not proof that
a scheduler works: cleanup/backup last-success remain null until independently
verified scheduler/backup exit records exist. Aggregates can cost a scan; run on
an operator-approved interval (initially minutes), observe cost/backlog and stop
if the report competes with business work. Do not poll it once per API request.

## Operator alarms and recovery evidence

Track external HTTPS availability and critical synthetic workflow latency,
PHP-FPM queue/errors, host CPU/RAM/disk/inodes/headroom, MySQL connections/locks,
Node RSS/event-loop lag/socket/pool queue, pending oldest-age/failed jobs,
worker/cleanup/backup exit/count/duration and last successful off-host recovery
set. Compare with a measured staging baseline and agreed SLO. Alert on missing
samples, sustained degradation, crash loops, queue saturation and expired
leases; a200 health response cannot replace end-to-end checks.

1. Preserve safe correlation/error labels and incident UTC; identify host,
   process, DB, disk or backlog failure. Do not expose environments or raw bodies.
2. Confirm supervisor's process identity, working directory, protected config,
   loopback binding, bounded restart/backoff and application SHA. Verify actual
   systemd/PM2 startup persistence and log rotation; avoid two accidental worker
   schedulers. Keep recovery keys outside the failed host (BACKUP_RESTORE.md).
3. On staging only, restart the owned Node process through its supervisor and
   observe degraded/recovered health, client reconnect with renewed auth/tenant
   rooms and idempotent chat retries. Local widget/unit tests are not that drill.
   Do not run broad process kills or disable authorization/rate limits to recover.
4. Verify DB connectivity/migration history and existing data before resuming
   writers. Notification expired leases are recoverable by the bounded worker;
   inspect attempts/backoff and eligibility, never erase attempts or replay
   completed jobs just to empty a dashboard. Provider crash gaps remain
   at-least-once external delivery; do not promise exactly-once pushes.
5. For disk/host loss, restore an authenticated verified off-host recovery set
   to an isolated replacement, validate rows/FKs/outbox/uploads and secrets,
   then follow a separately authorized cutover plan. Capture actual RPO/RTO,
   rollback, alarms and operator acceptance before asserting production recovery.

No production service was inspected or restarted during this implementation.
Deployment/supervision, alert delivery, real reconnect/device behavior and
off-host recovery remain operator verification gates.
