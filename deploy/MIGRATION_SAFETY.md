# Migration safety before rollout

Production DDL and migration history have not been inspected. Do not run a
blanket fresh/reset/refresh/rollback migration against an existing database.
Never run diagnostic tools or seeders against production.

`Services::migrations()` now selects SafeMigrationRunner outside testing. It
refuses historical AddTenantIdToAllTables up/down before loading/executing its
destructive implementation. Historical files remain unchanged. This protects
normal framework migration commands; directly constructing a historical class,
using the framework runner explicitly, or issuing SQL bypasses that source guard
and is prohibited in the operational procedure. Testing continues to use the
existing runner only in disposable guarded schemas.

Before enabling any new application revision:

1. Obtain authorized DB/uploads/config backups and verify checksums/off-host
   recovery. Restore to an isolated target with outbound notification disabled.
2. Inspect migration history, columns, keys, FKs and engines on that restore.
   Record the actual previously applied version; do not fabricate applied rows.
3. If the destructive historical tenant migration is pending on an existing
   database, stop. Prepare an explicit non-destructive backfill/baseline plan
   from its actual schema and tenant ownership. No universal assignment of
   existing users/events/cash to a guessed tenant is safe.
4. Apply only reviewed pending forward migrations on the isolated restore and
   compare row counts/content, identities, tenant boundaries, FK/index semantics
   and application writes. The new nullable binding columns suppress legacy
   unbound bearer/device sessions until login/re-registration.
5. Resolve migration-history differences before an authorized staging rollout.
   Fresh non-testing initialization is also refused at the destructive historical
   step; it needs a reviewed non-destructive baseline rather than an override.

REL-02 is partial: replay is blocked through the application runner, but an
existing-database upgrade/backfill cannot be proven without actual schema/history.
Forward migrations that refuse lossy down() are intentional; recover through a
validated forward repair or isolated restore, not blind rollback.
