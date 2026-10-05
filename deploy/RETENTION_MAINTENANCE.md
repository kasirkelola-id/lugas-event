# Bounded maintenance and safe upload reconciliation

These are source/runbook changes, not evidence that a production scheduler is
installed. First inspect the deployed document root, database migration/index
state, disk permissions and business retention/backup policy on staging. Never
use an application database for the disposable test harness.

`php spark maintenance:report` is dry-run session cleanup plus read-only upload
candidates. Review counts and the upload boundary before considering
`php spark maintenance:report --apply-sessions`. Apply is explicit; there is no
upload deletion option. Nothing prints bearer hashes or FCM registrations.

Session policy:

* Tokens expired or revoked more than 30 days ago may be removed, at most 500
  selected candidates (default 100) in a pass; active and recently expired tokens
  remain. Expiry/revocation eligibility is rechecked at the DELETE.
* Only devices with a non-null bearer binding, known update time older than 30
  days and no current matching unrevoked/unexpired user token are candidates.
  Binding/update fields and eligibility are rechecked when deleting. Current
  registration, recent rows, legacy unbound devices and unknown dates remain.
  This is global device housekeeping, not tenant-exclusive device ownership.
* A new registration replacing a captured binding must survive cleanup. Deleting
  already invalid bindings does not revoke an active session or contact FCM.
  Keep any separately required audit/archive record under an agreed privacy policy.

`php spark chat:cleanup` retains the existing 30-day UTC policy. It performs at
most ten independently committed batches of at most 1,000 rows; rechecks expiry
at DELETE; checks write failure; and reports busy/bounded/completed state and
counts/duration. It never deletes rooms or membership. The next pass resumes
normally. Pending outbox references to expired/deleted chat are suppressed by
the worker; outbox/receipt archival is a separate policy, not blindly deleted.

Both services use nonblocking file locks scoped by driver/database under
`writable/cache`, releasing on success/error. **This excludes local overlapping
owners, not multiple application hosts.** Runs use a 30-second loop budget;
MySQL SELECT execution and InnoDB lock waits are set to 5 seconds for the owned
connection and restored in finally. These are not a hard deadline for every
possible DML/storage stall. An operator-installed scheduler must additionally
enforce a 45-second process timeout, prohibit overlapping backups/maintenance,
record exit/counts/elapsed time and alert on repeated failure, busy or backlog.
Use a dedicated scheduler identity and allowlist commands. Verify actual cron
history and restart/recovery in staging before asserting production scheduling.

Upload report:

* Requires a real non-root FCPATH and canonical upload directories inside it.
* Visits at most 1,000 directory entries (default 100), no recursion/symlinks.
* Only generated 32-hex `.png` filenames older than 24 hours are considered.
  Recent/in-flight and unknown legacy formats are excluded, not deleted.
* Batch-checks both global users and organizations for any database reference.
  Reports currently unreferenced relative paths with deleted=0. This is a
  bounded candidate report, not a complete census or proof that deletion is safe.

For any future removal, take a verified backup, recheck all references/leases
immediately before action, confirm canonical path and grace period, reconcile
with concurrent upload writes, and retain an auditable reviewed manifest. A
snapshot may become stale; do not pipe candidates into recursive deletion or
delete uploads by age alone. Production uploads were never scanned in this run.

Log/disk policy needs operator approval: rotate application/Node/worker/access
logs daily or by size, compress closed files, restrict access and set finite
retention (for example 14 daily files initially, adjusted to agreed incident and
privacy needs). Exclude secrets/bodies; monitor free bytes/inodes, log growth,
oldest due notification, terminal failures, expired-chat backlog, cleanup count
and backup duration. Never run broad deletion of writable/uploads/tools or
truncate live logs. Database outbox/receipt retention and off-host backup proof
remain pending, so PERF-10/REL-12 are not wholly closed.

Backend tests now use `website/phpunit.xml.dist` and the guarded
`tests/_support/testing_bootstrap.php`. Prefer explicit
`php vendor/bin/phpunit --configuration phpunit.xml.dist` when an older ignored
local `phpunit.xml` exists. Each process creates a new TEMP sandbox/public root;
even two-level traversal fixtures remain inside its owned root. TEMP directories
are retained; individual tests clean only their exact fixture files. No test
adopts a drive-root upload directory when the application's public/ is absent.
