# Announcement author forward repair

Apply only the reviewed pending forward migration on an isolated verified copy
before deploying matching application code. Follow MIGRATION_SAFETY.md and
BACKUP_RESTORE.md; do not replay historical migrations over existing data.
This change is locally verified, not deployed or proof of production DDL.

The web author is an authenticated `superadmins` identity, not a global `users`
identity. New migration2026-10-06-000007 makes legacy `dibuat_oleh` nullable and
adds nullable unsigned `dibuat_oleh_superadmin` with a real superadmins FK
(update cascade/delete restrict). Existing global-user authors and their FK/
rows/indexes remain. No user is fabricated or chosen to impersonate a browser
administrator; no historical migration, data rewrite, TRUNCATE or production
DELETE is introduced. Migration down refuses an unsafe lossy conversion.

Web creation supplies the route tenant and current validated session author,
ignoring body author/tenant fields, validates the existing title/body bounds,
checks insert success and uses a generic failure response. The existing outbox
trigger remains atomic with the announcement insert; failed enqueue leaves no
announcement and cannot report success. Normal API creation keeps its existing
global-user author and permission/tenant policy. API lists use the appropriate
user/superadmin display name; legacy numeric dibuat_oleh response remains0 for a
browser-authored row, while the new superadmin column records the actual author.

MySQL8 uses one ALTER with existing rows preserved. SQLite parity uses an owned
checked transaction, original table definition/checks/FKs, captured explicit
indexes/triggers and the allocator high-water mark. Copying rows happens before
restoring insert triggers, so old announcements are not enqueued again. Any
incoming FK or unexpected definition requires review; an existing rebuild target
is refused, never adopted. Failure rolls back the owned scope. A stale handled
historical SQLite connection status outside a transaction is reset before the
new owned transaction; every new write and final status are checked. The
historical test migration compatibility runner is unchanged.

Inspect both author columns, FK actions, row reconciliation, indexes, all four
outbox triggers and a controlled insert/rollback on staging. Unknown operator
schema constraints/prefixes require review; deployment/lock duration remains
unmeasured. Release application and forward DDL together through the reviewed
rollout plan; earlier application code does not know the browser author column.

The old `/superadmin/karang_taruna/<id>/users` read route now redirects to the
existing paginated `/superadmin/manage/<id>/users`, whose authority is
organization_members. It no longer uses a missing view or legacy global tenant
column. Existing authentication, CSRF and management mutation POST rules remain.

Attendance display now follows the already tested check-in rule: a future event
is not_open, manually inactive/closed is closed_manually, and a past/today event
that remains active is open. Existing AbsensiTest explicitly permits past active
events. No new hour-window enforcement, check-in permission or checkout policy
is invented. Dashboard ongoing/upcoming ranking still uses its existing time
settings; that ranking is distinct from whether attendance remains enabled.
