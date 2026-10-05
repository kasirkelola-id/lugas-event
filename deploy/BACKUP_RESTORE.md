# Backup and isolated recovery runbook

REL-04 remains **PRODUCTION EVIDENCE REQUIRED**. The repository's synthetic
MySQL drill proves a local mechanism; it does not prove production backups,
off-host durability, keys, restore time, or a business RPO/RTO. No production
backup is executed by this document or the test probe.

## Before an operator schedules a backup

Record the deployed SHA, MySQL version, database/schema and migration history,
engines, document root, upload volume, configuration inventory, required services,
available disk space and named recovery owner. Inspect actual migration history
before using migration commands: historical destructive migrations are guarded;
do not replay them over restored data. Agree maximum tolerable data loss (RPO)
and recovery duration (RTO) with the operator. Backup frequency alone cannot
promise either value. If the RPO requires point-in-time recovery, separately
configure, protect and test binlog capture/replay; the local drill does not.

Use a dedicated least-privileged backup identity with verified TLS, credentials
in a restricted client option file/secret store and no command-line password.
Keep encryption/decryption recovery keys separately protected and test access
with the designated recovery identity. Do not paste .env, service accounts,
keystores, tokens, dumps or unfiltered process environments into logs or Git.

## Consistent recovery set

1. Take an exclusive operator maintenance lock shared with cleanup, migrations
   and deployment. Reserve space for the dump, archive and restore; alert instead
   of filling the live filesystem. Do not delete previous good backups to make
   an unverified job succeed.
2. Establish a recovery point for **both database and uploads**. Prefer an
   operator-reviewed write quiescence or coordinated storage snapshot. Stop
   domain/upload writes, queue delivery, cleanup and migrations as necessary;
   note UTC start/end. A transactional DB dump and a live upload copy by
   themselves are not an atomic recovery set. Verify engines; nontransactional
   tables require another consistency strategy. Avoid schema changes during dump.
3. With a compatible MySQL client, use a checked single-schema dump including
   triggers, routines and events, for example:

   ```text
   mysqldump --defaults-file=<restricted-client-file> --single-transaction --no-tablespaces --set-gtid-purged=OFF --routines --events --triggers --result-file=<new-private-path>/database.sql <reviewed-schema>
   ```

   This is a template, not a command run on production. Review required
   privileges, GTID/replication policy and client version with the operator;
   set-gtid-purged=OFF is not a replication recovery plan. Record a nonzero
   exit as failure. Do not put DROP/CREATE DATABASE or a source USE directive
   into a dump intended for a different isolated target.
4. Copy the validated managed upload tree, preserving relative paths and
   permissions. Reject escaped paths/symlinks and inspect archive paths before
   extraction; no recursive deletion. Keep private application configuration,
   systemd/PM2/cron/Nginx settings and required secrets in an encrypted recovery
   set with access restrictions, separately from public uploads. Record runtime
   versions, dependency locks, signing-key custody and the exact application SHA.
5. Generate SHA-256 hashes for every artifact and a manifest with recovery-set
   ID, UTC recovery point, byte sizes, SHA and format versions. Hashes detect
   corruption, not attacker replacement: protect/authenticate the manifest
   through the trusted backup store or signing policy. Encrypt before transfer.
6. Upload to a verified independent off-host destination using a restricted
   identity; verify remote object sizes/checksums and retrieval with a recovery
   identity. A second folder on the origin is **not off-host**. Alert on failure,
   record last completed off-host set and restore-test date, then resume writes.

Use finite operator-approved retention, initially discussed as daily/weekly/monthly
generations rather than a promise. Keep at least one verified independent good
set during rotation; use storage lifecycle rules, access controls and protected
versions where appropriate. Match retention to privacy/legal needs and available
storage. Deleting backups is a separately reviewed operation, not an app command.

## Isolated restore drill

1. Retrieve a chosen off-host set onto a newly provisioned isolated target;
   authenticate/decrypt it and verify every checksum **before** import. A
   deliberately corrupted copy must fail validation. Never overwrite a live
   schema/uploads or reuse an application database for a drill.
2. Disable outbound Firebase/provider traffic and application jobs, bind only
   the intended private interface, and install independent test credentials.
   Enforce an egress deny rule as well as application mocks. Restored bearer
   hashes/device registrations must not make the isolated host reachable by
   real clients. Do not resolve recovered production endpoints during testing.
3. Create a new empty target schema with the reviewed charset/collation. Inspect
   the dump for source database selection, privileges and unsafe external
   DEFINER references. Do not strip triggers silently; map legitimate definers
   to an approved isolated identity. Import with a checked exit status.
4. Restore uploads/config with canonical path boundaries and restrictive
   permissions. Review every recovered endpoint/secret before starting software;
   production configurations are not safe defaults for a drill. Use the
   recovered SHA/locks and the restored migration ledger; validate pending
   forward changes on this isolated copy, following MIGRATION_SAFETY.md.
5. Compare row counts and representative or full reconciliation hashes, tenant
   memberships, FK/index/engine/nullability definitions, migration history and
   trigger definitions. Test FK rejection, atomic outbox creation and existing
   upload references. Run isolated login/tenant/inventory/chat/attendance/cash
   checks with authorized synthetic identities; provider delivery stays disabled.
6. Measure retrieval, decrypt, import, upload/config restoration, validation and
   total time to a usable service. Record actual data volume, failures, recovery
   point, residual data loss and operator acceptance; compare with agreed RPO/RTO.
   Local tiny-fixture timings are not production RTO. Production failover/cutover
   is a separate authorized procedure with rollback and client/session policy.

## Local repository drill

`website/tests/_support/mysql_restore_probe.php` requires testing plus the same
strict local MySQL8 opt-in as tests/MySQL and an explicit
`KARTAR_MYSQL_TEST_BIN` containing MySQL8 Windows clients. Host is fixed127.0.0.1,
port/user explicit; two random schemas are freshly created and never adopted.
It invokes the complete current migration chain, seeds only synthetic rows with
empty/non-authenticatable password hashes, then dumps and restores into the
second newly empty schema. No production database/config/upload is read.

The probe verifies all table rows, migration rows, semantic DDL, exact column
charset/collation metadata, index definitions and triggers, plus restored outbox
execution and FK rejection. Comparison ignores only volatile index Cardinality
and redundant explicit CHARACTER SET utf8mb4 before an identical collation;
independent column metadata still matches. It copies three hashed synthetic
artifacts through a second **local** folder, detects corruption, and restores
one generated PNG and a harmless testing config template. Both schemas drop in
finally; only its exact new private client option file is removed. TEMP evidence
is retained, never committed. The fixture is quiescent; no claim of concurrent
production DB/upload snapshot consistency or off-host recovery is made.
