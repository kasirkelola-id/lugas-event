# KARTAR Production Readiness Audit

Audit date: **2026-10-05, Asia/Bangkok**. Repository: `D:\project\lugas`. Audited HEAD: `7f5986c` (`chore: version bump to 1.0.2+3 and track tools`).

**Latest remediation update — Batch 5, 2026-10-05:** **SEC-05 and SEC-06: RESOLVED IN WORKTREE — NOT DEPLOYED.** Session CSRF protects browser forms; eight GET mutations became POST. Creation/reset uses independent random credentials with enforced password change and immediate response-only delivery. Known historical defaults/username passwords cannot mint production sessions; demo seeding is testing-only. Open counts: **0 CRITICAL, 3 HIGH, 8 MEDIUM, 2 LOW**. SEC-12 remains open. Full PHPUnit **306 tests /1,766 assertions /0 failures /0 errors /7 skips**, Jest **51 PASS**, Flutter **64 PASS**, analysis **0 errors /16 warnings /152 infos**. Production remains **NOT READY**. HEAD is the existing local checkpoint `ceb64cb`; Batch5 is uncommitted/unstaged. No push or deployment. The appended ledger discloses the existing announcement-create/legacy-view limitations and required private privileged provisioning before rollout.

**Historical remediation update — Batch 4.1, 2026-10-05:** Real disposable **MySQL 8.0.46**, bound to **127.0.0.1:3308**, passed all six MySQL cases (**99 assertions, no failures/errors/skips**) plus two existing application cases. **SEC-08: RESOLVED IN WORKTREE — NOT DEPLOYED. REL-01: RESOLVED LOCALLY — PRODUCTION SCHEMA STILL UNVERIFIED. REL-03: RESOLVED IN WORKTREE — NOT DEPLOYED.** Open security counts are **0 CRITICAL, 5 HIGH, 8 MEDIUM, 2 LOW**; only SEC-08 was removed from the open security registry. Backend/Jest/Flutter regressions pass; Flutter analysis has zero errors and the unchanged 16 warnings/158 infos. Production remains **NOT READY**. MySQL proof permits next-batch review, but Batch 5 was not started. HEAD remains `ad4ff37`; no commit, push or deployment. Earlier ledgers remain historical snapshots; the appended Batch 4.1 ledger supersedes their unproven local MySQL status.

**Historical remediation update — Batch 3, 2026-10-05:** SEC-04 (**LEGACY SERVICE RETIRED**) and SEC-07 (**Engine.IO 6.6.9 → 6.6.10**) are **RESOLVED IN WORKTREE — NOT DEPLOYED**. Current open worktree registry: **0 CRITICAL, 6 HIGH, 8 MEDIUM, 2 LOW**. SEC-12 remains open; zero critical worktree findings does not establish production readiness. Runtime npm audit:0 vulnerabilities; dev-only brace-expansion remains1 high package family. Production is **NOT READY**; ready for next remediation batch review: **YES**. Original and earlier remediation evidence are preserved; the latest ledger is appended in section29. Production runtime/deployed dependencies remain unverified.

**Historical remediation update — Batch 2, 2026-10-05:** SEC-02 is **RESOLVED IN WORKTREE — NOT DEPLOYED**. Current open worktree security registry: **1 CRITICAL, 7 HIGH, 8 MEDIUM, 2 LOW**. The remaining critical is SEC-04. SEC-07 and SEC-12 remain open. Production is **NOT READY**; ready for Batch 3 review: **YES**. Historical audit and Batch 0/1 evidence below are preserved; the latest Batch 2 ledger is appended at the end of section 29. No deployed fix is asserted.

**Historical remediation update — Batch 0 + Batch 1, 2026-10-05:** SEC-01 and SEC-03 are **RESOLVED IN WORKTREE — NOT DEPLOYED**. The directly related SEC-13 AuthFilter organization-active/legacy-fallback defect and REL-11 test-network defect are also **RESOLVED IN WORKTREE — NOT DEPLOYED**. Open worktree security findings: **2 CRITICAL, 7 HIGH, 8 MEDIUM, 2 LOW**; the original/deployed-unknown baseline remains **4/7/9/2**. SEC-02 and SEC-04 remain critical and open. Production is **NOT READY**. Sections 1–28 preserve the original audit snapshot, with resolved registry rows annotated; the remediation evidence and current change record are appended in section 29. The original statements about unchanged application behavior apply to the audit, before this explicitly authorized remediation batch.

Scope: source, migrations, local installed dependencies, existing automated suites, five new isolated audit characterizations, an existing Android APK, historical diagnostic artifacts, and a synthetic in-memory SQLite experiment. **No production shell, staging credentials, production schema dump, infrastructure metrics, or restore evidence was available.** Server claims in the request are identified as reported rather than verified. No intentional production benchmark, deployment, push, commit, or application behavior change occurred.

Evidence labels: **MEASURED** = observed execution/artifact; **SOURCE** = code/migration inspection; **CALCULATED** = arithmetic with explicit assumptions; **ESTIMATED** = planning assumption; **UNKNOWN / NEEDS TEST** = unavailable or unverified. Passing a characterization below means a defect was reproduced, not that the security requirement passed.

## 1. Executive Summary

**Overall: NOT READY for broader production usage.** Capacity at any requested concurrency tier is **NOT PROVEN**. Ten thousand registered identities are a plausible data-volume target for this architecture, but current security, correctness, database portability, and recovery gaps prevent a readiness sign-off.

The baseline is useful but does not establish release readiness:

| Baseline | Passed | Failed / errors | Skipped | Other evidence |
|---|---:|---:|---:|---|
| Full backend PHPUnit | 153 | 0 / 0 | 1 | 154 tests, 771 assertions, 40.054 s, 24 MiB; no reported warnings or risky tests |
| Flutter tests | 39 | 0 | 0 reported | 13 test files; final test output at 23 s; one test unexpectedly used the public API |
| Flutter analyze | — | 0 errors | — | 16 warnings, 158 infos, 174 issues; command exit 1 |
| Full chat-server Jest | 19 | 0 | 0 | 2 suites; 26.248 s; no snapshots |
| Backend/web PHP lint | 196 files | 0 syntax failures | — | All `website/app/**/*.php`; includes web controllers/views |

After audit-only tests were added, the full backend run had **157 passed, 0 failed/errors, 1 skipped; 158 tests, 778 assertions, 28.745 s, 24 MiB**. Node had **20 passed, 0 failed; 3 suites, 2.097 s**. Four PHP characterizations and one real localhost Socket.IO test reproduce current defects. Flutter source was unchanged and its suite was not rerun against the public API.

Highest priorities:

1. Require approved active memberships throughout API/socket access; fix attendance tenant scope and private socket tenant context.
2. Restore inventory transactions and verify concurrent approval/return on MySQL.
3. Patch the exposed Engine.IO dependency and keep the legacy unauthenticated Ratchet service disabled.
4. Enable web CSRF protection, remove GET mutations, and replace predictable/default credential flows; establish production Android signing.
5. Validate the actual MySQL schema, document/test restore, bound lists and notification fan-out, then run representative staging load tests.

Original audit security registry: **4 CRITICAL, 7 HIGH, 9 MEDIUM, 2 LOW**. One critical finding is a latent legacy-service defect whose deployment exposure is unknown; three critical findings have local runtime reproductions. See the remediation ledger for resolved worktree findings; no deployed fix is asserted. Performance registry: **P0 1, P1 5, P2 4, P3 2**. These are distinct registry entries, not counts of advisory CVEs, affected routes, or repeated manifestations.

## 2. Architecture

The repository has three active application directories and a legacy realtime implementation:

```text
Flutter Android (mobile/lib/main.dart; StatefulWidget + setState; singleton services)
  | HTTPS /api; opaque Bearer token + X-Karang-Taruna-ID
  v
Cloudflare [reported; one incidental Cloudflare response observed]
  v
Nginx / PHP-FPM [reported; effective configuration unavailable]
  v
website/index.php -> CodeIgniter 4 routes -> AuthFilter -> controllers/models
  | MySQLi; shared CI database connections
  v
MySQL [production version 8 reported, not measured]

Flutter ChatService <--> Socket.IO /socket.io/ <--> chat-server/server.js
  Node -> PHP /api/internal/socket-auth: Bearer + tenant header + shared secret
  Node -> mysql2 pool: room/member validation + message INSERT
  Node -> PHP /api/internal/chat-notification -> FCM HTTP v1
  PHP WheelController -> Node /internal/wheel-event -> wheel session rooms

PHP REST chat fallback -> MySQL + FCM; no matching Node chat broadcast found
PHP event/announcement/inventory mutations -> synchronous FCM HTTP requests
Uploads -> local website/uploads/... -> public image URLs
CLI spark chat:cleanup -> batched expired chat deletion
Web superadmin -> file session -> SuperadminFilter -> cross-tenant management

Legacy, separate: spark websocket:serve -> PHP Ratchet App\Libraries\ChatServer
```

Entry points: [website/index.php](website/index.php), [website/spark](website/spark), [chat-server/server.js](chat-server/server.js), [mobile/lib/main.dart](mobile/lib/main.dart). The web front controller lives at the **website root**, not a `public/` directory. This affects Nginx secret/source protection.

[Routes.php](website/app/Config/Routes.php) explicitly defines API and superadmin routes. [Routing.php](website/app/Config/Routing.php) disables automatic routing. `AuthFilter`, `SuperadminFilter`, and `RateLimitFilter` are custom filters; `BaseApiController` standardizes success/error JSON. [Rbac.php](website/app/Config/Rbac.php) and `AuthService` provide permission checks. Fifty-five migration files and 25 application tables were discovered, plus framework migration bookkeeping. No dedicated durable notification queue or external cache adapter configuration was found.

Environment dependencies: PHP >=8.2 with MySQLi and image handling; Composer dependencies; writable local sessions/cache/logs/uploads; Node with native fetch and `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `INTERNAL_API_SECRET`, `INTERNAL_API_URL`; optional pool limit/port; `NODE_SOCKET_URL` for PHP wheel broadcasting; Firebase service-account file; Flutter/Dart SDK and Android SDK/JDK. Values and private keys are deliberately omitted. PHP assumes loopback callers for internal APIs, so routing through a non-loopback proxy can break socket auth even with a correct shared secret.

## 3. Current Infrastructure

| Item | Verified evidence | Outstanding evidence |
|---|---|---|
| EC2 instance / single origin | Reported by request | Instance type, vCPU, RAM, burst credits, region, availability, utilization |
| Nginx | Reported; Apache `.htaccess` also exists in source | Running Nginx config, workers, body limits, FastCGI settings, deny rules, upload execution block |
| PHP 8.3 / FPM | Reported | Pool mode/counts, OPcache, effective `memory_limit`, process RSS |
| MySQL 8 | Reported; MySQLi configured in source | Exact version, DDL/indexes, migration state, engine, `max_connections`, buffer pool, slow-query data |
| Node / PM2 `kartar-socket` | Reported | Version, instances, fork/cluster mode, restart/startup config, memory and log retention |
| Cloudflare | Public URL in mobile source; incidental test received Cloudflare 522 | DNS/proxy state, TLS mode, WAF, origin firewall, WebSocket policy |
| Approximately 15 GB disk | Reported possibility | Actual size/free bytes, inode usage, DB/upload/log/backup footprint |
| Production paths | `/var/www/kartar/website`, `/var/www/kartar/chat-server` reported | Deployed SHA, permissions, effective document root |

Local tools observed: PHP **8.2.12**, PHPUnit **10.5.64**, Node **24.13.1**, Android build tools 37.0.0, installed Flutter/Dart. Local tests therefore do not validate PHP 8.3 production parity. No listener at local TCP 3306 was observed. Docker CLI exists, but its Linux engine pipe is absent; no Docker MySQL test environment was available. No server or SSH connection was initiated.

Existing tracked [db_audit_output.txt](website/tools/db_audit_output.txt) records a historical local `lugasku` database with **3 users, 3 memberships, 3 chats, 2 rooms, 2 room memberships, 1 token and 2 devices**. Its SHOW INDEX/EXPLAIN text is artifact evidence, not a query executed during this audit or proof of current production MySQL 8 state. The associated PHP tool targets localhost; engine/version and run provenance are not recorded. No tool under either existing `tools/` directory was executed or edited.

The Flutter fallback test uses an `HttpOverrides` class that counts client construction and delegates to `super.createHttpClient`, so it is not a network stub. Its unauthenticated POST to `/api/chats/messages` returned **522 at 2026-10-05 10:58:53 Bangkok**. This is one incidental failure, not uptime or load evidence. No successful production write was observed, and no further production probes were made. Test network isolation must be fixed before routinely rerunning this suite.

## 4. Application Modules

| Module / controller | Source assessment | Important limitation |
|---|---|---|
| Auth / PIN / tenant selection | Opaque tokens, hashed DB lookup, tenant membership resolution | Approval not checked in middleware; inactive tenant not checked on existing access |
| Memberships / approval | Scoped membership queries and approval history | REST approval not atomic; pending membership discovery includes status-active pending rows |
| Users / roles | Tenant membership authorization and paginated API | Invalid pagination bounds; predictable created password; global identity mutations |
| Profile | Authenticated self update; photo bounds and old-file cleanup | Password change has no old-password requirement or token revocation |
| Events / participants | Scoped events; completed attendance list exclusion | Event N+1; broken participant GET route; participant insert omits tenant column |
| GPS attendance / history | Scoped mutation targets; DB unique event/user | Past active event accepts check-in; status lacks tenant scope |
| Kas | Scoped reads/writes and role gates | Unbounded list; INT amount versus general numeric input; replay duplicates |
| Announcements | Scoped CRUD and list role filtering | Dashboard/FCM do not apply the same target-role filter |
| Voting | Scoped parent/option; DB unique voting/user | Unbounded N+1; date-wide rather than precise timestamp semantics |
| Inventory / loans | Scoped inventory ownership; state transition checks | Transaction removed, making multi-write stock transition unsafe |
| Private / group chat | Recipient and custom group checks; bounded history/contacts | Private user rooms cross tenant context; REST chat misses permission gates |
| Wheel / Undian | Scoped sessions; owner spin/close; production row lock | Unbounded sessions/items/results; reconnect/listener and close/spin races |
| Dashboard / reports | Tenant conditions; compact returned summary | Fetches all candidate events; role-target and permission omissions |
| Tenant settings | Tenant-scoped CRUD; setting bounds | Arbitrary keys, no key-count cap; no cache invalidation for persistent workers |
| Superadmin web | Separate session gate; intended global control | CSRF off; GET mutations; unbounded HTML data; incomplete validation |
| Android updates | Public allowlisted metadata; versionCode comparison | URL policy and stale state on some non-200 responses |
| FCM / devices | Unique global device token and owner rebinding | Synchronous per-token fan-out; stale receiver membership and logout failures |
| Uploads | Profile and tenant-logo image paths discovered | Nginx execution/source protection unverified; logo cleanup precedes successful DB update |
| Geographic administration | `KelurahanController`, tenant/kelurahan relation | Global unpaginated reference lists |
| Legacy / diagnostics | Ratchet service, disabled migration controller, root PHP scripts | Must not be exposed or run in production indiscriminately |

Undocumented/additional surfaces include `kelurahan`, membership approval history, `report/summary`, `check:index`, old `websocket:serve`, and root `test_*.php` / `fix.php`. No additional event/media upload handler was found beyond profile photos and logos in the inspected controllers. This is source discovery, not proof that deployed files match.

## 5. Multi-Tenant Security

`X-Karang-Taruna-ID` is a selector, not proof of authorization. For ordinary users the filter resolves `(user_id, karang_taruna_id)` and checks `status_aktif=1`, then overrides legacy tenant/role context. Most controllers derive writes from `AuthService::getTenantId()`, ignoring arbitrary tenant IDs in mutation bodies. Superadmin is intentionally global and must be evaluated separately.

| Area | Tenant/IDOR classification | Evidence / boundary |
|---|---|---|
| Users and role changes | SAFE for ordinary nonmember target IDs; NEEDS TEST for global-identity trust policy | Membership lookup before mutation; existing tenant isolation tests |
| Events | SAFE at resource lookup boundary | `EventController` uses tenant + ID, including update/close/reopen |
| Participants | NEEDS TEST | Scoped event parent, but GET route method mismatch and insert schema drift |
| Attendance check-in/out/history | SAFE at event lookup boundary | Scoped event/parent join; uniqueness is event/user |
| Attendance status | **VULNERABLE, CRITICAL SEC-01** | `AbsensiController::status()` filters user/date only |
| Kas | SAFE at ordinary resource boundary | Scoped list/summary/create/delete; tested body tenant override |
| Announcements | SAFE tenant boundary; role visibility incomplete | Scoped CRUD; dashboard/FCM targeting differs |
| Voting/options/votes | SAFE through scoped voting parent | Option must belong to selected voting; DB uniqueness |
| Inventory/loans | NEEDS TEST for early-return oracle; mutations scoped afterward | Idempotent same-status return precedes inventory tenant check |
| REST private chat | SAFE recipient tenant boundary; approval/RBAC gaps apply | Active receiver membership in selected tenant; scoped history |
| REST groups | SAFE parent/custom-member boundary | Tenant room check and custom membership; direct model is safe only behind controller validation |
| Socket private chat | **VULNERABLE, CRITICAL SEC-02** | User rooms are global to user ID; reproduced cross-context delivery |
| Socket group joining/sending | SAFE initial tenant/custom membership checks; NEEDS TEST after revocation | Room + active membership validated; receiving socket not periodically revalidated |
| Wheel REST | SAFE tenant parent boundary | Session tenant comparison; creator check for spin/close |
| Wheel socket | NEEDS TEST | Tenant room names, but arbitrary same-tenant session ID joins without DB/session existence check |
| Settings | SAFE tenant query boundary | Scope from auth; global values remain in tenant 0 |
| Upload ownership | NEEDS TEST for deployed static access policy | Global user photo and global-admin tenant logo; public URLs are intentionally public, not authenticated tenant files |
| Membership selection | **VULNERABLE, CRITICAL SEC-03** | Pending/rejected approval is not enforced after token issuance |
| Superadmin management | Global authority, not ordinary tenant isolation | `deletePengumuman(kt,id)` ignores selected tenant; can delete an announcement outside the displayed tenant, but superadmin already has global access |

**SEC-01 reproduction:** authenticate in tenant 101; a historical open attendance row for the same global user in tenant 102 is returned in tenant 101's `active_event_ids`. The seeded historical row models a prior membership or imported attendance; the test does not claim another user's attendance is exposed. It demonstrates organization metadata escaping the selected tenant. Severity follows the requested critical treatment of confirmed cross-tenant access, with this limited impact explicitly stated.

**SEC-02 reproduction:** socket sender authenticated in tenant 101; recipient socket authenticated in tenant 102; database membership validation mocked as active for sender and recipient in 101. The recipient receives `karang_taruna_id=101` on the 102 socket. This establishes tenant-context contamination for the same global identity, not delivery to an arbitrary unrelated user ID. Flutter filters private messages by peer ID without comparing tenant, so a conversation with that peer in both organizations can display the wrong organization message. Namespace private rooms with tenant+user and also reject mismatched tenant payloads on the client.

**SEC-03 reproduction:** token from approved membership A, active pending membership B, header B, GET `/api/inventories` -> 200. Login blocks pending/rejected membership, but `AuthFilter` does not. Both branches of membership discovery and FCM recipient resolution also ignore approval state. Rejected members remain status-active unless separately disabled.

No SQL injection was established in reviewed bound raw SQL. Integer-cast IDs in raw subqueries are materially different from trusting an arbitrary string. No complete endpoint-by-endpoint deployed penetration test occurred. **Tenant isolation verdict: FAIL.**

## 6. Authentication & Authorization

Tokens are random 32-byte values returned as 64 hex characters, stored server-side as SHA-256 hashes with 30-day expiry. Logout revokes the presented token. Auth checks expiry/revocation, global user active status, selected membership active status, and forced-password-change restrictions. It does not continuously validate idle receiving sockets, approved membership, or organization active status. Password change/reset does not revoke other tokens. Tokens for superadmin have nullable user ID; the filter uses a virtual ID 0 rather than reloading a specific platform-admin identity. Session login sets fields without an explicit login-time regeneration call; timer-based framework regeneration is configured, but session-fixation behavior needs testing.

Source permission matrix (`Y` is configuration permission, not assurance every endpoint enforces it):

| Role | Cash read/write/delete | Event manage | Attendance | Announcement manage | Voting manage | Inventory create/approve | Chat manage | Member manage/approve | Settings |
|---|---|---|---|---|---|---|---|---|---|
| Superadmin API | — | — | — | — | — | — | — | read / approve | — |
| Admin | Y/Y/Y | Y | check/manage | Y | Y | Y/Y | Y | manage / **no approve** | Y |
| Ketua | Y/Y/Y | Y | check/manage | Y | Y | Y/Y | Y | manage / approve | Y |
| Sekretaris | Y/—/— | — | check | Y | — | —/— | — | read / approve | — |
| Bendahara | Y/Y/Y | — | check | — | — | —/— | — | **no read** / approve | — |
| Pengelola | Y/—/— | Y | check/manage | — | — | —/— | — | read / — | — |
| Anggota | Y/—/— | — | check | — | — | —/— | — | read / — | — |
| Tamu | **Not a configured role** | — | — | — | — | — | — | — | — |

All six configured tenant roles also have inventory view/borrow/return, voting view/vote, announcement view, and chat read/send. `inventory.return` is configured for ordinary borrowers but the only loan-status endpoint requires `inventory.approve`: borrowers cannot independently return via that API. `report.view` exists for Ketua/Admin/Sekretaris/Bendahara; Pengelola and Anggota lack it. Finance reporting permission only Ketua/Admin/Bendahara. Admin is further prevented from assigning executive roles in `changeRole` despite its general role-change permission.

Database roles additionally include `wakil_ketua`, `wakil_sekretaris`, `wakil_bendahara`, and `seksi`; these have **no Rbac.php permission entries**. Do not invent inherited permissions. Flutter routes deputies to the generic member home. REST chat reads/sends and dashboard do not uniformly require their configuration permissions, so these roles can access surfaces that permission-based endpoints deny. Wheel has creator-based policy, with no explicit wheel permission family; creation by any authenticated active member appears to be current policy and is not automatically an unauthorized action.

Unauthorized/unintended behavior by role:

- Any holder of an approved tenant token with a status-active pending/rejected membership elsewhere can access that unapproved organization (SEC-03).
- All roles allowed to use the dashboard can receive previews of announcements targeted to a different role; dashboard announcement query omits `target_role`. Notifications use all active tenant members regardless of announcement target (SEC-10/20).
- Roles missing chat permissions, including deputies/seksi and virtual superadmin API identity, can reach some REST chat reads because controller permission checks are absent; Node properly checks chat permissions.
- Ketua/Admin can create accounts whose password equals the username without `password_must_change=1` (reproduced). Existing user lists expose usernames to other members; this is a practical credential risk.
- A tenant manager can reset a shared user's **global** password. This affects all organizations for that identity and must be an explicit platform trust decision. Do not mistake the existing identity-semantics tests for confirmation that this trust model is acceptable.
- Web superadmin has intended cross-tenant control, but an attacker can induce GET mutations using that browser session because CSRF is disabled (SEC-05).
- Tamu has no supported authenticated role policy; unauthenticated users can access only the public routes and protected internal routes if their additional defenses are satisfied.

PIN is a six-character organization locator stored as `kode_pin`; it is not a second authentication factor. Login and PIN route rate limits exist. No password-reset challenge route, MFA, token-list/session-management UI, or rotation protocol was discovered. Password minimum is six characters in self-service API. Changing password requires a valid token but not old password. Global `/memberships` omits client tenant header yet middleware can fall back to the token's original tenant; when that membership is revoked, discovery/logout can be blocked despite other active memberships. Legacy fallback allows a user with no active memberships but retained `users.karang_taruna_id` through when no selector exists; this special state needs a negative regression test.

## 7. Database

**Schema evidence comes from migrations, not SHOW CREATE TABLE on production.** Foreign-key indexes can be created implicitly by MySQL; absence of explicit `addKey()` alone is not proof of a production full scan. SQLite tests intentionally suppress some DDL errors, skip FKs, rebuild users, and bypass MySQL-only indexes. They cannot establish MySQL schema correctness or locking behavior.

The historical local tool output corroborates the unique user/membership/token/device keys and implicit FK access paths for seven tables on that old database. Its chats index listing lacks the later created_at/room-history/private-contact performance indexes shown in current migrations. Tiny-table plans include full scans, temporary tables and filesorts for contacts/cleanup; three rows are insufficient to infer large-table optimizer choices. Actual deployed indexes, current migration application, user tenant nullability and representative MySQL plans remain **NEEDS TEST**.

Table inventory (all `id` primary keys below are auto-increment integers unless indicated):

| Table | Tenant ownership / primary key | FK and index evidence | Growth / frequent use |
|---|---|---|---|
| users | Global identity; legacy tenant column; `id` | Legacy tenant FK; old unique `(username,karang_taruna_id)` | 10k users plausible; login/profile joins; 10k x ~0.5–2 KiB rows+indexes is an estimate |
| organization_members | Direct tenant; `id` | User/tenant FKs on MySQL; unique `(user_id,tenant)` and `(tenant,username)` | U x average memberships; auth every request; tenant role/status filters |
| user_tokens | User token + original tenant; `id` | Unique `token_hash`, user FK, legacy tenant FK | Every login adds a row; no expiry pruning found |
| events | Direct tenant; `id` | Creator/tenant FKs; QR unique; `(status_aktif,tanggal_acara)` | Unbounded archive; list, dashboard, attendance |
| absensi | Direct tenant + event/user; `id` | Event/user/tenant FKs; unique `(event_id,user_id)`; `(user_id,event_id)`, `(waktu_absen,waktu_checkout)` MySQL indexes | One lifecycle/member/event; 100k rows (~30–100 MiB planning envelope) |
| event_participants | Direct legacy tenant + event/user; `id` | Unique `(event_id,user_id)` and FKs | Up to members x events; compatibility module |
| kas | Direct tenant; `id` | Creator and tenant FKs; no tenant/date composite migration found | Unbounded transactions; date list, sums; 100k rows (~30–100 MiB estimate) |
| pengumuman | Direct tenant; `id` | Creator/tenant FKs | Unbounded TEXT bodies; list/search/dashboard |
| votings | Direct tenant; `id` | Tenant/creator FKs; explicit tenant key | Sessions and archive; date lifecycle |
| voting_options | Inherited through voting; `id` | Voting FK | Options/voting; count per option |
| voting_votes | Inherited through voting; `id` | Voting/option/user FKs; **unique `(voting_id,user_id)`** | Members x polls; not purged |
| inventories | Direct tenant; `id` | Tenant FK, therefore likely implicit tenant index on MySQL | Small catalog, name sorting, stock |
| inventory_loans | Inherited through inventory; `id` | Inventory/user FKs | Unbounded history; approval/status/user queries |
| chats | Direct tenant; `id` | Tenant/sender/receiver/room FKs; tenant index; sender/receiver; created_at; `(chat_room_id,id)`; `(tenant,type,sender,receiver,id)` | 30-day visible and intended retained window; 100k rows depends mainly on message size |
| chat_rooms | Direct tenant; `id` | Tenant key/FK, creator FK | Unbounded number of rooms; no archive cleanup |
| chat_room_members | Inherited through room; **composite `(chat_room_id,user_id)` PK** | Room/user FKs | Custom rooms x up to 100 members; need user-first access path verification |
| user_devices | Global user device; `id` | Unique FCM token; user index; tenant column explicitly removed | 10k users x 1–3 devices = 10–30k estimate |
| wheel_sessions | Direct tenant; `id` | Tenant key/FK, creator FK | Unbounded sessions/dashboard |
| wheel_items | Inherited through session; `id` | Session/member FKs | Members or custom candidates; currently unbounded request list |
| wheel_results | Inherited through session; `id` | Session/item FKs; session key; no unique sequence migration found | Spins; latest spin + history |
| settings | Direct tenant; 0 is global; `id` after MySQL migration | Unique **`(setting_key,karang_taruna_id)`**; tenant FK removed for sentinel 0 | Small if keys bounded; arbitrary new keys currently allowed |
| membership_approval_history | Direct tenant + parent; `id` | Parent/tenant FKs and keys; actor type + ID | Append per action; no retention policy found |
| karang_taruna | Global organization registry; `id` | Unique PIN; nullable kelurahan FK | Tenants, logos; platform administration |
| kelurahan | Global reference; `id` | No per-tenant scope required | Geographic reference list |
| superadmins | Global privileged identities; `id` | Unique username | Low cardinality; default account migration present |
| migrations | Framework bookkeeping | Framework-managed | Deployment history; not tenant-owned |

Child tables need a scoped parent boundary, not necessarily a redundant tenant column. For voting_votes, options, inventory_loans, chat_room_members, wheel_items/results, parent-scoped joins are valid. If tenant columns are added later, enforce consistency rather than introducing two independent sources of truth. FCM tokens and global users should not automatically be made tenant-exclusive.

**REL-01 / HIGH, P0 correctness prerequisite: MySQL/SQLite identity schema drift.** `AddTenantIdToAllTables` adds a nonnullable users tenant column and FK. Register/create now omit that column from global user INSERTs. The SQLite-only `FixSQLiteUsersUniqueConstraint` rebuilds users with nullable tenant. No corresponding MySQL nullability migration was found. On a strictly migrated MySQL schema, insert fails; in permissive mode an implicit 0 can fail the FK. Actual deployed DDL might contain a manual fix, which must be verified. Participant add also omits the legacy nonnullable tenant field. Settings PK migration is MySQL-only; several tests construct a different SQLite settings table. This is why green SQLite tests do not establish database readiness.

**REL-02 / HIGH:** the historical tenant migration truncates eight tables before schema changes. It must never be replayed over real data. Migration safety/backfill validation on a restored copy is required before deployment; no migration was run by this audit outside test SQLite.

Index recommendations, to validate with MySQL EXPLAIN before creating:

- Tenant member listing/approval: `(karang_taruna_id,status_aktif,role_level,user_id)` and a separate approval-first variant only if measurements justify it. Existing unique user/tenant helps auth; tenant/username helps login. Joined user-name sorting and leading-wildcard search still require work.
- Events: tenant-first status/date/time list paths; CASE ordering and `LOWER(status_aktif)` can still need filesort. Normalize status semantics before choosing indexes.
- Attendance history: `(karang_taruna_id,user_id,waktu_absen,id)` or a verified parent-join plan. Unique event/user already protects duplicates and event counts. Existing user/event reverse order serves different access, not automatically redundant.
- Kas: `(karang_taruna_id,tanggal,created_at,id)` for lists, possibly `(karang_taruna_id,jenis,tanggal)` for aggregate windows; replace date LIKE with explicit date ranges where beneficial.
- Announcements/voting/wheels: tenant-first status/time/created paths; current tenant indexes alone do not establish efficient sorted archives.
- Loans: `(user_id,status,created_at)` and `(inventory_id,status,created_at)` subject to join plans.
- Settings: current unique `(key,tenant)` is ideal for exact pair lookup but not tenant-only preload; consider `(tenant,key)` if measured. It overlaps uniqueness and should not be added blindly.
- Chat: exact directed private history is covered by `(tenant,type,sender,receiver,id)`. Contacts use `(sender=? OR receiver=?)`; a receiver-first branch/index may help. LIMIT after GROUP BY bounds output, not scanned work. `created_at` is a range predicate after ID-order access, so retention cleanup can affect history scan depth. `(created_at)` supports cleanup; `(chat_room_id,id)` supports room keyset paging. Do not drop overlapping sender/receiver or FK indexes without SHOW INDEX and plan evidence.
- Wheel latest spin/history: `(session_id,started_at)` and `(session_id,spin_sequence)`; consider unique sequence after validating old data.

No representative MySQL EXPLAIN/EXPLAIN ANALYZE was run. Proposed indexes are candidates, not proven performance gains. **Database readiness: FAIL for sign-off until schema parity and inventory atomicity are resolved.**

## 8. API

Pagination inventory:

| List / function | Classification | Current bound / issue |
|---|---|---|
| `UserController::index` | PAGINATED, bounds incomplete | Default/max 100, offset/page; no minimum page/limit; limit 0 can disable LIMIT and divide by zero |
| `EventController::index` | UNBOUNDED | Full scoped archive / attendance candidates, N+1 count |
| Participants / event attendees | UNBOUNDED | Parent validation exists; participant route defect below |
| `AbsensiController::myHistory` | UNBOUNDED | All user/tenant history |
| `KasController::index` | UNBOUNDED | Optional month; missing/invalid month leaves all transactions |
| `AnnouncementController::index` | UNBOUNDED | Full bodies; search does not bound output |
| Private chat contacts | PAGINATED | Default 50/max 100, offset; database aggregation remains unbounded by page size |
| Private/group history | PAGINATED | Default 50/max 100, `before_id`; positive validation |
| Chat rooms | UNBOUNDED | Group size <=100 does not cap number of groups |
| Voting list/options | UNBOUNDED | Full archive; options per create have no maximum |
| Inventory / loans | UNBOUNDED | Loans grow over time; borrowers scoped to own loans |
| Wheel sessions/items/results | UNBOUNDED | Full sessions, full history/candidates |
| Membership pending/discovery | UNBOUNDED | Discovery per user likely small; pending per tenant grows |
| Superadmin tenants/users/events/announcements/cash/reference lists | UNBOUNDED | Server renders all rows/modals; any client table paging does not reduce transfer/DB work |
| Log viewing | No application log-list endpoint found | Files grow without evidenced retention |
| Dashboard / report summary | BOUNDED response, UNBOUNDED internal reads | Fetches all future events or all scoped events to compute summary |
| Settings | UNBOUNDED small-reference assumption | Arbitrary keys invalidate a fixed-size assumption |

Conceptual payload envelope (**CALCULATED**, assume 500 bytes/record before JSON/body variation): 100 rows ~49 KiB; 1,000 ~488 KiB; 10,000 ~4.77 MiB; 100,000 ~47.7 MiB. At 1 Mbit/s, 10k rows take ~40 s for transfer alone. A 100-row cap bounds transfer to ~49 KiB; it does not by itself fix an expensive COUNT/GROUP BY. Flutter local filtering/render paging is insufficient for unbounded APIs. Event responses also retain old QR fields; chat includes full message content intentionally; no base64 image/chat upload route was found. Prefer summaries/previews on lists and detail routes for large text.

Source-derived query amplification excludes ~3 auth queries (token, global user, membership), notification queries, and driver overhead:

| Function | Amplification | Severity / smallest fix |
|---|---|---|
| `EventController::index`, attendance count at line 114 | `1 + N` event/count queries: 100 ->101; 10k ->10,001 | HIGH / P1: grouped counts + page |
| `VotingController::index` | `1 + N + ended_N`; 100 ended polls ->201 | HIGH / P1: batched user-vote and grouped totals |
| `VotingController::show` ended results | Initial voting/options/user-vote/total + one count per option | MEDIUM: one grouped option count |
| `WheelController::index` | `1 + N` item counts | MEDIUM / P1: grouped item counts + page |
| `ParticipantController::add` | Per supplied user: membership + existence + optional insert, up to ~3N | HIGH at unbounded request size: cap and batched validation/insert |
| `ChatController::createRoom` | Insert room + up to two DB actions/member | MEDIUM: capped at 100, ~201 statements maximum; batch after validation |
| Tenant settings update | Lookup + write per arbitrary key, ~2N | MEDIUM: allowlist/cap keys, use verified upsert |
| FCM send | OAuth acquisition + one network request/device | HIGH / P0: durable queued bounded fan-out |

High-frequency paths: dashboard, event/attendance lists, auth/me, users, chat contacts/history, cash summary, socket authentication, chat notifications. Dashboard performs a fixed series of count/latest/aggregate queries (~8–16 depending on populated modules) but reads all future event candidates; no constant response size guarantee for that work. Reports fetch every event then build a potentially large IN list. Kas summaries scan scoped amounts repeatedly. No response-time, DB-count, payload, or memory measurements for a real CI4+MySQL HTTP deployment were available.

**REL-03 / MEDIUM:** route GET `events/{id}/participants` points to `ParticipantController::getByEvent`, but the controller defines `index` and no `getByEvent`. Source also selects `users.whatsapp`, while schema/model use `no_whatsapp`. This API cannot be considered working based on current code. Fix the route/function and selected field, preserve tenant parent validation, then add route-level tests.

## 9. Realtime / Socket.IO

Node is a single `http.createServer` process in source, with in-memory Socket.IO adapter, online-user Map and per-socket rate Map. Actual PM2 topology is unknown. Each authenticated socket joins `tenant_{tenant}` and **`user_{global_user_id}`**. Custom chat room joins validate room tenant, current active organization membership, custom-room membership, and chat.read. Group sends repeat membership checks and derive sender/tenant from socket metadata; private sends validate active same-tenant receiver. Client `sender_id` is ignored. SQL values are bound.

Inventory of events: `auth`, `auth_success`, `auth_error`, `join_room`, `room_joined`, `send_message`, `new_message`, `error`, `disconnect`, `join_wheel`, `wheel_joined`, `wheel_spin_started`, `wheel_closed`. Internal Node `/internal/wheel-event` uses a shared-secret header and tenant/session room names. Express CORS and Socket.IO CORS allow `*`; CORS is not authentication and is not sufficient to restrict native/raw WebSocket clients.

Limits: transport payload 1 MB; message length <=2,000 Unicode characters; unauthenticated disconnect after 5 s; 5 messages/second/socket. There is no user/IP aggregate quota, room-count cap, auth-event in-flight guard, join-event rate limit, or practical queue cap. Multiple sockets multiply permitted sends. Wheel joining accepts an arbitrary session identifier without DB existence/permission verification. Malformed `join_wheel` payload can throw before validation; authentication can be initiated several times before the first fetch completes.

Pool: default **10**, configured integer clamped **1–100**; `waitForConnections=true`, **queueLimit=0** (unlimited queue). Ten thousand sockets do not consume ten thousand DB connections: sockets share the pool. Burst operations can nevertheless accumulate queued work/memory. Auth and revalidation fetch have **no AbortController deadline**; initial socket disconnect does not cancel a hanging PHP fetch. Notification fetch alone has a 3 s deadline.

Private send: two membership queries + INSERT + timestamp SELECT, about **4 Node SQL statements**. Default group send: room + active member + INSERT + timestamp, ~4. Custom group adds another membership query, ~5. A first operation after 60 s also hits PHP auth (~3 SQL queries). Broadcast cost is O(recipient sockets), not O(database users); default groups can have much larger fan-out than custom 100-member groups. Notification fan-out is separate and potentially dominant.

Revalidation happens on **send**, after a 60 s cached auth interval. Idle clients stay in rooms and can receive group/tenant/wheel events after membership/user/token revocation. Joining checks active organization membership but does not refresh revoked tokens or role permission snapshots. Implement periodic receiving-side revalidation or server-driven eviction; bound its cost and prevent reconnect storms.

No graceful shutdown/drain handler or metrics endpoint was found. Abrupt PM2 restart loses room subscriptions; one process is a single point of failure. Multiple PM2 instances need compatible routing for polling and a shared adapter for cross-instance room broadcasts. Do not turn on cluster mode alone and assume rooms remain coherent.

**Socket readiness: FAIL** because of tenant-context delivery, the vulnerable production dependency, and uncontrolled receiving-session revocation.

## 10. Chat

Recent hardening has real benefits: bounded, validated contacts/history parameters; same-tenant recipient validation; custom group membership checks; 2,000-character messages; max 100 custom-room members; mutually exclusive Node/REST write paths; canonical UTC timestamps; pool/environment bounds; SQL performance indexes. The audits/tests do not validate idle-session revocation, multi-tenant user-room semantics, real MySQL plans, or persistence during packet loss.

Retention: `ChatModel::RETENTION_DAYS=30`; private, group and contact read queries apply a UTC cutoff. `ChatCleanupService` selects <=1,000 expired IDs ordered by created_at/id and deletes each small batch; no outer all-history transaction. Only chats are deleted; rooms/members are retained. `ChatCleanupCommand` reports counts/duration and controlled errors. `idx_chats_created_at` is MySQL-only migration evidence supporting the selector. **No cron installation or schedule documentation was found in the inspected project documents.** Logical read retention is present; physical production deletion is unverified. A looping cleanup with continual delete failure/zero progress is not explicitly bounded; overlap prevention and a run-duration limit should be considered. InnoDB file size is not guaranteed to shrink after DELETE.

Delivery semantics: **best effort; default Socket.IO transport delivery is at-most-once, not exactly-once.** No `client_message_id`, DB idempotency key, acknowledgement callback, durable resend queue, or catch-up offset protocol was found. The Flutter deduplicator uses the server row ID, which suppresses repeated rendering of one row but cannot prevent two different rows from a repeated logical send. A successful insert followed by SELECT/broadcast failure yields a stored message without delivered confirmation; a user retry can create another row. Socket path returns null and waits for broadcast, with no confirmed-delivery timeout. Offline history is recoverable via REST while inside retention, but reconnect does not implement general replay. These conclusions agree with [Socket.IO delivery documentation](https://socket.io/docs/v4/delivery-guarantees/).

Introduce a UUID client message ID scoped by tenant/sender with a unique database constraint and an acknowledged stored result before adding automatic retries. Return a prior stored result on duplicate logical send; test retry after INSERT succeeded but response/broadcast was lost. This establishes idempotent persistence, not magical exactly-once delivery to devices.

REST fallback sends FCM **before** inserting chat, does not check chat.send/read consistently, and does not broadcast into Node rooms. Recipients may receive a notification for a failed insert, while online clients may need a reload to see a REST message. Use persistence-first ordering and one reliable fan-out mechanism. Notification deduplication/durable retries do not currently exist.

Flutter singleton ChatService explicitly closes/disposes its socket on logout and tenant selector changes; chat screen subscriptions/controllers are disposed. It clears known event listeners on new socket setup. However it creates a new socket if the old socket exists but is disconnected, without first disposing the reconnecting object; repeated initialization can leave duplicate sockets/listeners. It retains joined rooms until disconnect and does not emit leave_room when a screen is closed. Only the latest `_activeRoomId` rejoins after auth. Notification-triggered tenant switching saves tenant and restarts InitialScreen without closing the existing socket; if that socket is connected, init returns early using the previous organization. Peer-only filtering compounds SEC-02.

**Chat readiness: FAIL.** Retention logic and pagination pass local functional checks, but delivery correctness and tenant context need repair before broader use.

## 11. Attendance

Lifecycle: scoped event lookup -> global active user -> role permission -> event active status -> date/GPS check -> existing attendance lookup -> INSERT with unique `(event_id,user_id)` -> checkout updates that same row. The database unique constraint protects simultaneous check-ins if deployed. Completed attendance cannot be inserted again for the same user/event. Checkout currently uses read-before-update without `WHERE waktu_checkout IS NULL`, so simultaneous checkouts can both report success and overwrite time; the unique lifecycle is retained but exact transition semantics are not guaranteed.

Recent commit `21e16b6`: `attendance_only=1` event list uses NOT EXISTS for the current user's nonnull checkout. Existing `EventAttendanceFilterTest` exercises completed/ongoing/other-member behavior. This is a useful tested list fix. It does not add the same exclusion to every dashboard candidate query, and it does not fix event expiry enforcement at mutation time.

`getAttendanceState` marks a past event closed by time, and dashboard consults before/after minute settings. **Check-in only rejects dates before the event; it accepts a seven-day-old active event**, reproduced in the audit test. It does not enforce `waktu_mulai`, `waktu_selesai`, or configured before/after minutes. Checkout does not enforce the event's current active/date window. Clarify intended late checkout semantics and apply one authoritative time rule at the API. Attendance is intentionally open to all active members; current report comments explicitly stopped treating event_participants as the attendance denominator. Participant enrollment is not a security prerequisite in the present policy.

GPS uses Haversine in meters. Server geofence radius comes from tenant `default_geofence_radius`, not `event.radius`; source/client event radius fields therefore need consistency tests. Coordinate numeric bounds and accuracy thresholds are not validated robustly; `accuracy` is stored, not enforced. Event coordinates of zero can become null in response mapping due to truthiness. Client coordinates are forgeable by a modified app, mock-location provider, or direct API call. GPS provides proximity validation on supplied data, **not an anti-fraud guarantee**. Device attestation/spoof heuristics would only add signals, not eliminate that limitation.

Attendance tests are PARTIAL: serial duplicate protection and geofence paths exist; MySQL concurrent check-in/out, precise time boundaries, invalid coordinate ranges, and authoritative radius agreement need coverage. Status tenant leak is SEC-01; fix both the query and regression expectations.

## 12. Voting

Parent tenant scope, `voting.view/manage/vote` checks and option/voting association checks are present. Results/counts are returned only after dynamic status ends. `CreateVotingVotesTable` includes **UNIQUE(voting_id,user_id)**, so simultaneous votes cannot both persist on the correctly deployed schema. The application also checks existing vote and catches duplicate/unique errors. Actual MySQL race behavior and friendly response under production `DBDebug=false` remain untested; an INSERT returning false without exception must not be reported as success.

Time semantics are intentionally date-based in controller creation: start 00:00:00, end 23:59:59; dynamic status compares calendar dates. They are not exact time-of-day boundaries. Dashboard compares timestamps, so timezone/null handling consistency needs tests. Creation accepts an array length >=2 but filters blank/invalid options afterward, allowing fewer than two stored valid options; no maximum option count or text cap was found. Creation is transactional; status/vote race is not synchronized on the voting row, so a concurrent close can occur after the active check but before vote INSERT. Decide whether those boundary votes must be rejected.

No duplicate vote vulnerability is claimed against an installed correct unique constraint. Verify SHOW INDEX on production rather than assuming the migration ran. Pagination and batched counts are P1.

## 13. Kas

Ketua/Admin/Bendahara can create/delete; other configured tenant roles can read. List/summary/delete target queries scope tenant; body tenant override is ignored. No update route was found. Single-row INSERT/DELETE is atomic as a database operation, and balances are computed from transactions rather than a separate mutable balance, reducing lost-update risk.

`nominal` is a signed **INT**, while validation accepts general numeric >0 and response mapping casts to int. Fractional or oversized input can truncate/error depending on MySQL mode; no reliable decimal financial model can be claimed. For whole rupiah use bounded integer validation and a sufficiently wide exact integer type; for fractions use explicit DECIMAL and string-safe serialization. Future dates are not forbidden; configured backdate days are enforced for past dates. Cash rows can be permanently deleted and are not an immutable financial ledger. No idempotency key, duplicate-submission constraint or cash audit journal exists. Two requests can insert the same logical payment twice; two deletes can both act on a previously observed row. This is not a demonstrated aggregate lost-update race.

Kas tests cover amounts/models/month filtering/role and tenant scope but not production overflow, replay/idempotency, concurrent mutations or recovery. Month prefixes and tenant-only FK indexes do not prove efficient long-history sorting; add pages and validate candidate date composites.

## 14. Inventory

The stock state machine handles pending -> approved/rejected and approved -> returned/rejected; same status is treated as success. Available stock is checked at request time and approval. Only inventory.approve roles can change status. Loan reads join through scoped inventory and ordinary users see their own loans.

**SEC-08 / HIGH correctness finding:** [InventoryController.php](website/app/Controllers/Api/InventoryController.php), `changeLoanStatus`, lines 148–211 explicitly remove transStart/transComplete. `SELECT ... FOR UPDATE` has no durable lock across the subsequent statements in normal autocommit mode. Two loans may each read stock 1, each write 0, and both become approved; the counter need not go negative for stock to be oversold. Two duplicate returns can race, and a failure between stock and loan writes leaves inconsistent state. The [MySQL locking-read documentation](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html) requires a transaction for these locks to protect the workflow.

Smallest proposed fix: begin transaction, fetch loan through tenant-scoped inventory, lock loan and inventory in consistent order, reevaluate status/stock under locks, update both, check transaction status, commit, then notify. Enforce available quantity bounds and affected-row results. Test two concurrent approvals on last stock, two returns, approve versus reject, and a forced second-write failure against MySQL. Existing SQLite serial idempotency tests do not test autocommit lock lifetime.

The early same-status return occurs before tenant ownership validation: a foreign loan ID with matching requested state yields success. This is an ID/state oracle needing a direct negative test even though it performs no stock mutation. Move ownership validation before all successful returns. Borrow and return date ordering is not enforced. Pending requests do not reserve stock; approval is the decisive stock operation.

## 15. Flutter

State management is primarily StatefulWidget/setState with async service calls, no discovered Provider/BLoC/Riverpod architecture. Chat uses broadcast streams; auth/tenant state uses SharedPreferences. Lists often use lazy ListView.builder but retain the full downloaded backing collection; lazy painting does not cap network data or memory. Chat keyset pages and contact paging improve initial cost, yet accumulated history/messages have no fixed in-memory maximum.

Analyzer: **0 errors, 16 warnings, 158 infos**. Most warnings are unused imports/fields; infos include async BuildContext usage and deprecated UI APIs. Analyzer exits nonzero. App update/models/storage/chat regression tests exist; no device CPU/frame/memory profile was run. UI performance cannot be classified GOOD at 10k rows based on source or unit tests.

Lifecycle findings: NotificationService.initialize subscribes to token refresh/foreground/opened streams each invocation without an initialization guard or retained subscriptions. InitialScreen invokes it for valid sessions, so tenant/app-state re-entry may duplicate listeners. Profile upload screens call setState after async work without consistent mounted checks; finally cleanup is present in all three role profile screens. Chat list debounce timer/subscriptions/scroll/tab controllers are disposed; chat room controllers/subscription are disposed; wheel AnimationController and socket handlers are removed. Wheel `off(event)` removes all listeners for that event, not only the current screen callback; there is no session-ID filter in received handlers, leave-wheel event, or auth/reconnect resubscription.

Network behavior: ApiClient provides a 30 s Future timeout and controlled 408/503/500 responses. It has no universal token-expiry interceptor, request cancellation, persistent offline mutation queue, or retry policy. Timed-out Future wrappers do not establish that underlying server mutation was canceled. Avoid automatic retries for money/inventory/chat until idempotency exists. Profile multipart upload bypasses ApiClient and has no timeout. Several list services turn errors into empty arrays, obscuring failure versus empty state. InitialScreen only uses its connection-failure UI for null statusCode; synthetic 408/503 from ApiClient have a statusCode and can instead route back to PIN. Improve retry UX while preserving a valid token/tenant during transient failure.

Storage: auth_token is plaintext SharedPreferences, not secure credential storage (SEC-14). No persisted password, bulk chat JSON cache, local chat database, or base64 image store was found in the inspected storage/service code. Image-picker temporary files are removed in finally, but no central app-cache budget/pruning policy was found. Picker quality is 85 with no max dimensions; very large images can consume decode/upload resources even if compressed.

**Mobile readiness: FAIL for broad release**, driven by signing and tenant socket lifecycle; analysis hygiene and network UX remain conditional improvements.

## 16. Android

**MEASURED existing APK:** `mobile/build/app/outputs/flutter-apk/kartar app.apk`, **29,904,469 bytes = 28.52 MiB (29.90 decimal MB)**. It was last modified 2026-09-17; it is an existing artifact, not a fresh reproducible build from audited HEAD. AAPT: applicationId `com.kartar.app`, versionName **1.0.2**, versionCode **3**, minSdk **24**, targetSdk **36**, native-code **arm64-v8a only**. Source pubspec is 1.0.2+3. Other ABIs, including 32-bit ARM, are not supported by this APK.

Source ABI restriction checks Gradle task names containing capitalized `Release`, then applies abiFilters and packaging exclusions. The current artifact confirms the intended output; test the actual release command in CI because task-name heuristics are brittle. No new APK/build was produced. Native scanner/printer/location/Firebase packages still contribute dependencies even if features are unused.

**SEC-11 / HIGH:** release signing points to `signingConfigs.getByName("debug")`. `apksigner verify --print-certs` confirms certificate subject **Android Debug**. This does not mean the APK's debuggable flag is true: AAPT did not report application-debuggable, and merged manifest inspection found no debuggable=true. Debug signing is nevertheless unsuitable as the long-term production update identity. Establish a protected release keystore, recovery/backup, and an upgrade migration strategy for any already distributed debug-signed installs. A change of signing identity can prevent in-place updates.

**SEC-15 / MEDIUM:** merged/source application allows `usesCleartextTraffic=true`; current API/socket URLs are HTTPS. Restrict cleartext in release and confine development exceptions. No network-security-config file was found at the expected source location. Camera/Bluetooth/location permissions remain; merged APK also includes notifications/wakelock/network/FCM permissions. Audit legacy printer/scanner requirements before removing. Launcher activity exported=true is expected; image picker providers/internal services are exported=false; Firebase exported receiver has its required permission. No app deep-link intent filter was found beyond launcher/process-text query. Merged third-party components require review on dependency changes. Public Firebase app configuration is not equivalent to a service-account private key; no private signing key was printed or inspected.

Recent commits `6dc11ef` and `f1a3e47` improved size/update integration; they did not solve signing or cleartext configuration.

## 17. FCM

The device table intentionally uses global user ownership. Registration finds a unique FCM token and updates owner on account switch; deletion requires current user + token. A token refresh subscription sends the replacement token. 10k users x 1–3 devices implies 10–30k device rows (ESTIMATED), manageable in principle with current user/token indexes; no production size or delivery measurement exists.

Logout attempts device deregistration before API logout, catches failures, and removes local auth regardless. If deregistration fails, the installation can continue receiving notifications addressed to the previous owner until successful rebinding; server logout itself does not remove device records. Revoked/inactive users and departed custom-room members can still be selected in custom room notification paths because membership is not consistently rechecked. Some REST chat FCM payloads omit tenant_id/chat_id, while Node internal notifications include tenant context; taps can navigate with stale organization context. Token refresh inserts a new unique token but does not directly prune old registrations; unsuccessful/stale token cleanup happens during send. Unique-token races and false insertion success need DB error-path tests.

`NotificationService::sendPushNotification` obtains an OAuth token then loops devices, one HTTP v1 request each with 5 s request / 3 s connect limits. FCM work is synchronous inside event/announcement/inventory and REST-chat requests; Node delegates notification to PHP asynchronously, but that PHP worker remains occupied. For 1,000 tokens at assumed 200 ms/request, fan-out is ~200 s; at five seconds each it can be ~5,000 s if failures continue through the loop. These are arithmetic scenarios, not measured delivery latency. The Node 3 s abort is not proof PHP has stopped work. Queue-based bounded fan-out is justified by existing O(devices) synchronous design, without requiring Redis: a small durable DB-backed worker can be evaluated first.

Invalid-token log includes the full FCM token; response logs can include provider body. Redact tokens and avoid message/PII bodies in diagnostic logs. Token access is not cached across PHP requests, adding repeated auth/network cost. FCM_MOCK tests exercise notification routing with no real provider delivery guarantee.

## 18. File Storage

Profile: uploaded image JPEG/PNG/WebP MIME + is_image, max **5,120 KiB**, randomized server filename, 512x512 crop/compress, global user path `uploads/users/profile/`. Logo: same MIME family, max **2,048 KiB**, resize preserving ratio, `uploads/karang_taruna/logos/`. CI4 4.7.4 `UploadedFile::getExtension()` attempts a MIME-guessed extension before client fallback; do not falsely claim it blindly retains a `.php` client extension. Validation still benefits from an explicit allowlist and server re-encoding. Neither endpoint relies on a client-provided filesystem path.

Both processing handlers fall back to moving the original file if image transformation fails; this loses the fixed dimensions/re-encoding guarantee. Directories are created with 0777 subject to umask. Profile safeDeleteOldPhoto verifies prefix and rejects `..`, updates DB before deleting old file, and deletes the new image on DB failure. Concurrent profile updates can still orphan an intermediate image. Logo deletes old files before successful DB update and ignores some DB failures; a failed update can reference a missing old file and leave a new orphan. Restrict logo deletion to managed paths and make success ordering consistent.

No executable-upload exploit was proven. **Nginx must deny script execution in uploads and direct access to app/vendor/writable/.env**, since website root is the document root candidate and `.htaccess` protects only Apache. No effective Nginx configuration was available to verify these requirements. Photos/logos are publicly addressable; they are not confidential tenant documents. If private files are introduced, add authorized download paths rather than guessing secrecy from filenames.

Storage planning (**CALCULATED/ESTIMATED**, actual 15 GB infrastructure unknown):

| Component | Explicit scenario | Disk envelope |
|---|---|---|
| 10k profile photos | Average 100–200 KiB after processing | ~0.95–1.91 GiB |
| Profile upper admission bound | 10k x 5 MiB originals if fallback retained | ~48.83 GiB; incompatible with assumed 15 GB disk |
| 100 organization logos | Average 200 KiB | ~19.5 MiB; max 2 MiB each ->200 MiB |
| Retained chat | 1,000 DAU x 10 messages/day x 30 days =300k rows | At 0.5–1.5 KiB/row ~146–439 MiB raw; reserve more for indexes/DB overhead |
| Large chat text | 2k Unicode chars x up to 4 bytes x300k | ~2.24 GiB message text alone |
| User/member/device data | 10k identities, multi-membership/devices | Tens to low hundreds of MiB, estimate dependent on indexes |
| Attendance/cash | 100k each in planning dataset | Approx 60–200 MiB combined planning envelope; not measured MySQL file sizes |
| Application/PM2/Nginx logs | Assumed 10 MB/day combined | ~300 MB/month without pruning; actual logs unknown |
| Backups | Seven 2 GiB local dumps/upload snapshots | 14 GiB, before system/DB/live uploads; unsuitable on assumed 15 GB |

Runway formula: `(verified_free_bytes - reserved_headroom - temporary_backup_space) / measured_daily_net_growth`. Example only: 6 GiB usable /50 MiB/day =~123 days. No actual storage runway can be reported until free space/growth is measured. Chat DELETE may release reusable table space without returning disk to the filesystem. Back up off-host; configure both retention and minimum free-space alerts. ARM64 APK size does not predict database/upload disk growth.

## 19. Security Findings

Canonical security registry; severity reflects source/reproduced scope, with deployment uncertainty explicitly marked:

| ID | Severity | Finding / evidence | Smallest proposed fix / required regression |
|---|---|---|---|
| SEC-01 | CRITICAL, historical | Cross-tenant attendance status metadata; `AbsensiController::status` lines 199–217; reproduced. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch 1 | Selected user + attendance tenant + parent event tenant enforced; safe negative/positive regressions pass |
| SEC-02 | CRITICAL, historical | Private socket user room lacks tenant namespace; original Node lines 123,365–366; reproduced. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch 2 | One tenant+user room helper, authoritative private sender/receiver delivery, eligible recipients, Flutter tenant/context checks, safe switch/reconnect and multi-device regressions; historical exploit preserved below |
| SEC-03 | CRITICAL, historical | Middleware accepts pending/rejected active membership with other tenant token; reproduced. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch 1 | AuthFilter requires eligible membership before establishing tenant context; internal socket-auth inherits it. Independent recipient/discovery checks remain later work |
| SEC-04 | CRITICAL, historical/latent | Legacy `App\Libraries\ChatServer::onMessage` trusted user_id/tenant without token and logged message bodies. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch3: **LEGACY SERVICE RETIRED** | Handler removed; command is fail-closed with exit1; Ratchet dependency removed. No active app-client/startup reference found; production runtime still UNVERIFIED |
| SEC-05 | HIGH, resolved in worktree | Historical CSRF-off/GET mutation defect. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch5 | Session framework CSRF; eight GET mutations become POST; all16 missing-token actions blocked, unchanged cross-site row/session snapshots; existing announcement-create schema failure disclosed |
| SEC-06 | HIGH, resolved in worktree | Historical username passwords, shared reset/defaults and inconsistent forced change. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch5 | Independent random temporary credentials, bcrypt, flag1/exact endpoint restrictions, response-only delivery,12-character/72-byte policy; production default login gate/test-only seeder. SEC-12 remains open; private privileged provisioning required |
| SEC-07 | HIGH, historical | Installed/locked runtime engine.io6.6.9 had unauthenticated transport-upgrade DoS. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch3 | Engine.IO6.6.10 targeted override under unchanged Socket.IO4.8.3; only Engine.IO npm lock entry changes; runtime audit0; polling/upgrade and full security regression pass |
| SEC-08 | HIGH, resolved in worktree | Historical autocommit stock/loan defect. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch 4.1: existing scoped transaction/checked writes passed real MySQL approval/return/decision races and forced rollback | MySQL 8.0.46; InnoDB; one last-stock approval; one return increment; one opposing decision; rollback with no notification. Open HIGH count reduced only for SEC-08 |
| SEC-09 | HIGH | **RESOLVED IN WORKTREE — NOT DEPLOYED**: atomic local user/IP mutation quotas, bounded arrays, socket quotas and in-flight guards | Overnight phase 1 measured; single-instance controls, distributed rollout/capacity still require staging |
| SEC-10 | HIGH | **RESOLVED IN WORKTREE — NOT DEPLOYED**: REST chat.read/chat.send and dashboard announcement.view/target_role enforced | Overnight phase2 red/green and full backend; configured deputy/seksi permission omissions preserved |
| SEC-11 | HIGH | Existing release artifact is debug signed | Protected production signing identity + tested upgrade plan; artifact certificate gate |
| SEC-12 | MEDIUM | Password reset/change keeps other tokens; idle revoked sockets keep receiving | Revoke sessions / periodic receiving authorization / eviction; two-device tests |
| SEC-13 | MEDIUM, historical | Existing API/token access does not check tenant status_aktif; legacy fallback states. **RESOLVED IN WORKTREE — NOT DEPLOYED**, directly related Batch 1 authorization path | Active organization required; legacy user tenant cannot grant access. Explicit/implicit selection and invalid legacy states tested; intentionally global superadmin unchanged |
| SEC-14 | MEDIUM | Bearer token in plaintext SharedPreferences | Platform-backed secure credential storage; migration/logout tests |
| SEC-15 | MEDIUM | Android release permits cleartext | Release network policy; artifact cleartext check |
| SEC-16 | MEDIUM | FCM invalid-token log, raw mysql2 error objects, debug API response fields | Redact tokens/chat/PII/temp credentials; safe structured error logs |
| SEC-17 | MEDIUM | Wheel returns raw DB JSON/exception messages | Generic client error + safe server correlation; forced DB failure test |
| SEC-18 | MEDIUM | PHP internal API/wheel secret defaults to known development fallback | Fail closed outside tests/dev; test absent/empty configuration, bind internal services to loopback |
| SEC-19 | MEDIUM risk, NEEDS TEST | Root document structure + upload fallback requires explicit deployed server protection | Verify denied sensitive paths and nonexecuting uploads on staging; no arbitrary execution or secret disclosure proven |
| SEC-20 | MEDIUM | Custom-room FCM recipients not consistently active/approved; logout deregistration failure; REST tenant context omission | Recheck scoped recipients, persist cleanup retry/context; revoked member/account-switch tests |
| SEC-21 | LOW | Weak/incomplete web validation, arbitrary tenant-setting keys/count, permissive update URL policy | Shared validators/allowlists; HTTPS trusted update URLs; key-count and validation tests |
| SEC-22 | LOW production exposure | Vulnerable brace-expansion versions are development-only dependencies | Targeted dev lock repair before next release; no claim of runtime endpoint exploit |

No confirmed SQL injection, stored XSS, SSRF, path traversal, open redirect, or arbitrary executable upload was established. Reviewed views commonly use `esc()`, raw SQL binds parameters, and controller mutations generally construct allowlisted fields. This is not exhaustive proof of absence. CSRF applies to session-backed web, while mobile Bearer APIs need their own authorization/rate policy. Node wildcard origin is an exposure/configuration concern, not by itself an auth bypass.

Rate controls: login 5/60 s/IP-route; PIN 10/60 s/IP-route; socket 5 messages/s/socket. No evidence of rate limits on web superadmin login, registration, self password change/reset operations, voting, attendance, uploads, chat REST, join/auth events, or API globally. Cloudflare/Nginx rules UNKNOWN. CI uses a file cache throttler with dummy fallback; verify writable cache and concurrency/IP proxy behavior. Correct trusted-proxy configuration is essential: otherwise all clients may share a proxy bucket, or spoofed forwarding headers may bypass limits. RateLimitFilter's X-RateLimit-Test bypass only applies in testing.

Dependency checks on audit date:

- `composer audit --locked --format=json`: **0 advisories, 0 abandoned packages**. Locked CI4 **4.7.4**, google/auth **1.53.0**, Ratchet **0.4.4**. This does not prove deployed Composer versions or absence of undisclosed vulnerabilities.
- `npm audit --json`: **2 vulnerable package families, both labeled HIGH by npm**. Runtime engine.io **6.6.9**; dev brace-expansion **5.0.9** and **2.1.4**. Runtime priority **URGENT**, dev priority **BEFORE NEXT RELEASE**. Multiple brace advisories are not counted as independent application findings.
- The [Socket.IO maintainer advisory](https://github.com/socketio/socket.io/security/advisories/GHSA-2gc4-cqfq-p2gv) identifies affected engine.io >=6.6.0,<6.6.10 and the patched version 6.6.10. Current source permits transport upgrades, so the affected path is enabled. The exploit was not executed.
- `flutter pub outdated --json`: direct updates available include firebase_core 3.15.2 ->4.15.0, firebase_messaging 15.2.10 ->16.7.0, google_fonts 8.2.1 ->9.0.0, and several minor updates. No discontinued package was reported in the returned outdated list. This command is **not a comprehensive Dart vulnerability scanner**. Major upgrades are **LATER**, evaluated individually; outdated != vulnerable. Printer/scanner packages are usage/maintenance review candidates, not automatically unsupported.

No dependency was upgraded and no lockfile was modified.

## 20. Performance Findings

| ID | Priority | Severity | Evidence / bottleneck | Proposed bounded intervention |
|---|---|---|---|---|
| PERF-01 | P0 | HIGH | Synchronous FCM O(devices) holds PHP workers; source-confirmed, latency ESTIMATED | Persist-first queued fan-out, finite batches/timeouts, observable failures |
| PERF-02 | P1 | HIGH | Unbounded API/web lists and request arrays | Server pages/keysets, caps, previews, validated lower/upper limits |
| PERF-03 | P1 | HIGH | Event/voting/wheel count N+1 | Grouped/batched queries preserving tenant scope |
| PERF-04 | P1 | MEDIUM | Tenant/date/order composites not evidenced; CASE/LIKE sorts | Actual MySQL plans and selective indexes/date ranges |
| PERF-05 | P1 | HIGH | Per-socket limits can be multiplied; auth/join/payload/room fan-out uncapped | User/IP quotas and bounded event/room work |
| PERF-06 | P1 | HIGH | Unlimited Node pool wait queue, uncanceled auth/revalidation fetch | Queue/backpressure policy, fetch deadlines and in-flight guard |
| PERF-07 | P2 | MEDIUM | Contacts aggregate scans + offset work before page LIMIT | UNION direction plans, candidate receiver index, cursor/summary if measured |
| PERF-08 | P2 | MEDIUM | Dashboard/report internally unbounded and repeated sums | SQL aggregation, bounded candidates; tenant-keyed short cache only after profiling |
| PERF-09 | P2 | MEDIUM | Flutter accumulates backing lists, repeated socket/FCM setup, large image decode | Retention budgets/lifecycle fixes, dimensions, device profiling |
| PERF-10 | P2 | MEDIUM | Unbounded log/token/room/history growth and backup overlap | Measured growth, retention, off-host backups, disk alerts |
| PERF-11 | P3 | LOW | Static compression/cache/header opportunities unverified | Verify Nginx gzip/static TTL; cache selected non-sensitive responses |
| PERF-12 | P3 | INFO | Distributed scaling before workload evidence would add cost | Defer shared cache/adapter/LB until measured need |

Synthetic experiment (**MEASURED local SQLite only**): temporary script `C:\Users\lenovo\AppData\Local\Temp\kartar-audit-dataset.js`; Node 24 built-in SQLite in-memory, reduced schema and explicit approximate indexes. It did not load CI controllers, organization_members, FKs, production storage, MySQL optimizer, network, FCM, or concurrent requests. Ten warm repetitions/query; events 10k, users 10k, attendance/cash/chats 100k each, distributed across 10 and 100 tenants. The experiment is illustrative algorithm/payload evidence, not a replacement for staging MySQL.

| Query fragment | 10 tenants (mean ms / rows / JSON bytes) | 100 tenants (mean ms / rows / JSON bytes) |
|---|---|---|
| Scoped event select/date sort | 0.852 /1,000 /61,890 | 0.300 /100 /6,189 |
| Scoped cash select/date sort | 10.837 /10,000 /728,890 | 0.906 /1,000 /72,889 |
| Directed private history, max 100 | 0.015 /10 /1,687 | 0.017 /10 /1,687 |
| Private contact grouping fragment | 0.664 /1 /34 | 0.075 /1 /34 |
| Per-event attendance count loop | 1,001 total queries; loop 1.839 ms | 101 total queries; loop 0.225 ms |

SQLite EXPLAIN QUERY PLAN showed temporary B-trees for cash sort and contact GROUP BY; directed history used the private composite. Reduced event schema lacked a tenant index and scanned; **this is not evidence that MySQL events lacks its implicit FK index**. Contact traffic is deliberately simple and concentrated, not representative of broad real private messaging. Process RSS ~105–116 MiB includes generated datasets and Node runtime; it is not API/Node-server per-socket memory. Experiment table/file size is not a measured MySQL growth result.

Needed MySQL dataset: migrations faithfully applied to a disposable MySQL 8 database; 10/100 tenants; 10k identities with realistic multi-memberships/devices; 100k attendance/cash/chats plus recent/expired distribution; realistic custom/default groups. Capture SHOW INDEX, EXPLAIN FORMAT=JSON and read-only EXPLAIN ANALYZE of exact controller queries, cold/warm trials, rows examined, sort/temp spills, DB query counts, response bytes, and p50/p95. No MySQL timing is asserted in this report.

## 21. Reliability Findings

| ID | Severity | Finding / state | Required evidence or minimal repair |
|---|---|---|---|
| REL-01 | HIGH, resolved locally; production unverified | **RESOLVED LOCALLY — PRODUCTION SCHEMA STILL UNVERIFIED**, Batch 4.1: fresh chain, stricter synthetic upgrade, preserved rows, equivalent DDL/index/FK semantics and application writes passed MySQL 8.0.46 | Production DDL was not inspected. Historical ADD COLUMN nullability assumptions corrected in the latest ledger; no production NOT NULL guarantee is claimed |
| REL-02 | HIGH | Historical migration truncates real tables; SOURCE | Never replay on data; validate a non-destructive upgrade/backfill strategy |
| REL-03 | MEDIUM, historical | Participant GET route/function/field mismatch. **RESOLVED IN WORKTREE — NOT DEPLOYED**, narrow Batch 4 repair | Existing index method routed; no_whatsapp selected as whatsapp; authenticated route/tenant/phone regression passes |
| REL-04 | HIGH readiness gap | Backup/restore evidence absent | Off-host DB/uploads/config backups, tested restore, agreed RPO/RTO |
| REL-05 | MEDIUM | Chat persistence/notification/broadcast ordering and no durable retry | Idempotent persistence + reliable postcommit notification |
| REL-06 | MEDIUM | REST membership approval/history and registration multiple writes are not atomic | Transaction with state/ownership checks; forced second-write rollback |
| REL-07 | MEDIUM | Wheel pre-lock session state, close/spin race, precommit wheel_closed emit | Reload status under lock; synchronize close; postcommit emits |
| REL-08 | MEDIUM | Socket/FCM listener and tenant switch/reconnect gaps | One initialized subscription set, dispose old socket, rejoin/leave scoped rooms |
| REL-09 | HIGH readiness gap | Single-instance recovery, health/metrics, disk and log rotation unverified | Runbooks, startup/restart evidence, alerts and tested recovery |
| REL-10 | MEDIUM | Logo DB/file ordering and concurrent profile orphans | DB-success cleanup ordering, managed-path checks, orphan reconciliation |
| REL-11 | MEDIUM, historical | Flutter test performs production HTTP instead of isolated mock. **RESOLVED IN WORKTREE — NOT DEPLOYED**, Batch 0 | In-memory fallback transport + hard HTTP/WebSocket test guard; final 43-test run has zero unexpected production transport attempts and zero external successful requests |
| REL-12 | MEDIUM | Read 30-day chat retention exists but installed cron unknown | Scheduler lock, run logs, deletion/backlog alerts |

Atomicity map:

| Workflow | Application guard | DB-level guarantee / gap |
|---|---|---|
| Check-in | Read-before-insert | Unique event/user; one lifecycle guaranteed if deployed |
| Checkout | Checks empty time | Conditional update absent; duplicate requests can overwrite timestamp |
| Vote | Existing-vote check | Unique voting/user; close/vote boundary not locked |
| Inventory approve/return/reject | Stock/state checks | **No transaction**; lock does not span writes |
| Kas create/delete | Permissions/tenant | Single statements atomic; no logical request idempotency/audit ledger |
| Membership approve/reject REST | Pending check | Update/history not transactional; opposite actions can race |
| Membership approve/reject web | Pending check + transaction | Atomic writes but initial status checked before transaction; row lock/CAS absent |
| User create | Exclusive role/username checks | User+membership transaction; exclusive role concurrency only application guard |
| Register | Username/phone checks | User+membership no transaction; no unique phone constraint found |
| User profile/admin update | Membership checks | Global user + tenant username updates not atomic; concurrent profile orphan risk |
| Group creation | <=100, membership validation | Transaction; rollback invalid member; recheck revoke races if needed |
| Group deletion | Tenant/type/role | Single parent delete + FK cascade if actually installed |
| Wheel spin | Creator/state/latest duration checks | Transaction + MySQL row lock, but initial session stale and close unsynchronized |
| Wheel create/duplicate | Input checks | Transactions bypassed in testing; duplicate does not check transStatus consistently |
| Web tenant create | PIN uniqueness check | Tenant+default-room+upload not atomic |
| Settings update | Bounds | Tenant transaction; concurrent upsert conflict possible; global web update lacks multi-key transaction |

Single points of failure, **if reported topology is accurate**: one EC2 outage interrupts API/web/socket/uploads; one MySQL outage interrupts login and all core state; one socket process loses realtime rooms; local disk failure loses DB/uploads without off-host backup. Cloudflare cache/proxy does not remove those origin dependencies. No premature microservice split is recommended.

## 22. Test Coverage

No instrumented line/branch coverage report was generated; these labels reflect functional scenarios found, not a percentage.

| Module | Coverage | Evidence / missing cases |
|---|---|---|
| Auth/PIN/tenant/RBAC | PARTIAL | AuthTest, ActiveTenantContextTest, RbacPolicyTest, AuthServiceRbacTest; pending/rejected bypass and defaults escaped baseline |
| Users/memberships | PARTIAL | MemberLifecycle, IdentitySemantics, MembershipApi/Approval; role/approval concurrency missing |
| Tenant isolation | PARTIAL | TenantIsolationTest + module checks; newly reproduced status/socket context defects |
| Profile/photos | PARTIAL | ProfileTest, ProfilePhotoTest; multipart deployment rules/concurrency missing |
| Events/attendance | PARTIAL | EventLifecycle/Ux/AttendanceFilter, AbsensiTest; MySQL race and precise expiry/radius parity missing |
| Kas | PARTIAL | KasMonthTest, model tests, tenant/RBAC paths; overflow/replay/recovery missing |
| Voting | PARTIAL | VotingTest; actual MySQL duplicate and close/vote concurrency missing |
| Inventory | PARTIAL | InventoryIntegrity/Loan tests are serial SQLite; MySQL lock lifetime not tested |
| REST chat / retention | PARTIAL | ChatTest, notification spoof/routing; approval/permission/FCM persistence-order gaps |
| Socket | PARTIAL | 19 baseline tests cover auth/room/message/tenant spoof/env; one audit context test; DB/internal API mocked |
| Flutter chat | PARTIAL | Dedup/timestamps/contacts/stream/fallback/source regressions; network stub invalid; device lifecycle missing |
| Wheel | PARTIAL | WheelFeatureTest, Feature/WheelTest; production lock/close/reconnect cases missing |
| Dashboard | PARTIAL | DashboardTest; role-targeted preview, large candidate sets missing |
| Android update | GOOD for unit version comparison; PARTIAL system | Backend AppVersionTest; six Flutter cases newer/same/lower/disabled/failure/malformed; external APK upgrade/signature untested |
| Flutter storage/session/models | PARTIAL | Storage/session/model tests; secure storage migration and provider lifecycle untested |
| Web superadmin | PARTIAL | PlatformSuperAdminTest does not establish CSRF/session browser safety; broad web flows missing |
| FCM actual delivery | MISSING | Mocked requests only; no real provider multi-device/stale/logged-out test |
| Upload execution / Nginx / backup / restore / production load | MISSING | No staging/browser/server recovery evidence available |

Skipped backend test: `SocketAuthTest::test...` remote-IP scenario explicitly skips because CLI FeatureTestTrait cannot easily mock a remote address. Loopback enforcement is source-inspected, not runtime proven by that suite.

Commands run: `php vendor/bin/phpunit --colors=never` in website with `CI_ENVIRONMENT=testing`; `npm.cmd test -- --silent`; `flutter.bat analyze --no-pub`; `flutter.bat test --no-pub --reporter expanded`; `dart.bat analyze --format machine` to count exact categories; `php -l` over app files; Composer/npm audits; `flutter pub outdated --json`; AAPT/APK signature inspections; git hygiene checks. Exact baseline/post-addition counts are in section 1. Node assertions and Flutter assertion totals are not emitted by their standard reporters and are **UNKNOWN**, not invented. Warning totals reported by suites were zero where none were emitted; Dart outdated and Node SQLite tooling do not change application test pass counts.

Audit additions: [ProductionReadinessAuditTest.php](website/tests/Api/ProductionReadinessAuditTest.php), 4 tests/7 assertions; [production-readiness.audit.test.js](chat-server/tests/production-readiness.audit.test.js), 1 test. Both files explicitly label passing expectations as **current defect characterizations**. After a fix, convert them to enforce denial/isolation rather than keeping insecure outcomes as accepted behavior. Initial characterization setup encountered a dirty SQLite transaction status and a Jest callback-return error; both test harness issues were corrected. The final runs above contain no remaining characterization failures. The PHP harness resets only test transaction status because suppressed SQLite migration DDL failures leave it dirty; production application code was not altered.

## 23. Capacity Model

**No representative concurrency load test was run.** The following is a planning model, not a benchmark or a server sizing recommendation.

Assumptions for browsing sessions: 1 request per 20 seconds/active user, 0.2 s origin service time; one socket per active user. HTTP in-flight is `RPS x service_seconds`. Active users, open HTTP connections, executing HTTP requests, PHP workers, and DB connections are different quantities. A burst of attendance/login can greatly exceed steady browsing.

Let `F=total effective PHP-FPM max_children`, `N=Node instances`, `P=per-instance DB pool cap`, `A=admin/monitor/cron connection reserve`. Source default P=10, cap <=100; F/N/A and MySQL max_connections UNKNOWN. Approximate worst connection budget is **F + N*P + A**, assuming one shared PHP DB connection per worker. Other PHP pools/long-lived workers and maintenance processes must be added. Example only: F=16,N=1,P=10,A=8 ->34; F=32 ->50. Keep reserve below actual MySQL limit; do not set it from registered users. Active busy connections and open persistent pool connections differ.

| Scenario | Registered | DAU | HTTP concurrent executing | Socket concurrent | DB connections | CPU | RAM | Disk | Status / evidence |
|---|---:|---:|---|---:|---|---|---|---|---|
| Current infrastructure | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | F+N*P+A UNKNOWN | UNKNOWN | UNKNOWN | 15 GB merely reported possibility | NOT TESTED; infrastructure UNKNOWN |
| 10k registered / normal use | 10,000 | 1,000 assumption | ~1 at 100 active browsing sessions | ~100 assumption | Budget above; not 10k | UNKNOWN | UNKNOWN | Section 18 estimate | CONDITIONAL data-volume feasibility; current production NOT READY |
| 100 active concurrent users | Independent of registered total | UNKNOWN | ~1; ~5 RPS calculated | 100 | Pool shared; measured pressure UNKNOWN | UNKNOWN | Socket increment estimate below | UNKNOWN | NOT TESTED |
| 250 active concurrent users | Independent | UNKNOWN | ~2.5; ~12.5 RPS calculated | 250 | UNKNOWN | UNKNOWN | Estimate below | UNKNOWN | NOT TESTED |
| 500 active concurrent users | Independent | UNKNOWN | ~5; ~25 RPS calculated | 500 | UNKNOWN | UNKNOWN | Estimate below | UNKNOWN | NOT TESTED |
| 1,000 active concurrent users | Independent | UNKNOWN | ~10; ~50 RPS calculated | 1,000 | UNKNOWN | UNKNOWN | Estimate below | UNKNOWN | NOT TESTED |
| 10,000 active users, extreme | >=10,000 | UNKNOWN | ~100; ~500 RPS calculated | 10,000 | UNKNOWN; queue/rate risk | UNKNOWN | Estimate below | UNKNOWN | NOT TESTED / NOT PROVEN |

If "100 concurrent HTTP" means 100 simultaneously executing requests rather than 100 active people, it is a materially heavier workload and is separately **NOT TESTED**. Do not use the small browsing estimates to certify that interpretation.

Socket memory planning: assume **50–150 KiB incremental memory/socket** for Socket.IO/transport/metadata/rooms, excluding process baseline, kernel buffers, queued messages, clients and fan-out. This range is **ESTIMATED, not measured**, and can be exceeded by queues/rooms/traffic.

| Sockets | Calculated incremental memory under assumed range |
|---:|---:|
| 100 | 4.9–14.6 MiB |
| 250 | 12.2–36.6 MiB |
| 500 | 24.4–73.2 MiB |
| 1,000 | 48.8–146.5 MiB |
| 5,000 | 244–732 MiB |
| 10,000 | 488–1,465 MiB |

Measure actual Node RSS/heap, event-loop lag, socket count and queue depth at each stage. Do not derive a supported socket ceiling from this range. Source Map stores Bearer tokens in memory for revalidation; protect diagnostics/heap dumps.

FPM RAM model: `F x measured worker RSS + MySQL resident memory + Node RSS + OS/agents + headroom`. For assumed 30–80 MiB/worker, 8 workers use ~240–640 MiB, 16 use ~480–1,280 MiB **before** the other services. Actual RSS/OPcache/buffer sharing and infra RAM UNKNOWN. Never set a large max_children merely to match user count. Waiting synchronous FCM workers still occupy capacity even when CPU is low.

Nginx ceiling: effective worker_processes x worker_connections is an upper slot count, bounded by file-descriptor limits. Proxied HTTP/WebSocket connections consume client and upstream slots; accept backlog, kernel limits, PHP capacity and memory matter. No practical concurrent ceiling can be calculated without deployed values. Proxy upgrade headers/read timeouts, keepalive, client body size, static cache/gzip and FastCGI limits are all NEEDS TEST. Cloudflare forwards WebSockets and origin HTTP; it cannot fix database scans, FCM worker occupancy, authorization, origin memory, or restore gaps.

## 24. 10,000 User Readiness

| Requested tier | Capacity classification | Current production-readiness interpretation |
|---|---|---|
| 10,000 registered users | **CONDITIONAL** | Architecture/data volume plausible; current security/correctness gates make release **NOT READY** |
| 1,000 daily active users | **NOT TESTED** | DAU alone does not specify peak workload; representative mix absent |
| 100 concurrent users | **NOT TESTED** | NOT PROVEN |
| 250 concurrent users | **NOT TESTED** | NOT PROVEN |
| 500 concurrent users | **NOT TESTED** | NOT PROVEN |
| 1,000 concurrent users | **NOT TESTED** | NOT PROVEN |
| 10,000 concurrent users, theoretical | **NOT TESTED** | NOT PROVEN; major origin/notification/adapter planning required |

No tier is labeled SUPPORTED or LIKELY SUPPORTED from guessed hardware or green unit tests. NOT TESTED is not evidence of inevitable failure, but it prevents a capacity promise. The current server cannot be endorsed for 1k/5k/10k registered identities without actual RAM/disk/workload and fixed release gates. Registration volume alone is a poor reason to purchase distributed infrastructure.

## 25. Infrastructure Upgrade Triggers

Use measured triggers after correctness and query repairs:

- Vertical CPU/RAM upgrade: sustained CPU >70% during representative peak windows, repeated burst-credit exhaustion, FPM listen queue, worker saturation, RSS headroom <20%, swap/OOM, or p95 breaches attributable to origin resources. Confirm whether FCM stalls or bad queries are the cause before buying hardware.
- Disk: alert at ~70% usage, plan expansion by ~80% or <30 days measured runway; reserve sufficient space for temporary DB operations and backups. These are provisional operating thresholds.
- MySQL: repeated connection occupancy >70–80% of verified limit, pool waiting, lock waits or slow-query p95; first tune queries/pools. Do not simply raise max_connections on insufficient RAM.
- Socket: rising event-loop lag, memory/queue growth, reconnect/auth bursts, delivery p95 >500 ms under normal regional workload, or process instability. Measure one process before sharding.
- Reliability: business RPO/RTO requires surviving one host/disk failure -> off-host backups immediately; managed database/object storage/multiple origins when justified by downtime tolerance, not user count alone.
- FCM: fan-out holds request latency beyond mutation target -> durable background dispatch before raising FPM workers.

Early production and 1k/5k/10k registered-user server retention are **CONDITIONAL on measured peak, disk and recovery evidence**, not automatically rejected or approved by account totals. No provider price/instance-size quote is given because infra/budget/workload facts are unavailable.

## 26. Recommended Fixes

These are proposals for review, **not implementations**. Each behavior change needs a minimal patch, regression requirements below, and separate authorization to proceed.

| Priority | Recommendation | Impact | Cost | Complexity | Validation |
|---|---|---|---|---|---|
| P0 | SEC-01/02/03 tenant scope, room namespace, approved membership | Critical | Low | Low–medium | Turn audit characterizations into safe-denial/isolation tests; two tenants/devices |
| P0 | Inventory transaction and ownership-before-idempotent-return | High correctness | Low | Medium | MySQL simultaneous requests + rollback |
| P0 | Targeted Engine.IO patch and disabled legacy Ratchet | Availability/security | Low | Low | Full socket tests + transport compatibility |
| P0 | Web CSRF/POST mutations and safer credential/default flow | High security | Low | Medium | Browser cross-site/role/login-reset regressions |
| P0 | Production APK signing + existing-install transition | High release integrity | Low infrastructure cost | Medium | Signed artifact and real upgrade test |
| P0 | MySQL schema/migration parity and actual restore verification | High reliability | Low–medium | Medium | Disposable MySQL migrations + restore + critical flows |
| P0 | PERF-01 persist-first durable notification dispatch | Worker availability | Low–medium | Medium | Worker failure/retry/fan-out timing; API remains inside targets |
| P1 | Server pagination/request caps/validated user lower bounds | High scale | Low | Medium client/API work | 100/1k/10k/100k rows and invalid bounds |
| P1 | Batch event/voting/wheel counts and fix participant route/schema | High | Low | Low–medium | Correct counts/tenant filters with constant page query budget |
| P1 | MySQL EXPLAIN-guided composites/date queries | High when plan proves | Low | Low–medium | Before/after rows examined and latency; rollback plan |
| P1 | User/IP rate limits, bounded pool queue/auth/join deadlines | High overload protection | Low | Medium | Multi-device bypass, reconnect storm, saturation |
| P1 | Password/session revocation, current-credential policy, secure mobile token | High security | Low | Medium | Two-device token reset and offline logout |
| P1 | Chat acknowledgement/idempotency/postcommit fan-out | High delivery correctness | Low–medium | Medium | Lost ack, reconnect, duplicate logical request |
| P1 | Scheduled cleanup, off-host backups, log retention/metrics/alerts | High operational | Low recurring | Low–medium | Observable successful schedules and restore drill |
| P2 | Flutter socket/FCM singleton lifecycle, upload timeout/dimensions, bounded history | Medium | Low | Medium | Device navigation/weak network/memory profiling |
| P2 | Dashboards/report SQL aggregation and tiny scoped cache if measured | Medium | Low | Low–medium | Invalidation/role/tenant cache tests |
| P2 | Exact money validation, immutable audit trail/idempotency, wheel close/spin sync | Medium–high correctness | Low | Medium | Boundary/replay/concurrency tests |
| P2 | Remove or isolate legacy/diagnostic deployment artifacts after usage review | Medium risk reduction | Low | Low | Verify references/deployment first; do not delete blindly |
| P3 | Static caching/compression and optional package maintenance | Low | Low | Low | Headers/payload and compatibility checks |
| P3 | Shared cache/Socket.IO adapter/LB/multi-app architecture | Workload-dependent | Medium–high recurring | High | Only with proven single-instance bottleneck/availability need |

Scaling roadmap:

**Stage A — current/early production:** keep CI4/MySQL/one Node service; repair critical defects, validate schema, stable signing, cap lists, minimize SQL, rate/backpressure, install cleanup/log rotation, test off-host restore and basic alerts. A simple DB-backed notification worker may be enough. Redis is not needed because 10k accounts exist.

**Stage B — approaching 10k registered:** repeat realistic MySQL/load dataset, verify tenant skew and event/notification bursts, tune pool/FPM within measured memory, upgrade vertically only on triggers, expand disk or move uploads when observed growth requires, and strengthen immutable financial/approval records. Cache selected short-lived tenant/permission-safe aggregates only if measured.

**Stage C — high concurrency:** if one origin/process no longer meets measured SLO/availability needs, consider managed MySQL, object storage/CDN, load balancer and multiple app instances, shared session/cache, Socket.IO Redis adapter, durable queue workers. Include polling affinity and cross-node broadcasting, graceful draining, backup recovery and operational budget. No microservice rewrite is justified by this audit.

## 27. Load Test Plan

Run only against an explicitly isolated local/staging origin and synthetic users/devices; disable/mock external FCM delivery and production download URLs. Safety gates must reject production hostname/IP, require a disposable database schema, block outbound real notification calls and avoid production credentials. Do not seed production. Validate authorization and data invariants before escalating load.

Scenario matrix:

| Scenario | Workload and invariants |
|---|---|
| A API browsing | Dashboard/user/announcement/inventory/cash reads with randomized tenant skew, pages and think time |
| B login | Real password hashing and token writes; expired/revoked/pending cases; realistic rate limits rather than bypassing every failure |
| C event lists | Small/large tenant archives, attendance_only, batched counts, next-page correctness |
| D attendance | Synchronized event rush; valid/invalid GPS; duplicate check-in/checkout races; exactly one lifecycle |
| E chat REST | Bounded history/contacts; disabled socket fallback sends; idempotency/persistence ordering after fixes |
| F socket authentication | Real internal PHP auth and MySQL membership; cold start/reconnect burst; late auth timeout |
| G private socket | Tenant/peer isolation, same-user devices, acks/duplicates, token expiry and receiving revocation |
| H group chat | Default tenant groups plus custom up to100 members; removal/revocation; fan-out and queue depth |
| I mixed | Initial proposal: 40% dashboard/events,20% member/announcement/cash browsing,15% history/contacts,10% inventory/voting reads,10% chat sends,5% login/attendance/mutations; refine from usage |

Test stages **25 ->50 ->100 ->250 ->500 ->1,000 active clients**. At each stage: warmup 2 min, steady 10 min, controlled event burst, recovery/cooldown 2 min; repeat at representative region/network. Separate steady socket sessions from in-flight HTTP concurrency, and report both. Start with 10 then100 tenants and include one disproportionately large tenant. Optional 30–60 min soak only after short stages pass. A 10k-connection scenario is a separate later experiment, not inferred from the1k stage.

Provisional audit targets: API p50 <300 ms; p95 <1 s; critical mutation p95 <1.5 s; unexpected errors <1%; socket stored-message delivery preferably p95 <500 ms under normal regional conditions. Count intended 401/403/409/429 separately from unexpected server errors; also report successful denied-access invariants. These are operating targets, not contractual SLAs.

Stop escalation when unexpected error rate >=1% for a sustained interval, p95 above target across two windows, OOM/swap/restarts, CPU >85% sustained, FPM queue rising, DB connection/pool queue saturation, growing event-loop lag/backlog, disk headroom unsafe, or any data/security invariant fails. Save results and recover between stages; do not hide overload by infinitely retrying. A single security or oversold-stock failure blocks release regardless of latency.

Capture: origin/staging SHA and hardware, PHP/Node/MySQL versions and limits, random seed, rows/indexes, RPS/HTTP in-flight/socket count, p50/p95/p99, status codes, response bytes, query counts/plans/rows examined, lock waits, pool wait/queue, FPM active/idle/listen queue, CPU/RAM/disk, Node RSS/heap/event-loop lag, reconnects, message DB+delivery timing and duplicate/loss metrics. Record FCM mocked versus actual provider work explicitly. k6 HTTP and a Socket.IO-capable Node harness are possible implementations; no runnable production load harness was created or executed in this audit.

Existing [load_test.js](chat-server/tools/load_test.js) was inspected only. It emits fabricated `fake_jwt_token_*` values against real opaque-token authentication; it therefore cannot establish authorized workload capacity as written. RSS/event-loop measurements describe the generator process, not the server. It counts each received recipient event as a DB insertion without checking persisted rows, expects exactly two deliveries without modeling devices, has no group workload or message-latency recording, and sends tenant-2 users toward tenant-1 peers. It can accept arbitrary SERVER_URL without a production exclusion guard. Replace these assumptions in a separate authorized staging harness before trusting its metrics; this file was left untouched.

## 28. Production Runbook Gaps

Backup/recovery **CONDITIONAL / unverified**, not an assertion that no backups exist. No project-specific dump/upload/config backup schedule or restore procedure was found. Minimum evidence: encrypted off-host DB+uploads+environment recovery, limited-access credentials, successful restore to an isolated host, critical workflow verification, checksums, retention/restore logs and agreed RPO/RTO. Example objectives for discussion: daily backups imply at best ~24 h RPO absent binlog/PITR, and an RTO must be timed during a drill. Do not promise a recovery time without executing it.

Scheduled jobs required/evaluated:

- `chat:cleanup`: daily candidate schedule, tenant-independent UTC cutoff, single-run lock, timeout/backlog metrics. Suggested operator-reviewed command: `flock -n /run/lock/kartar-chat-cleanup.lock /usr/bin/php /var/www/kartar/website/spark chat:cleanup` from the correct working directory/user. Cron/systemd entry and actual paths/permissions must be verified before installation. No schedule was installed.
- Expired/revoked token pruning, stale-device maintenance, upload orphan reconciliation and log retention are recommendations; no equivalent active command/schedule was found.
- Backups, restore drills and Node startup/log rotation require deployment jobs outside app routes. Avoid duplicate cleanup/backups after adding a second instance; cron needs a designated runner or shared lock.
- Wheel spin completion is inferred from stored timestamps/duration; completed_at lacks an evidenced periodic finalizer. Decide whether it is a deprecated field or a required job before introducing one.

CI log files rotate by date, but deletion/retention was not evidenced; daily filename change is not a storage retention policy. Node logs connections and raw error objects; PM2 can grow indefinitely without logrotate. Nginx/MySQL slow/general/binlogs and actual retention are UNKNOWN. Firebase token logs/debug temporary passwords/chat bodies need redaction. PHP production boot sets display_errors=0 and CI_DEBUG=false; effective environment and `DBDebug` remain unverified, and wheel manually exposes exceptions regardless.

Minimum budget monitoring: external HTTP availability, origin CPU/RAM/disk and burst credits if applicable, HTTP5xx/latency, FPM queue, MySQL connection/lock/slow query data, Node process restarts/RSS/event-loop lag/socket count/pool queue, notification/cleanup backlog and last successful backup. Use existing host/provider metrics and a small exporter/log dashboard before purchasing distributed infrastructure. Alarm for failed backups/cleanup, disk headroom, crash loops, latency and authorization anomaly rates.

Operator evidence needed, **without passwords/private keys**: deployed SHA; sanitized Nginx/PHP-FPM setting summary; `free -m`, `df -h`, safe process RSS/counts; MySQL version/SHOW INDEX/connection and buffer limits; PM2 mode/instances/restart cap/startup/logrotate summary; last successful cleanup/backup and restore drill; TLS/origin firewall policy. Do not paste `.env`, `pm2 jlist` environments, service-account files, private keys, or unfiltered configs containing secrets. None of these production commands were run by this audit.

Cache: source CI cache uses **file**, backup **dummy**, configured TTL60; settings have a per-request static tenant cache. No application Redis/APCu usage was found. Cache handlers listed by framework are capabilities, not proof they are enabled. No broad response caching call was found in app controllers; required pagecache filter alone does not prove authenticated data is cached. If introduced, tenant/user/permission variations and invalidation are mandatory, and cached responses must never bypass auth.

Dead/legacy candidates, not deleted: Ratchet library/CLI/dependency; disabled HTTP MigrateController; root `fix.php`, `test_auth.php`, `test_db.php`, `test_form.php`, `test_request.php`; obsolete QR/printing dependencies and Bluetooth/camera permissions; unused ParticipantModel imports; legacy users role/tenant reads in superadmin counts; `KarangTarunaController::users` references a view absent from discovered views; outdated README PHPUnit9 guidance versus installed10.5. Migration files are deployment history, not automatically dead code. Five tracked files under existing tools directories were left untouched.

The existing `website/tools/db_seed.php` deletes tables with foreign-key checks disabled and inserts a tiny synthetic dataset; it is destructive diagnostic code, not an audit prerequisite. `db_audit.php`/`final_proof.php` target local databases and contain diagnostic queries; they were inspected without execution. Deny HTTP access to both tools directories at the deployed web boundary where applicable, and never run old seed scripts against a database containing real data.

## 29. Final Verdict

**NOT READY for broader production.** Existing tests show useful functional coverage; five targeted audit tests demonstrate why that baseline is insufficient. Address tenant approval/status/socket context, inventory atomicity, Engine.IO exposure, web/credential/signing issues and MySQL/restore evidence before a release sign-off. Then measure the requested concurrency stages. Ten thousand registered users remain a conditional architectural target; ten thousand simultaneous connections are **NOT PROVEN**.

| Readiness area | Result |
|---|---|
| Tenant isolation | FAIL |
| Database | FAIL for sign-off; deployed schema itself UNKNOWN |
| API | FAIL |
| Socket | FAIL |
| Chat | FAIL |
| Mobile | FAIL for broad production release |
| Backup/recovery | CONDITIONAL, evidence absent |
| 10k registered users, current broader production readiness | NOT READY |
| 100 /250 /500 /1,000 /10,000 concurrent | NOT TESTED, NOT PROVEN |

Phase coverage crosswalk (limitations are documented rather than implied complete):

| Requested phases | Report location / status |
|---|---|
| 1 discovery,2 tenant,3 auth | Sections2,4–6; source and targeted local tests |
| 4 DB,5 N+1,6 pagination | Sections7–8,20; production schema/plans NEEDS TEST |
| 7–11 chat/socket/security/retention/delivery | Sections9–10,19; local mocked integration; live cron unavailable |
| 12–13 API performance/payload | Sections8,20,27; only reduced SQLite experiment; real origin metrics unavailable |
| 14 attendance,15 Kas,16 voting,17 inventory | Sections11–14,21; MySQL races NEEDS TEST |
| 18 upload,19 storage,20 logging,21 errors,22 rates,23 OWASP | Sections18–19,28; deployed execution protection/logs UNKNOWN |
| 24 transactions,25 races | Section21; source + serial suites, no real MySQL concurrency |
| 26–28 Flutter performance/network/storage | Section15; tests/analyzer, no device profile |
| 29–31 Android security/APK/update | Sections16,22; existing APK inspected; no fresh release build/upgrade drill |
| 32 FCM,33 web,34 cache | Sections4,6,17,19,28; provider/server/browser evidence partial |
| 35–40 MySQL/FPM/PM2/Nginx/Cloudflare/SPOFs | Sections3,9,21,23; formulas, reported topology; configs UNKNOWN |
| 41–43 backup/cron/observability | Section28; source gap assessment, runtime unverified |
| 44 dependencies,45 legacy,46 coverage | Sections19,22,28; audits run; no automatic upgrades/deletions |
| 47 dataset,48 load,49 SLO,50 capacity | Sections20,23,27; synthetic SQLite executed; representative load NOT TESTED |
| 51 current server,52 roadmap,53 economics | Sections3,24–26; no unsupported hardware endorsement |
| 54 security,55 performance severity | Sections19–20; explicit finding registers |
| 56 change policy,57 tests,58 Git hygiene | Sections1,22 and closing record below |

Original audit change record: only this canonical report and two narrowly scoped audit-test files were added to the repository. Application/controller/service/config/migration/dependency behavior remained unchanged during that audit. Synthetic dataset script and command logs were placed in the local temporary directory. Initial `git status --short` was clean; `git diff --check` passed initially and after work. The audit ended with three new audit files; no existing `chat-server/tools/` or `website/tools/` content was modified. **No commit, push, deployment, destructive production load test, or production seeding occurred.** The subsequently authorized remediation batch is recorded below.

### Remediation Batch 0 + Batch 1 — worktree only, 2026-10-05

Scope authorization: the user requested test network isolation and SEC-01/SEC-03 repairs, including directly related organization/membership-active authorization. Only three runtime PHP files changed. Flutter production code, Node behavior, legacy Ratchet, inventory, Engine.IO/dependencies, migrations, existing tools and performance/load behavior were left unchanged. No production SSH, deployment, commit, push, migration, seeding or load test occurred. Baseline at batch start already contained the report and two untracked audit test files.

Batch 0 root cause and boundaries:

- Original path: `chat_service_fallback_test → ChatService.sendMessage → sendMessageViaApi → ApiClient.post → http.post → IOClient → HttpClient`. The old override returned `super.createHttpClient`, enabling real network I/O. That override and its global leak were removed.
- [chat_service_fallback_test.dart](mobile/test/chat_service_fallback_test.dart) now injects a `package:http` in-memory `MockClient` using `runWithClient`; it verifies exactly one POST, JSON contents, synthetic 201 response and parsed chat ID. SharedPreferences/socket state is reset and cleaned up. Its production URL is an in-memory mock input, not a transport request; no real client handles that URL.
- [flutter_test_config.dart](mobile/test/flutter_test_config.dart) initializes the Flutter binding, then installs a deny-only global override through test execution/teardown. Test registration runs before test bodies, so the override is restored only in `tearDownAll`. It reports failures into the current test zone immediately and rechecks attempts in teardown, defeating application catch-all handlers.
- [network_guard.dart](mobile/test/support/network_guard.dart) supplies a client that never creates/delegates to a real transport. All HTTP open methods throw before connection. Its proxy-resolution override also blocks Dart's already-cached default WebSocket client's handshake before DNS/connection, covering the Socket.IO default native transport. It denies all hosts, including production, external HTTP and FCM; new tests must use explicit in-memory transports. Counter summaries are emitted per test-file isolate.
- [network_guard_test.dart](mobile/test/network_guard_test.dart) verifies installation, production HTTP, uppercase/trailing-dot host spelling, external HTTP/FCM, cached default WebSocket blocking and failure reporting despite caught exceptions. Six intentional negative control calls use independent deny-only guards: **three production-addressed controls and three other external controls**, all blocked before network I/O in the final run. These are distinguished from unexpected suite requests rather than hidden in the zero counters.
- Other Flutter network-sensitive tests: all six `app_update_test` cases already inject an in-memory BaseClient; example APK URLs are fixture data and are not downloaded. Remaining original tests inspect source, parse models, test streams/deduplication or use mock SharedPreferences. No original test initializes a live Socket.IO connection or native Firebase/FCM service. Neither existing tools directory was executed.
- Backend network-sensitive tests: wheel spin/close calls `WheelController::triggerSocketEvent → Services::curlrequest → NODE_SOCKET_URL/internal/wheel-event`; that URL could otherwise inherit an external environment value. Shared [BaseTest.php](website/tests/_support/BaseTest.php) now rejects a non-testing environment before setup, forces/restores `FCM_MOCK=true`, and injects a test-only cURL mock whose constructor/request transport is never executed. Only the exact HTTP POST loopback `/internal/wheel-event` integration returns an in-memory response; any production or other external URL is rejected and recorded, with a teardown assertion even if a controller catches the error. Notification tests already request FCM mock mode; setup now enforces it for all shared-base tests, preventing OAuth/provider sends. [NetworkIsolationTest.php](website/tests/NetworkIsolationTest.php) proves production/FCM/external rejection, in-memory wheel response and forced FCM test mode: five cases. A focused wheel/notification/network group passed **16 tests,68 assertions,0 failures/errors**. These tests create no live wheel HTTP connection.
- Node network-sensitive tests: both socket test files start only ephemeral localhost servers with mocked MySQL/fetch, so internal auth/notification URLs never reach external HTTP. The environment validation test spawns a child without required server configuration; it exits before DB/server startup. Existing Jest tests were run unchanged; no Node test/setup/runtime refactor was added.

**Failed preliminary boundary check, preserved for honesty:** before the final boundary was correct, one newly added default WebSocket negative control reached production and received HTTP 522. A zone-only override did not survive runner-owned test zones, early restoration occurred after registration, and the cached WebSocket client also required the proxy hook. No successful production mutation was observed. Those harness problems were corrected before final full verification. Therefore “no production request during the entire remediation session” would be false. **Observed production requests during final full verification: 0; external successful HTTP requests: 0.** This evidence consists of the deny-only boundary, its negative tests and all 14 isolate counter summaries, not a packet capture or a general OS firewall assertion.

Batch 1 runtime changes:

- `AbsensiController::status` requires selected tenant/current user and joins the parent event; predicates constrain both `absensi.karang_taruna_id` and `events.karang_taruna_id`. Existing open-today/checkout/current-user semantics remain. An inconsistent imported attendance/event tenant pair cannot pass either direction.
- `OrganizationMemberModel::getEligibleMemberships` is the single rule used by both explicit/token-default selection and implicit selection in AuthFilter: matching global user, active global user, active organization, active membership, **approval_status=approved**, and exact requested membership tenant. It does not silently replace login/discovery/recipient policies elsewhere.
- `AuthFilter` clears stale service state at request entry, rejects malformed/nonpositive/overflow tenant selectors, validates token tenant defaults through the same rule, and assigns role/tenant/tenant username only from an eligible membership. Zero eligible memberships fail closed on tenant APIs. Multiple memberships still require explicit selection for tenant APIs. Membership administration subroutes are no longer mistaken for global discovery through a broad prefix match.
- Exact global profile/me/logout/device/discovery endpoints may still operate without selected tenant when no token default/header selects one, but retain **no legacy tenant or role authority**. Existing denial when a token-default tenant itself is revoked remains unchanged in this batch. The intentionally global superadmin branch remains unchanged and is explicitly regression-tested.
- Compatibility evidence: `CreateOrganizationMembersTable` already backfills legacy user tenant/role into memberships; `AddApprovalStatusToOrganizationMembers` defaults existing membership approval to approved. Missing/ineligible membership is not an evidenced legitimate tenant-access state. No migration or automatic approval/backfill was added. A deployed database missing backfilled memberships will now deny those tenant requests; operators must verify membership data before rollout rather than reenable bypasses.

Before/after evidence:

| Finding | Before remediation | After local remediation |
|---|---|---|
| SEC-01 | Selected 101 status returned the same user's tenant-102 event ID | 200 with foreign event absent; same-tenant open-today event retained; approved A/B selection returns only each selected tenant's ID |
| SEC-03 | Token from approved A + active pending B header returned inventories 200 | Same request 403; active rejected B also 403; approved active A and B can each be selected |
| Related SEC-13 | Inactive organization not checked and legacy user tenant could authorize without membership | Explicit/token-default/implicit inactive organization denied; no/pending/rejected/inactive membership cannot use legacy tenant alone |
| REL-11 | REST fallback test performed real production I/O | Exactly one in-memory mock call; final full suite zero unexpected transport attempts; deliberate negative controls blocked |

The two original SEC-01/SEC-03 characterizations were converted to safe expectations **before changing production PHP**, and both failed against the old code: **2 tests, 3 assertions, 2 failures, 0 errors**. They now pass safe expectations. The unrelated past-event/predictable-password PHP characterizations and the SEC-02 Node characterization still reproduce open defects; they were not converted into false security passes.

Regression evidence: [TenantAuthorizationRegressionTest.php](website/tests/Api/TenantAuthorizationRegressionTest.php) adds **30 cases**. It covers approved/pending/rejected memberships, inactive membership/organization/global user, spoofed nonmember tenant, five malformed selectors, absent token tenant, five invalid legacy states across tenant/membership administration routes, the token-default path, approved-only implicit selection, global me/logout without legacy authority, intended superadmin context, inherited internal socket-auth denial, two approved organizations with attendance only in B, identical event/date-like values in A/B, authorized switching/nonmember denial, both directions of corrupted attendance-parent tenant data, and ordinary current-user/open-today status behavior.

Duplicated eligibility/discovery/recipient checks remaining for later remediation (inspected, **not refactored**):

| Caller | Current relationship to the new rule | Later work |
|---|---|---|
| AuthFilter explicit/token-default/implicit branches | Now reuse `getEligibleMemberships` | Completed in this batch |
| `InternalApiController::socketAuth` / Routes.php internal/socket-auth | Already passes through AuthFilter; inherits new denial, no independent membership resolver | Keep secret/loopback and revocation work separate; SEC-02 still open |
| `AuthController::login` | Independent user/member active and pending/rejected checks; PIN route independently checks organization status | Review direct login organization eligibility and error semantics; no login refactor here |
| `AuthController::login` membership list and `MembershipController::index` | Independently list status-active memberships without identical approval/organization eligibility | Align selectable versus pending-status discovery explicitly in a later batch; listed membership cannot bypass the new filter |
| `NotificationService::getTokensForTenant` | Selects active memberships, omits approval/user/organization eligibility alignment | FCM recipient remediation remains open |
| `ChatController::createRoom`, private/history/send receiver checks and default-group member enumeration | Independent status-active checks; no uniform approved eligibility helper | Align receiver/member policy later; do not infer SEC-02 closure |
| `ChatModel::getPrivateChatContacts` | Joins status-active membership independently | Review contact visibility and eligibility later |
| `InternalApiController::chatNotification` custom room and `ChatController` custom-room token lookup | Uses room members/device owner lookup without complete current eligible organization membership recheck | Remains in SEC-20; no FCM behavioral change here |
| Node `join_room` / group send / private sender+receiver SQL checks | Independent status-active membership SQL; initial/cached PHP auth benefits from filter fix, recipient/receiving checks remain independent | Batch 2 socket security and later recipient/revocation rules; Node source untouched |
| Participant target-member selection | Independent member role/active check | Review approved eligibility with participant route/schema work later |

The above excludes administration queries that intentionally inspect pending/rejected memberships; those must not blindly filter away the very rows an approver needs to see. `getMembership`/`getUserMemberships` remain generic lookup methods, not authorization promises. Authenticated tenant context is now authoritative **in AuthFilter**, not a claim that every recipient/discovery policy across the system is repaired.

Final verification:

| Run | Tests / passes | Assertions | Failures | Errors | Skipped | Other |
|---|---:|---:|---:|---:|---:|---|
| Targeted SEC-01/03 + authorization + backend network regressions | 37 /37 | 131 | 0 | 0 | 0 | 3.782 s; 20 MiB |
| Earlier attendance/tenant/auth/membership/RBAC/superadmin/socket-auth regression group, before shared backend guard | 110 /109 | 609 | 0 | 0 | 1 | 18.242 s; existing remote-IP skip; these cases also run in the final full suite |
| Full PHPUnit | 193 /192 | 1,040 | 0 | 0 | 1 | 21.580 s; 26 MiB; includes final shared backend transport guard |
| Full Flutter tests | 43 /43 | Not emitted | 0 | 0 | 0 | Final output at 4 s; 14 test files |
| Flutter analyze | — | — | — | **0 analysis errors** | — | **16 warnings,158 infos;174 issues; exit1**; unchanged from audit baseline |
| Existing Node Jest | 20 /20,3 suites | Not emitted | 0 | 0 | 0 | 3 s; behavior/source unchanged; SEC-02 characterization still reproduces its defect |
| PHP syntax lint | 7 changed/new PHP files | — | 0 syntax failures | — | — | Three app files, two API tests, shared BaseTest and network test |

Final Flutter transport evidence: **14 guard summaries, all production_attempts=0, transport_attempts=0, external_successful_requests=0** for the suite's unexpected-request counters. The six intentional independent-guard controls described above are separately verified rejections. There is no assertion that a mocked 201/200 was an external successful HTTP response. Full analyze remains nonzero because of pre-existing warnings/infos; no clean analyzer claim is made.

A separate disposable test also verified the complete failure path, not just a stub callback: it used the installed `NoNetworkHttpClient`, attempted a production GET and deliberately caught the error. The test runner still failed immediately with `Unexpected test network request`, exit1, plus teardown rejection. That one deliberate attempted call was **blocked before I/O**, not sent to production. Its log is local TEMP `kartar-batch01-guard-failure-probe.log`; its temporary source was removed before final full verification and is not part of the 43 passing tests or final change set.

Commands: safe red tests, focused/regression/full `php vendor/bin/phpunit --colors=never` with `CI_ENVIRONMENT=testing` and `FCM_MOCK=true`; `flutter.bat test --no-pub --reporter expanded`; `flutter.bat analyze --no-pub`; `npm.cmd test -- --silent`; changed PHP lint; formatting only the four touched Flutter test files. Full-run command logs are in local TEMP (`kartar-batch01-phpunit.log`, `kartar-batch01-flutter-tests.log`, `kartar-batch01-flutter-analyze.log`), not production or tracked application logs. No external dependency upgrade or package resolution was requested/run.

Security registry delta, **worktree only**:

| Severity | Original finding count | Resolved here | Remaining open worktree count |
|---|---:|---|---:|
| Critical | 4 | SEC-01, SEC-03 | **2** |
| High | 7 | None | **7** |
| Medium | 9 | SEC-13, directly related authorization rule with explicit/implicit/legacy regressions | **8** |
| Low | 2 | None | **2** |

REL-11 belongs to the separate reliability registry, so its resolution does not subtract another security finding. SEC-13 is reduced only because both its recorded AuthFilter subissues—organization active status and legacy fallback authority—were repaired and tested within the user's explicitly allowed related authorization path. Remaining receiving socket/FCM/session/discovery defects retain their own open IDs. **No production severity count is reduced by an undeployed worktree fix.** Performance registry remains unchanged.

Final gates:

| Gate | Result |
|---|---|
| Test suite network isolated for reviewed HTTP/WebSocket/native test call paths | PASS |
| Production domain blocked by final test guard | PASS |
| No production network during final full tests | PASS — 0 observed; preliminary failed boundary check disclosed above |
| SEC-01 closed | PASS — RESOLVED IN WORKTREE — NOT DEPLOYED |
| SEC-03 closed | PASS — RESOLVED IN WORKTREE — NOT DEPLOYED |
| Tenant authorization authoritative in AuthFilter | PASS — active user/org/member + approved + matching selector |
| Organization inactive / legacy fallback / spoof / pending / rejected / inactive membership | PASS — BLOCKED on tenant access |
| Cross-tenant attendance metadata | PASS — BLOCKED |
| Full backend regression | PASS — one pre-existing remote-IP skip |
| Flutter regression | PASS tests; analyzer unchanged nonzero with 0 errors,16 warnings,158 infos |
| Node regression | PASS; SEC-02 remains an open reproduced defect |
| Ready for Batch 2 socket security | **YES** |
| Production ready | **NO** |

Batch change record: **12 files touched**, including this existing untracked report and existing untracked PHP audit test; the prior untracked Node audit test was left unchanged. Runtime files: `AbsensiController.php`, `AuthFilter.php`, `OrganizationMemberModel.php`. Flutter test files: `chat_service_fallback_test.dart`, new `flutter_test_config.dart`, new `network_guard_test.dart`, new `support/network_guard.dart`. Backend tests: converted SEC-01/03 expectations in `ProductionReadinessAuditTest.php`, new `TenantAuthorizationRegressionTest.php`, shared `_support/BaseTest.php`, new `NetworkIsolationTest.php`. Git status/name-status/stat/check were reviewed; tracked diff is limited to the three PHP runtime files and the existing Flutter/shared PHP test files. New files are listed separately because Git diff does not include untracked artifacts. Both tools directories, Node runtime/test artifacts from the audit, lockfiles, mobile runtime and migrations were unchanged. **No commit, push or deployment.** The subsequent explicitly authorized Batch 2 is recorded below.

### Remediation Batch 2 — Socket Tenant Isolation — worktree only, 2026-10-05

Scope: the user explicitly authorized SEC-02 private Socket.IO tenant isolation, matching receiver eligibility, Flutter consumption/switch/reconnect isolation and a bounded receiving authorization improvement. Batch 0/1 changes were retained without edits. No SEC-04/07 repair, dependency upgrade, migration, inventory change, queue/pool/rate redesign, Redis/cluster/adapter, load test, production probe, SSH, commit, push or deployment occurred. Original sections 1–28 and the preceding Batch 0/1 ledger remain historical evidence; their open counts and source descriptions refer to those earlier snapshots.

SEC-02 root cause and before/after proof:

- Old private room: **`user_{global_user_id}`**. Every authenticated tenant context for that global identity joined the same room. Both private emits (sender echo and receiver delivery) used it; the same peer IDs in different organizations also passed Flutter's peer-only UI filter.
- New canonical helper: `privateUserRoom(tenantId,userId)` returns **`tenant_{tenant_id}_user_{user_id}`**. Authentication and both private emits reuse it. Authenticated user 5 in tenant 101 joins `tenant_101` and `tenant_101_user_5`, plus its automatic Socket.IO socket-ID room; no global `user_5` room. Custom chat/wheel rooms still require their existing join paths.
- The tenant and sender come from **socket metadata assigned from PHP internal socket-auth**, which passes through the Batch 1 AuthFilter. Client `sender_id`, `tenant_id` and `karang_taruna_id` do not override private INSERT values or emit destinations. Receiver ID is validated within that authenticated tenant. A small in-flight auth guard prevents two concurrent authentication responses from assigning different tenants/rooms to one socket; late authentication after disconnect cannot join rooms.
- **BEFORE:** the original unchanged audit characterization passed, proving tenant-101 sender → tenant-102 socket for the same recipient delivered a payload with tenant 101. The characterization was then changed to require zero cross-tenant deliveries **before** the isolation runtime change. It failed: one received tenant-101 payload versus expected empty array (**1 test,1 failure**).
- **AFTER:** [production-readiness.audit.test.js](chat-server/tests/production-readiness.audit.test.js) requires sender delivery confirmation and **zero** tenant-A events on the tenant-B receiver. It passes. It was converted, not removed.
- Flutter transport injection alone was added to exercise the unchanged handlers without real networking. Safe expectations against the old behavior produced **5 tests:1 pass,4 failures**: wrong-tenant private and group events reached the stream, connected old socket was reused after tenant change, and reconnect sent captured old tenant. Two earlier test-harness constructor errors were corrected before this meaningful red run. No guard boundary was bypassed and no production request occurred in Batch 2.

Receiver eligibility: Node's private sender and receiver each use a **single bound SQL query** joining `organization_members`, `users` and `karang_taruna`. It requires exact user/tenant, active membership, **approved** membership, active global user and active organization. No eligible row means denial before INSERT or notification. Generic administration/pending-list queries are unchanged. This mirrors the PHP ordinary-member rule explicitly; Node does not pretend to invoke the PHP model helper. Source field names match current models/migrations; Node SQL is mocked in these tests, so actual deployed MySQL DDL/plans remain unverified.

Flutter defense and lifecycle:

- [chat_service.dart](mobile/lib/services/chat_service.dart) rejects `new_message` unless authentication is current, payload tenant matches the socket tenant, payload tenant matches the **currently stored active tenant**, and its captured socket/generation remains current after asynchronous storage lookup. Missing/malformed tenant data fails parsing and produces no stream message. This applies to private and group messages before downstream state changes. The chat-list/contact-refresh and chat-room UI listeners also check `isCurrentTenant` before mutation, covering queued stream callbacks after canonical context disposal.
- `switchTenant` is shared by **tenant selector and notification-triggered switching**: clear listeners → disconnect/dispose old socket and reset auth/rooms/context → save tenant state → initialize a fresh Socket.IO manager (`forceNew`) → authenticate new context → join only its authorized rooms. A connected socket is reused only for the identical tenant/token. A disconnected old instance is disposed before replacement. A generation/initialization check prevents stale callbacks or an in-flight initialization from resurrecting a closed/logout context.
- On reconnect, credentials are read from **current storage**, not merely captured during initial setup. An unchanged context reauthenticates and rejoins the current active group. A changed tenant/token disposes that socket and initializes a new manager, clearing old group IDs. Server reconnect creates a fresh socket and authoritative user/tenant rooms. Only the latest active group is restored in an unchanged context, as before; a general multi-room/leave-room redesign is outside scope.
- The optional socket factory is a narrow test seam. The production default is still `IO.io`; [chat_tenant_isolation_test.dart](mobile/test/chat_tenant_isolation_test.dart) uses a real event emitter with an in-memory Socket subclass whose Manager never opens Engine.IO/network transport. No Firebase initialization/provider send is performed. Notification/selector use of the shared switch is source-verified; the shared sequence itself is executed with the in-memory transport. No real notification tap/device UI/Android build is claimed.

Receiving authorization improvement and **SEC-12 remaining gap**:

Each authenticated socket now has a **60-second authorization lease**, renewed through the existing authoritative PHP socket-auth endpoint even if idle. Renewal first removes all application rooms, so an expired lease receives no broadcasts while renewal is pending. One fetch is allowed in flight per socket, with a **5-second AbortController deadline**. Successful renewal must match the same user/tenant and retain `chat.read`, then restores the prior rooms and schedules the next lease. Denial, identity mismatch, permission loss, provider/network failure or timeout disconnects and leaves rooms. Send and join paths share/check the lease, so a concurrent join cannot restore room access during renewal. Disconnect clears the lease timer. There is no database query per received packet.

| Revocation state while connected | Bounded behavior / evidence |
|---|---|
| A membership removed | PHP AuthFilter denies renewal; room eviction/disconnect tested with mocked authoritative denial |
| B membership rejected/inactivated | Same; Batch 1 PHP tests cover actual eligibility denial; Node renewal denial test passes |
| C organization disabled | Same; PHP active-organization condition and Node receiver/send checks enforce eligibility |
| D global user disabled | Same; PHP active-user condition and Node receiver/send checks enforce eligibility |
| E token revoked / logout | PHP token validation denies next renewal; room eviction/disconnect tested; client normal close still disposes its socket |

**Not immediate revocation:** an already authorized idle socket may receive until its next lease expires, nominally up to 60 seconds under a responsive event loop. No hard timing SLA under saturation is asserted. Removal from a **custom room alone**, while organization membership remains eligible, is not rechecked by PHP socket-auth; restoring that custom room can retain the previous subscription. Password reset/change still does not revoke other tokens. No server-driven invalidation hook or durable catch-up was introduced. Temporarily leaving rooms during renewal can miss events under the existing best-effort delivery model; REST history remains the recovery path. These are explicit remaining **SEC-12/REL-08** concerns, so neither finding is closed wholesale. SEC-02 closes the tenant-namespace/context defect independently of comprehensive session revocation.

SQL/authorization cost, SOURCE unless labeled locally measured:

| Operation | Before Batch 2 | After Batch 2 |
|---|---|---|
| Initial socket auth | 0 Node SQL;1 PHP auth HTTP request, approximately3 PHP SQL (token/user/member) | Same statement count; member query already uses Batch 1 eligibility joins |
| Private send, within valid lease | 4 Node SQL:sender membership,receiver membership,INSERT,timestamp SELECT | **4 Node SQL**, locally asserted; first2 queries now include eligibility joins |
| Receiver validation | 1 membership SQL per private send | **1 joined eligibility SQL**, independent of receiving device count |
| Tenant namespace | No SQL | **0 additional SQL** |
| Idle receiving authorization | No periodic request; stale authorization could persist indefinitely | 1 PHP auth request/socket/60s, about3 PHP SQL per renewal; no Node SQL |
| First send/join after expiry or while renewal pending | Sender-only cached auth renewal on send | Shares the one renewal in flight; no second concurrent renewal fetch |
| Default/custom group send | Approximately4/5 Node SQL | Unchanged within valid lease |

Lease cost is proportional to connected sockets, not packet/fan-out count. At S sockets, nominal renewal demand is **S/60 PHP requests/sec** (CALCULATED:100→1.67;1,000→16.67;10,000→166.67), plus reconnect/browsing traffic. It is bounded per socket, **not a global concurrency/backpressure cap**. Synchronized renewals and PHP capacity require later staging measurement. Initial auth fetch/pool wait queue and aggregate socket quotas remain separate open PERF-05/06/SEC-09 items. No capacity improvement/10k support is claimed.

Security matrix and evidence:

| Required behavior | Result |
|---|---|
| Same-tenant private sender/receiver | PASS — delivered |
| Same global identity with A/B recipient and sender sockets | ISOLATED — only A devices receive A; B devices receive0 |
| Multiple valid sender/receiver devices in A | PASS — each receives exactly1 |
| Same peer IDs across two tenants in Flutter | ISOLATED — wrong tenant private/group event produces0 stream messages |
| Spoofed tenant / spoofed sender | BLOCKED — authoritative tenant/sender persisted and emitted |
| Receiver nonmember / pending / rejected / inactive membership / inactive global user | BLOCKED —0 inserts/deliveries |
| Inactive organization | BLOCKED — recipient and sender negative cases |
| Custom/default group | PASS — same-tenant room delivery preserved; outsider receives0 |
| Wheel endpoint / tenant broadcasts | PASS — ephemeral localhost endpoint broadcasts only selected tenant room |
| Switch / reconnect | PASS — fresh context on tenant/token change; old rooms/listeners disposed; same-tenant group rejoin works |
| Idle renewal / timeout / identity or permission mismatch | PASS — production-scheduled callback tested deterministically, suspension and fail-closed eviction |
| Concurrent auth | PASS — cannot join two tenants' private rooms |

Node tests live in [tenant-isolation.test.js](chat-server/tests/tenant-isolation.test.js): **25 cases**, using ephemeral loopback Socket.IO and mocked MySQL/PHP/FCM. The 60-second lease and5-second timeout tests invoke the actual registered timer callbacks deterministically; they do not wait for wall-clock intervals or benchmark timing. Existing integration tests were retained; the global-user-room assertion was updated to its authorized tenant namespace. Flutter adds **13 cases**, including stale queued event, storage changed before reinitialization, same-context reuse, disconnected replacement and logout during initialization. Zero stream delivery means these events cannot drive the existing stream UI/contact-refresh consumers; no instrumented device rendering profile is claimed.

Final verification:

| Run | Tests / passed | Assertions | Failed | Errors | Skipped | Evidence |
|---|---:|---:|---:|---:|---:|---|
| Targeted Node SEC-02 + isolation matrix | **26 /26** | Not emitted | **0** |0 |0 |2 suites;2.241s |
| Full Node Jest | **45 /45** | Not emitted | **0** |0 |0 |4 suites;4.135s |
| Full PHPUnit | **193 /192** | **1,040** | **0** | **0** | **1** |25.067s;26MiB; same existing remote-IP CLI skip |
| Targeted Flutter tenant isolation | **13 /13** | Not emitted | **0** |0 |0 |In-memory socket/event transport |
| Full Flutter tests | **56 /56** | Not emitted | **0** |0 |0 |15 test files; final elapsed9s |
| Flutter analyze |— |— |— | **0 analysis errors** |— |**16 warnings,158 infos;174 issues; exit1**, baseline unchanged |
| Git whitespace check |— |— |0 |— |— |`git diff --check` PASS |

Batch 0 guard remains active and unchanged. Final full Flutter run emits **15 guard summaries**, all **production_attempts=0, transport_attempts=0, external_successful_requests=0** for unexpected requests. Its original independent guard negative controls still deliberately reject attempted HTTP/WebSocket/FCM transport before I/O. Node uses real localhost transport only; all PHP auth/FCM fetches and SQL are mocked. Backend shared cURL/FCM test isolation remains installed. **Unexpected production network attempts in Batch 2:0. External successful network requests:0.** This is boundary/source/test evidence, not packet capture. The historical audit/Batch 0 522 incidents remain documented and are not attributed to this batch.

Commands: `npm.cmd test -- --runInBand --silent` (targeted and full); `php vendor/bin/phpunit --colors=never` with testing/FCM mock; `flutter.bat test --no-pub --reporter expanded` (targeted/full); `flutter.bat analyze --no-pub`; Dart formatting of touched service/test; Git status/name-status/stat/check and scope review. Full logs are local TEMP `kartar-batch2-node.log`, `kartar-batch2-phpunit.log`, `kartar-batch2-flutter-tests.log`, `kartar-batch2-flutter-analyze.log`. No packages were installed/resolved/upgraded. Preliminary harness syntax/type errors were corrected before final passing suites.

Security registry delta, worktree only:

| Severity | Before Batch 2 | Resolved here | After Batch 2 open |
|---|---:|---|---:|
| Critical |2 |SEC-02 | **1: SEC-04** |
| High |7 |None | **7** |
| Medium |8 |None; SEC-12 remains open | **8** |
| Low |2 |None | **2** |

Original/deployed-unknown security baseline remains **4 critical,7 high,9 medium,2 low**. No undeployed repair proves a production registry reduction. SEC-04, SEC-07, SEC-12 remain explicitly open; performance registry unchanged. REL-08 improved only in the tenant switch/socket reuse paths; its FCM duplicate subscription, general wheel/listener/custom-room/replay concerns remain.

| Final gate | Result |
|---|---|
| SEC-02 closed | **PASS — RESOLVED IN WORKTREE — NOT DEPLOYED** |
| Private user room tenant-namespaced |PASS |
| Server private delivery tenant-safe |PASS |
| Flutter wrong-tenant defense |PASS |
| Tenant switch safe, including shared notification switch |PASS — mocked sequence plus source call-site verification |
| Multi-device safe / same user and same peer across tenants |PASS / ISOLATED |
| Full Node regression |PASS |
| Full backend regression |PASS — one existing skip |
| Full Flutter regression |PASS tests; analyzer remains baseline nonzero |
| Test network isolation preserved |PASS |
| Ready for Batch 3 | **YES** |
| Production ready | **NO** |

Batch 2 change record: **11 files touched**. Runtime: `chat-server/server.js`; Flutter `services/chat_service.dart`, `services/notification_service.dart`, `screens/auth/tenant_selector_screen.dart`, `screens/chat/chat_list_screen.dart`, `screens/chat/chat_room_screen.dart`. Tests: converted prior untracked `chat-server/tests/production-readiness.audit.test.js`; updated `chat-server/tests/socket.integration.test.js`; new `chat-server/tests/tenant-isolation.test.js`; new `mobile/test/chat_tenant_isolation_test.dart`. Documentation: this canonical report. The existing Batch 0/1 files remain in worktree unchanged by Batch 2. Both existing tools directories, backend runtime/migrations, Ratchet, inventory, lockfiles, Android signing/config and notification queue behavior were unchanged. Final Git output includes earlier batch changes and untracked artifacts; tracked diff statistics alone exclude the report/new tests. **No commit, push or deployment.** The subsequently authorized Batch3 is recorded below.

### Remediation Batch 3 — Legacy Realtime + Engine.IO Security — worktree only, 2026-10-05

Scope: authorized SEC-04 retirement and SEC-07 targeted repair, plus the requested narrow realtime logging and transport/security regression evidence. Existing Batch0/1/2 behavior remains. No inventory, CSRF, credential/signing, rate/pool/load, FCM queue, pagination, schema, migration or SEC-12 repair. No production commands, SSH, deployment, commit, push or production network probe occurred. The command smoke check below ran only in this local workspace. Historical audit and earlier ledgers remain snapshots; their old source/dependency descriptions and finding counts are preserved rather than silently rewritten.

**SEC-04: LEGACY SERVICE RETIRED.** Before this batch, `App\Libraries\ChatServer::onMessage` accepted client `user_id` and tenant without token validation and echoed the complete incoming message. The only application instantiation was the separate legacy `spark websocket:serve` command, which built `IoServer → HttpServer → WsServer → ChatServer` and could listen on port8081. No mobile client, route or repository startup configuration was found using that stack. Retiring this unused parallel source path is smaller than implementing another authentication stack. Production usage cannot be proved absent from repository evidence.

Legacy/reference inventory (first-party source including hidden project files was searched; Git metadata, generated build/runtime caches and dependency source implementations are classified separately):

| File / surface | Classification and evidence before action | Batch3 outcome |
|---|---|---|
| `website/app/Libraries/ChatServer.php`, `App\Libraries\ChatServer` | **LEGACY**; Ratchet MessageComponentInterface/ConnectionInterface; client-asserted auth, DB insertion, message-body logging | **DELETED**; class cannot be loaded |
| `website/app/Commands/WebsocketServe.php`, `App\Commands\WebsocketServe`, `spark websocket:serve` | **LEGACY**; only first-party handler caller; imports IoServer/HttpServer/WsServer; default `--port=8081` | Replaced with a **fail-closed retirement notice**; no port option/server construction or listener; returns EXIT_ERROR(1) |
| `website/composer.json` | **LEGACY dependency** `cboden/ratchet:^0.4.4` | Removed after reference inspection |
| `website/composer.lock` | **LEGACY dependency history** Ratchet0.4.4/RFC6455 plus React/Evenement/Symfony-only dependency subtree |13 packages removed; retained package versions AND metadata unchanged |
| Local ignored `website/vendor/cboden/ratchet`, `vendor/ratchet/rfc6455`, React/Evenement/Symfony subtree and `vendor/composer` autoload/installed metadata | **LEGACY dependency implementations / generated local artifacts**; not separate application callers/startup declarations | Composer removed unused packages and regenerated optimized autoload; InstalledVersions confirms Ratchet/RFC6455 absent; no hand-edited vendor code |
| `KARTAR_PRODUCTION_READINESS_AUDIT.md` | **LEGACY historical documentation**; architecture, module/CLI discovery, SEC-04/Composer snapshot, priority/legacy recommendations and earlier batch open counts | Historical evidence preserved; registry annotated and this ledger added |
| `website/README.md`, `website/tests/README.md`, `mobile/README.md`, iOS LaunchImage README | No Ratchet launch instructions found; generic application/framework/testing docs | Website README now documents supported Node service and retired CLI; other docs unchanged |
| `website/app/Config/Routes.php`, other PHP controllers/config/commands, root scripts and migrations | No Ratchet route, alternate ChatServer caller, IoServer/WsServer/WampServer launcher or legacy websocket port config found | Unchanged; no migrations/history removed |
| `website/app/Config/App.php`/Cors comments and root `test_auth.php`,`test_form.php`,`test_request.php` | **ACTIVE API config / legacy diagnostics**, HTTP localhost8080 examples, not Ratchet/WebSocket connection references | Unchanged; diagnostic scripts not executed |
| `mobile/lib/services/chat_service.dart`, `mobile/lib/core/config/api_config.dart` | **ACTIVE**, Socket.IO library, opaque auth, HTTPS socket origin and `/socket.io/` path; no raw Ratchet protocol/8081 reference | Unchanged |
| `mobile/pubspec.yaml` and `mobile/pubspec.lock` | **ACTIVE** Flutter Socket.IO client; installed client3.1.6 | Unchanged |
| `mobile/test/network_guard_test.dart` | **TEST-ONLY** `wss://.../socket.io/` negative guard probe; deliberately blocked before I/O | Unchanged; no Ratchet client |
| `chat-server/server.js`, `chat-server/package.json` start/main, existing socket/tenant/env tests | **ACTIVE** Node Socket.IO runtime / **TEST-ONLY** ephemeral loopback fixtures and Node startup validation | Runtime transport architecture unchanged; errors log fixed labels, dependency patch below |
| `website/app/Controllers/Api/InternalApiController.php`, `WheelController.php` | **ACTIVE** PHP socket-auth/chat-notification integration and Node `/internal/wheel-event`; commented localhost8080 examples are HTTP PHP API references | Unchanged |
| PM2/ecosystem, systemd, Supervisor, Docker/Compose, Nginx, cron, launch shell/PowerShell/batch configs | **UNKNOWN production / no repository artifact found launching Ratchet**. Reported PM2 `kartar-socket` is not a verified deployed config | No startup/deployed service edits; no server access |
| Hidden environment files/templates | Key/path search returned no Ratchet/raw WS URL/8081 setting; secret values were neither printed nor changed | Unchanged |
| New `website/tests/LegacyRealtimeRetirementTest.php` | **TEST-ONLY** supported CLI dispatcher, absent handler and dependency checks |3 tests/8 assertions pass |
| New `chat-server/tests/transport-security.test.js` | **TEST-ONLY** polling/upgrade/message/log/dependency tests |6 cases pass; no external application I/O |

No first-party WampServer use was found. WebSocket mentions in framework/vendor implementations or generic CSP capability comments are not evidence of an application Ratchet launch; iOS `8081...` identifiers are project IDs, not port configuration. No other application use of the removed React/Evenement/Symfony namespaces was found. Package subtree definitions were inspected via the Composer lock and source reference search, not interpreted as independent services.

Current intended runtime trace: **Flutter ChatService → ApiConfig.socketBaseUrl (HTTPS origin) → `socket_io_client`, path `/socket.io/`, polling/WebSocket Socket.IO protocol → Node `chat-server/server.js` → authenticated PHP internal API/MySQL**. PHP REST fallback and PHP wheel HTTP emission are separate from Ratchet. Production routing remains unverified; this trace is source plus localhost protocol-test evidence, not a live deployed connection.

Runtime caller found: **YES historically, only the retired legacy command; NO remaining handler caller**. Active mobile/application-client Ratchet path: **NO FOUND**. Repository startup reference launching Ratchet: **NO FOUND**. Production exposure: **NO ACTIVE APP/STARTUP REPOSITORY REFERENCE — PRODUCTION RUNTIME STILL UNVERIFIED**. This does not erase the previously executable legacy CLI path or establish that an already-running deployed process has stopped. Before rollout, an operator must review external startup units and running processes; no operator action was performed here.

Retirement evidence:

- The handler was removed, rather than copying it to another runnable PHP file. There is no runtime opt-in switch that reenables unauthenticated identity handling.
- The retained command name is a deliberate tombstone for old startup scripts: it prints `Legacy websocket:serve is retired. Start chat-server/server.js for Socket.IO.` and exits **1**, including when a former `--port 8081` option is supplied. It neither imports Ratchet nor opens a server. Registry dispatch is tested, not just matched against source.
- `LegacyRealtimeRetirementTest` verifies the handler file/class are absent, actual command dispatch returns EXIT_ERROR with the retirement guidance, and declared/installed Ratchet and RFC6455 are absent. A separate **local** `CI_ENVIRONMENT=development php spark websocket:serve --port 8081` smoke check confirms prompt exit1. No listener startup was attempted against the old insecure implementation.
- `composer remove cboden/ratchet --no-interaction --no-scripts --minimal-changes` removed **13 packages**, with **0 installs,0 version updates**: `cboden/ratchet`, `ratchet/rfc6455`, `evenement/evenement`, `react/cache`, `react/dns`, `react/event-loop`, `react/promise`, `react/socket`, `react/stream`, `symfony/http-foundation`, `symfony/polyfill-mbstring`, `symfony/polyfill-php83`, `symfony/routing`. Retained package entry comparison confirms no metadata changes. `composer validate --strict --no-check-publish` passes; locked audit reports **0 advisories,0 abandoned packages**.

Message-body logging: **REMOVED** with the PHP handler. The Node service previously passed raw driver/error objects or untrusted error messages to four error logs; those now emit fixed labels, preventing SQL/parameter/provider error contents from exposing bodies or credentials. A forced driver failure containing a synthetic sensitive sentinel returns the existing generic client error and logs no sentinel. Existing Node logs retain connection/user/tenant/room/event IDs and HTTP status codes, without chat bodies, Bearer/FCM tokens, passwords or secret values. This is narrow realtime logging work required by PartA5, not a general logging redesign or SEC-16 closure: Flutter FCM token logging, PHP notification logs and other diagnostic/API concerns remain outside scope and open.

**SEC-07 targeted dependency repair:** before `npm ls socket.io engine.io` showed **Socket.IO4.8.3 → Engine.IO6.6.9**. The [maintainer advisory GHSA-2gc4-cqfq-p2gv](https://github.com/socketio/socket.io/security/advisories/GHSA-2gc4-cqfq-p2gv), verified this batch, identifies affected Engine.IO `>=6.6.0,<6.6.10` and patched6.6.10 for the transport-upgrade protocol mismatch. No exploit or malicious heartbeat was sent.

`chat-server/package.json` now has a scoped override `socket.io → engine.io:6.6.10`, retaining Socket.IO's existing major/minor and all other direct/dev requirements. `npm install --ignore-scripts --no-audit --no-fund` changed **one installed package**. Lock comparison against the saved batch-start lock shows **only `node_modules/engine.io` changed**: version6.6.9→6.6.10, registry URL/integrity and the patched package's own removed `base64id` dependency edge. The retained base64id package and every unrelated package entry are unchanged. No `npm update`, bulk refresh, audit-fix, Socket.IO migration or dev dependency repair was run.

After installation, `npm ls` shows **Socket.IO4.8.3 → Engine.IO6.6.10 overridden**. Installed/locked server Engine.IO version checks pass; no server Engine.IO<6.6.10 remains locally. Engine.IO client is a distinct package and was not upgraded. Existing production-installed/deployed versions are still UNKNOWN.

| npm audit evidence | Before | After |
|---|---|---|
| Runtime Engine.IO |6.6.9;1 high family |6.6.10; **0 runtime vulnerabilities** via `npm audit --omit=dev --json` |
| Dev-only brace-expansion |5.0.9 and2.1.4;1 high family across multiple advisories | **UNCHANGED**,1 high family; both paths descend from Jest |
| Full `npm audit --json` |2 high vulnerable package families | **1 high**,0 critical/moderate/low/info; dev-only brace-expansion |
| Application security registry interpretation |SEC-07 high;SEC-22 low production exposure |SEC-07 resolved in worktree;SEC-22 remains open unchanged |

Do not turn several brace advisories/installed versions into multiple application findings; its canonical SEC-22 remains one dev-only package-family finding. This audit result is a dated advisory scan, not proof against undisclosed vulnerabilities.

Socket protocol/security regression: the new suite verifies a real **HTTP polling Engine.IOv4 handshake** advertises WebSocket upgrade, then runs equivalent authenticated private/group/wheel behavior through **polling-only**, **polling→WebSocket upgrade**, and **WebSocket-only** transports against an ephemeral localhost listener. It asserts the actual initial/final Engine.IO transport and upgrade event, private multi-device tenant namespaces, same-user cross-tenant exclusion and spoof-resistant payloads. The existing Batch2 wheel HTTP endpoint test remains in the targeted/full runs; the new modes additionally exercise wheel room delivery. Database/internal PHP/FCM calls are mocked. All Batch2 tenant/sender/receiver/switch/reconnect/lease tests pass unchanged. No transport upgrade workaround was enabled; normal polling/upgrade compatibility remains available.

Final verification:

| Run | Tests / passed | Assertions | Failures | Errors | Skipped | Evidence |
|---|---:|---:|---:|---:|---:|---|
| Targeted Node transport/security + Batch2 isolation + SEC-02 regression | **32 /32** |Not emitted | **0** |0 |0 |3 suites;4.435s |
| Full Node Jest | **51 /51** |Not emitted | **0** |0 |0 |5 suites;5.578s |
| Targeted backend retirement | **3 /3** | **8** | **0** | **0** |0 |0.069s;12MiB |
| Full backend PHPUnit | **196 /195** | **1,048** | **0** | **0** | **1** |20.703s;26MiB; same remote-IP CLI skip |
| Full Flutter tests | **56 /56** |Not emitted | **0** |0 |0 |15 test files; final elapsed4s |
| Flutter analyze |— |— |— | **0 analysis errors** |— | **16 warnings,158 infos;174 issues; exit1**, baseline unchanged |
| PHP lint / Node syntax |2 changed/new PHP files plus new JS suite |— |0 |0 |— |PASS |
| Git diff whitespace |— |— |0 |— |— |`git diff --check` PASS |

The first backend probe was inadvertently run while Composer was still generating the optimized autoloader; it failed before test execution due to a removed dependency's stale autoload entry. A testing-environment Spark probe separately lacked PHPUnit's SUPPORTPATH bootstrap. Neither is counted as a final test result or retirement proof. After Composer completed, targeted/full PHPUnit passed and the local development CLI check returned the intended retirement exit1. No application workaround was introduced for those probe setup errors.

Network isolation: Batch0 Flutter guard and backend cURL/FCM mocks remain unchanged. Final Flutter run has **15 guard summaries, all production_attempts=0, transport_attempts=0, external_successful_requests=0** for unexpected suite requests. Its intentional deny-only negative controls remain separately classified as blocked before I/O. Node tests use loopback only with mocked fetch/MySQL; the new raw polling handshake also targets the ephemeral loopback server. **Unexpected production attempts during Batch3 application tests:0. Successful external application/test requests:0.** Package registry/advisory downloads/scans and the official advisory lookup were authorized dependency/research network access, outside these application-test counters. No claim of zero external access throughout all dependency work, packet capture or production server verification is made.

Full-run logs are local TEMP `kartar-batch3-node.log`, `kartar-batch3-phpunit.log`, `kartar-batch3-flutter-tests.log`, `kartar-batch3-flutter-analyze.log`. Before/after/runtime npm JSON and Composer audit/lock/hash snapshots are also local TEMP. Commands were targeted/full Jest, targeted/full PHPUnit with testing/FCM mock, Flutter test/analyze without package resolution, npm ls/audit/install, targeted Composer remove/validate/audit, local Spark retirement smoke check, syntax and Git scope checks. No existing diagnostic/tools script was executed.

Security registry delta, **worktree only**:

| Severity | Before Batch3 open | Resolved here | After Batch3 open |
|---|---:|---|---:|
| Critical |1 |SEC-04, legacy retired | **0** |
| High |7 |SEC-07, runtime Engine.IO patched | **6** |
| Medium |8 |None;SEC-12/16 remain open | **8** |
| Low |2 |None;SEC-22 unchanged | **2** |

Original/deployed-unknown baseline remains **4 critical,7 high,9 medium,2 low**. No deployment has occurred. Inventory atomicity, CSRF/default credentials, signing, schema/restore, FCM fan-out, rate/backpressure and concurrency readiness remain unresolved. Zero critical findings in the current worktree is not a production sign-off; capacity and deployment exposure are still unverified. Performance registry unchanged.

| Final gate | Result |
|---|---|
| SEC-04 closed | **PASS — RESOLVED IN WORKTREE — NOT DEPLOYED; LEGACY SERVICE RETIRED** |
| Legacy insecure realtime cannot accidentally start through repository command/class/dependency path | **PASS** — disabled CLI exit1, handler/dependency absent |
| SEC-07 closed | **PASS — RESOLVED IN WORKTREE — NOT DEPLOYED** |
| Engine.IO>=6.6.10 / vulnerable runtime version gone locally | **PASS**,6.6.10 |
| Socket.IO compatibility / polling handshake / WebSocket upgrade | **PASS**,Socket.IO4.8.3 unchanged |
| Batch2 tenant isolation / private multi-device / group / wheel / authorization lease | **PASS** |
| Full Node regression | **PASS** |
| Full backend regression | **PASS**,one existing skip |
| Full Flutter regression | **PASS tests**; analyzer unchanged nonzero baseline |
| Test network isolation preserved | **PASS** |
| Critical security findings remaining in worktree | **0** |
| Ready for next remediation batch | **YES** |
| Production ready | **NO** |

Batch3 change record: **11 paths touched**, including one deletion and two new test files. `website/app/Libraries/ChatServer.php` deleted; `website/app/Commands/WebsocketServe.php` replaced with retirement stub; `website/composer.json`/`composer.lock` remove unused Ratchet subtree; `website/README.md` documents retirement; new `website/tests/LegacyRealtimeRetirementTest.php`; `chat-server/package.json`/`package-lock.json` patch only Engine.IO; `chat-server/server.js` changes only four unsafe error-log arguments relative to Batch2; new `chat-server/tests/transport-security.test.js`; this canonical audit updated. A batch-start hash inventory distinguishes these changes from earlier uncommitted remediation. Existing Batch0/1/2 tests/mobile/backend authorization files, both tools directories, migrations and unrelated dependencies remain unchanged in this batch. Git status/name-status/stat/check and lock entry comparisons were reviewed; cumulative Git output includes prior batches and untracked files. **No commit, push or deployment. Await review before additional remediation.**


### Remediation Batch 4 — Inventory Atomicity + MySQL Schema Parity — worktree only, 2026-10-05

Scope: explicitly authorized SEC-08 transaction/ownership/notification repair, REL-01 migration-chain analysis and forward alignment, plus the request's extremely narrow REL-03 participant route/phone exception. Work started from the clean local checkpoint **ad4ff37** (`fix(security): harden tenant isolation and realtime access`). Its failed push was not retried or treated as a prerequisite. The checkpoint was not recreated/amended. No other finding, queue, pool, rate, pagination, signing, credential or CSRF repair occurred. No production probe, SSH, production migration, seeding, deployment, commit or push occurred. Historical audit and earlier ledgers remain snapshots.

**SEC-08: PARTIAL. REL-01: OPEN / NOT PROVEN. REL-03: RESOLVED IN WORKTREE — NOT DEPLOYED.** Source changes and serial SQLite regressions do not establish MySQL row-lock correctness or deployed schema parity. Open security counts remain **0 critical, 6 high, 8 medium, 2 low**. Production remains **NOT READY**.

Inventory state machine and preserved semantics:

| Request / existing state | Stock effect | Loan effect | Notification | Authorization / result |
|---|---|---|---|---|
| pending → approved | Subtract quantity if sufficient | approved | Existing borrower notification after commit | inventory.approve + selected tenant owns inventory |
| pending → rejected | None | rejected | After commit | Same gate |
| approved → returned | Add quantity, bounded by total | returned | After commit | Same gate |
| approved → rejected | Add quantity, bounded by total | rejected | After commit | Same gate; intentional cancellation preserved |
| approved/returned/rejected → identical status | None | None | None | Ownership first; existing idempotent success |
| Other transition to approved/returned/rejected | None | None | None | 409 conflict |
| Target pending/unknown | None | None | None | Existing 422 target validation |
| Foreign/missing loan, including same status | None | None | None | 404 before successful state disclosure |

Only inventory.approve roles can change status; ordinary borrower independent-return permission was not redesigned. Pending borrowing still does not reserve stock. Existing successful response text for changed and unchanged requests is preserved.

[InventoryLoanTransitionService.php](website/app/Services/InventoryLoanTransitionService.php) starts a real transaction before reads, observes the scoped source state, then locks **loan → inventory** consistently. The loan uses an inventory tenant ownership predicate; the inventory lock repeats the selected tenant constraint. MySQL uses FOR UPDATE; SQLite does not pretend to implement that lock. State/quantity/stock are reevaluated under locks. Quantity must be positive and existing/resulting stock must stay within zero and total. Conditional stock and loan writes require success and exactly one affected row, then transaction status and commit are checked. Uncommitted paths roll back. An outer transaction is refused because a nested CI transaction cannot promise a durable commit before notification. Same-status read-only transactions release locks through rollback, without writes/notification.

If two competing decisions both observed pending, a different locked state yields409 rather than reinterpreting a losing rejection as cancellation. A later request that first observes approved may still intentionally reject it. There is no client expected-status protocol or guarantee that arbitrarily timed requests share a source snapshot. The MySQL opposing-decision fixture synchronizes their initial observations to exercise this boundary; no application sleep/retry was added.

[InventoryController.php](website/app/Controllers/Api/InventoryController.php) calls the existing borrower notification only after the service successfully commits a changed result. A narrow protected notification seam observes committed state and transaction depth0 in tests. No notification is invoked on unchanged/rollback. This verifies local ordering, not actual FCM delivery/durable retries; synchronous fan-out remains. Domain failures preserve404/409/422; DB failures return generic500 and log a fixed label without raw SQL/driver details.

Before/after proof: three safe foreign same-status cases were added **before** changing runtime PHP. They failed against the old controller: **3 tests,6 assertions,3 failures,0 errors**, old200 versus required404. They now pass with foreign stock unchanged. [InventoryAtomicityTest.php](website/tests/Api/InventoryAtomicityTest.php) has **18 serial cases**: those ownership negatives, all four transitions, three owned repeats, last-stock sequential approvals, sequential duplicate return, insufficient/invalid transition, corrupt restock upper bound, forced second-write failure, zero-affected-row stock/loan failures and nested-transaction refusal. Actual SQLite triggers abort/ignore writes; tests execute the controller/service, not a mocked transition. Rollback preserves both records and sends no notification. The postcommit probe checks depth0 and newly stored status/stock. **Serial tests are not concurrency proof.**

Local MySQL runtime and limits:

| Check | Result |
|---|---|
| Local MySQL 8 available | **NO** |
| Existing XAMPP engine | **MariaDB10.4.32**, not MySQL8; not used as substitute |
| Local TCP3306 listener | None found |
| Docker Linux engine | Unavailable: dockerDesktopLinuxEngine named pipe absent |
| Actual MySQL concurrency | **0 passed,0 failed,4 NOT RUN** |
| Fresh full MySQL migration | **NOT RUN** |
| Upgrade-only-new MySQL migration | **NOT RUN** |
| Actual SHOW CREATE TABLE / SHOW INDEX | **NOT RUN**; no measured MySQL schema/index evidence |
| Production schema/runtime | **UNVERIFIED**, no connection made |

The opt-in path is documented in [tests/MySQL/README.md](website/tests/MySQL/README.md). [DisposableMySQL.php](website/tests/_support/DisposableMySQL.php) requires testing environment, explicit opt-in/port/user and fixed loopback host; it rejects MariaDB/non-8 versions. It generates a new random `kartar_batch4_test_<16 hex>` schema with plain CREATE, never adopts an existing database, and drops only its own successfully created schema. It does not inherit application credentials or print/pass credentials as process arguments. Four guard unit cases run without connecting. A disabled worker exits before bootstrap/DB startup.

[InventorySchemaMySQLTest.php](website/tests/MySQL/InventorySchemaMySQLTest.php) contains six MySQL-only cases: two approvals against stock1, two returns, opposing pending decisions, a forced second-write MySQL trigger failure, full fresh migrations, and synthetic upgrade row preservation/equivalent four-table DDL. Independent PHP/MySQL workers invoke the actual transition service; the observation barrier/deadline is test-only. Inventory/loans must use InnoDB. Intended TEMP JSON captures statuses/stock/responses/version and SHOW CREATE/INDEX. Historical migrations run only to construct a newly empty baseline; after seeding, the upgrade applies **only the new migration**. The fixture is linted and its boundary guards tested, but **actual MySQL execution remains unverified**. Skipped cases produced no race/schema proof.

Migration timeline, **SOURCE**: migrations creating/altering/populating the four target tables. Other migrations referencing users as a foreign parent do not modify these target columns/keys and remain unchanged.

| Migration | Fields / keys / data | Driver distinction / later change |
|---|---|---|
| 2026-08-21-205028 CreateUsersTable | Unsigned auto id PK; required full name; nullable nickname; username100 initially unique; password255; nullable no_whatsapp20; role/status; timestamps | Global username key replaced later on MySQL |
| 2026-08-22-070334 CreateEventParticipantsTable | Unsigned id PK; event/user IDs; unique(event,user); cascade FKs; created_at | Tenant added later |
| 2026-08-22-082101 AddPasswordMustChangeToUsers | password_must_change default0 | SQLite down skips drop |
| 2026-08-27-112401 CreateSettingsTable | setting_key100 PK; TEXT value; description255; timestamps; default setting | Key-only PK later diverges from MySQL |
| 2026-08-27-180000 AddRtToUsersTable | rt default1 | SQLite down skips drop |
| 2026-08-27-183000 ModifyRolesEnum | users six-role ENUM default anggota | MySQL-only ALTER |
| 2026-09-01-054552 AddTenantIdToAllTables | Required unsigned tenant; MySQL tenant FKs; users unique(username,tenant) replaces global key | **Historical TRUNCATE of eight tables**; never replay on real data. SQLite skips FK/unique replacement |
| 2026-09-02-120000 UpdateUsersEnumForSQLiteTest | PRAGMA ignore_check_constraints | SQLite testing only |
| 2026-09-02-130000 CreateOrganizationMembersTable | Unsigned id PK, user/tenant; nine-role enum, active/joined/timestamps; unique(user,tenant); legacy membership backfill | MySQL cascade user/tenant FKs; SQLite skips FKs; empty legacy tenants skipped in backfill |
| 2026-09-03-064118 AddUsernameToOrganizationMembers | Nullable username100; backfill; unique(tenant,username) | MySQL JOIN versus SQLite correlated update |
| 2026-09-03-065633 FixSQLiteUsersUniqueConstraint | Users rebuild with nullable legacy tenant and unique(username,tenant) | **SQLite testing only**; no original MySQL nullable-user counterpart |
| 2026-09-03-072554 AddProfilePhotoToUsers | Nullable profile_photo255 | SQLite down skips drop |
| 2026-09-04-060017 UpdateOrganizationMembersRoles | Eleven-role enum including deputy secretary/treasurer | MySQL-only ALTER |
| 2026-09-04-062000 FixSettingsTableFk | Remove settings tenant FK to allow global0 | MySQL-only; SQLite had no such FK |
| 2026-09-07-024618 AddApprovalStatusToOrganizationMembers | pending/approved/rejected enum default approved | Existing memberships inherit approved default |
| 2026-09-07-032712 InsertSuperadminDummyUser | Dummy tenant/user0 | SQLite testing only |
| 2026-09-09-063655 FixSettingsTablePrimaryKey | Signed auto INT id PK; unique(key,tenant) | MySQL-only; SQLite retained key-only PK |
| 2026-09-16-065925 CreateAndroidUpdateSettings | Global0 metadata settings | Data insert; no DDL |
| **2026-10-05-000001 AlignGlobalIdentityAndSettingsSchema** | **New:** nullable MySQL legacy user tenant; SQLite settings id PK + unique(key,tenant) | Forward-only; historical migrations unchanged |

Final expected schema, **SOURCE / MySQL NOT PROVEN**:

| Table | Relevant expected fields / keys | Current write/authority rule |
|---|---|---|
| users | Unsigned auto id PK; legacy tenant unsigned INT **NULL DEFAULT NULL**; existing fk_users_tenant and unique(username,legacy tenant) retained; other fields preserved | Global identity inserts omit legacy tenant; membership supplies organization/role |
| organization_members | Unsigned id PK; required user/tenant; unique(user,tenant); unique(tenant,nullable username); MySQL cascade user/tenant FKs; eleven-role enum; approval enum default approved | Batch1 active approved membership + active user/organization remains authoritative |
| event_participants | Unsigned id PK; required unsigned event/user/tenant; unique(event,user); existing cascade FKs | Insert tenant from authenticated scoped event context, ignoring body override |
| settings | MySQL signed auto INT id PK; key100/value/description/timestamps; required unsigned tenant; unique(key,tenant); no tenant FK | Global0 and multiple tenants can share a key; id updates and exact key/tenant lookup |

The nullable users legacy tenant does not invent global username uniqueness: the existing (username,tenant) index stays, with membership usernames for tenant login. No membership authority or login policy is reverted. SQLite now matches application settings identity/uniqueness, but its types/enums/FKs/locks still do not prove MySQL equivalence.

New migration: [2026-10-05-000001_AlignGlobalIdentityAndSettingsSchema.php](website/app/Database/Migrations/2026-10-05-000001_AlignGlobalIdentityAndSettingsSchema.php).

- **MySQL up:** relax users legacy tenant to unsigned INT NULL/defaultNULL, preserving rows/values/FK/index definitions. No membership backfill or automatic approval. MySQL settings already has the intended September9 PK/key and remains unchanged. DDL may implicitly commit/take metadata locks; successful ALTER execution remains unproven.
- **SQLite up:** users already permits NULL through historical test rebuild. Checked transactional create → copy every original value/timestamp → **DROP old settings table** → rename → commit introduces id/key-tenant uniqueness. This is a lossless table rebuild, not a claim of zero DROP DDL. No truncation/delete/filter/value rewrite. An invalid-copy test proves rollback retains original rows/table and removes the temporary replacement. Already-aligned settings only ensures its unique index. IDs are newly generated because the old schema had none.
- **Preconditions:** expected prior migrations/column types, valid nonnull settings tenants and no surrounding SQLite transaction. Before rollout, verify DDL/FKs/indexes and pending migration history on a restored copy. Legacy values and memberships remain; no tenant backfill is required. Never blindly replay pending destructive historical migrations on real data.
- **down:** deliberately refuses lossy rollback. New global users cannot safely become tenant-required, and repeated global/tenant keys cannot fit the old key-only PK. Use an independently verified backup/restore or reviewed forward correction; do not invent tenant values or delete rows.

[SchemaParityRegressionTest.php](website/tests/Api/SchemaParityRegressionTest.php) has six cases: actual create/register without legacy tenant; settings id/global0/repeated tenant keys; old-schema lossless alignment; invalid-copy rollback; down refusal; and participant insert/list tenant/phone correctness. REL-03 qualifies under the explicitly allowed extremely narrow/isolated exception: GET routes to the existing `index`, and `no_whatsapp as whatsapp` preserves the response field. POST supplies the schema-required selected tenant; spoofed body tenant/foreign member do not create foreign participation and a foreign viewer is denied. No participant approval/role redesign occurred.

Attendance/tenant tests now use the real migrated settings table instead of dropping/recreating adhoc fixtures. Two existing inventory setups reset only the test transaction flag left dirty by suppressed historical SQLite DDL failures. Shared Batch0 BaseTest/cURL/FCM/network guards and production transaction behavior are not bypassed.

Final verification:

| Run | Tests / passed | Assertions | Failures | Errors | Skipped | Evidence |
|---|---:|---:|---:|---:|---:|---|
| Targeted inventory/schema/existing inventory/guard/MySQL | **41 /35** | **175** | **0** | **0** | **6** |2.850s;18MiB; six MySQL-only skips |
| Full PHPUnit | **230 /223** | **1,186** | **0** | **0** | **7** |21.311s;28MiB; six new MySQL skips + existing remote-IP CLI skip |
| Full Node Jest | **51 /51** |Not emitted | **0** |0 |0 |5 suites;4.705s; source/tests unchanged |
| Full Flutter tests | **56 /56** |Not emitted | **0** |0 |0 |15 files; final elapsed4s |
| Flutter analyze |— |— |— | **0 analysis errors** |— |**16 warnings,158 infos;174 issues; exit1**, baseline unchanged |
| PHP syntax |16 changed/new PHP files |— |0 syntax failures |0 |— |PASS |
| MySQL concurrency |0 /0 |— |0 |— |4 NOT RUN |No measured concurrency proof |
| MySQL fresh/upgrade schema |0 /0 |— |0 |— |2 NOT RUN |No measured DDL/index proof |
| Git whitespace |— |— |0 |— |— |git diff --check PASS |

Preliminary test setup errors (framework seed-method naming collision, missing password confirmation, protected-property assignment, fixture property collision and incorrect lint path) were corrected before final passing checks. They are not application defects or final-suite results. Wider upgrade-row/DDL evidence in the skipped fixture was linted; it does not count as executed MySQL verification.

Network isolation: **15 Flutter guard summaries**, all unexpected **production_attempts=0, transport_attempts=0, external_successful_requests=0**. Existing deliberate negative controls still block before I/O. Backend cURL/FCM mocks remain; Node uses ephemeral loopback with mocked PHP/FCM/SQL. **Unexpected production application/test attempts:0. External successful application/test requests:0.** No real Firebase delivery, production HTTP or dependency download occurred in Batch4. This is source/guard/test evidence, not packet capture.

Commands: targeted/full PHPUnit with testing/FCM mock; full Jest; Flutter test/analyze without package resolution; PHP lint; disabled worker/guard tests; local MySQL/listener/Docker availability checks; Git status/name-status/stat/check. TEMP logs: `kartar-batch4-targeted.log`, `kartar-batch4-phpunit.log`, `kartar-batch4-node.log`, `kartar-batch4-flutter-tests.log`, `kartar-batch4-flutter-analyze.log`. Historical migrations were executed only on testing in-memory/disposable SQLite fixtures. Existing tools scripts were not executed.

| Security severity | Before Batch4 | Resolved here | After Batch4 open |
|---|---:|---|---:|
| Critical |0 |None | **0** |
| High |6 |None; SEC-08 partial pending MySQL proof | **6** |
| Medium |8 |None | **8** |
| Low |2 |None | **2** |

REL-03 is a separate reliability finding, so its local resolution does not subtract a security finding. REL-01 stays open. Other findings and the performance registry remain unchanged. Original/deployed-unknown security baseline remains4/7/9/2.

| Final gate | Result |
|---|---|
| SEC-08 inventory atomicity | **NOT PROVEN end-to-end; PARTIAL IN WORKTREE** |
| Tenant ownership before idempotent response | **PASS**, foreign same-status404 |
| Transaction / lock order | **ADDED**, loan then inventory; MySQL execution unverified |
| Transaction rollback / second-write failure | **PASS locally**; MySQL counterpart NOT RUN |
| Notification after commit | **PASS locally**, committed state/depth0; none on rollback |
| Last-stock approval / duplicate return / opposing-decision races | **NOT PROVEN on MySQL**; serial tests pass |
| MySQL concurrency | **NOT PROVEN**, four cases NOT RUN |
| Fresh MySQL migration | **NOT PROVEN**, NOT RUN |
| Upgrade MySQL migration | **NOT PROVEN**, NOT RUN |
| MySQL schema parity / REL-01 | **PARTIAL source repair; OPEN / NOT PROVEN** |
| Production schema verified | **NO** |
| REL-03 | **RESOLVED IN WORKTREE — NOT DEPLOYED** |
| Full backend | **PASS**, skips disclosed |
| Full Node | **PASS** |
| Full Flutter | **PASS tests / 0 analyzer errors**; baseline warnings/infos remain |
| Test network isolation | **PASS** |
| Ready for next batch | **NO — await MySQL verification and review** |
| Production ready | **NO** |

Batch4 change record: **18 paths touched**. Runtime: `website/app/Controllers/Api/InventoryController.php`; new `app/Services/InventoryLoanTransitionService.php` and `InventoryTransitionException.php`; `app/Controllers/Api/ParticipantController.php`; `app/Config/Routes.php`; new `app/Database/Migrations/2026-10-05-000001_AlignGlobalIdentityAndSettingsSchema.php`. Tests: new `tests/Api/InventoryAtomicityTest.php`, `SchemaParityRegressionTest.php`, `tests/DisposableMySQLGuardTest.php`, `tests/MySQL/InventorySchemaMySQLTest.php`, `tests/MySQL/README.md`, `tests/_support/DisposableMySQL.php`, `mysql_inventory_worker.php`; existing `tests/Api/InventoryIntegrityTest.php`, `InventoryLoanTest.php`, `AbsensiTest.php`, `TenantIsolationTest.php`. Documentation: this canonical audit, preserving historical sections with registry annotations.

Scope review: eight tracked modifications including the report, ten new files; tracked diff statistics exclude new files. Both tools directories, historical migrations, dependency manifests/locks, Node/mobile source, Batch0 transport guards, Batch1 authorization runtime and Batch2/3 realtime behavior remain untouched. Git status/name-status/stat/check reviewed. Local HEAD remains **ad4ff37**; nothing staged. **No commit, push or deployment. Await review.** MySQL8 execution and later sanitized production DDL remain required before sign-off.

### Remediation Batch 4.1 — Real MySQL 8 Concurrency + Schema Verification — 2026-10-05, Asia/Bangkok

**MEASURED: SEC-08 RESOLVED IN WORKTREE — NOT DEPLOYED. REL-01 RESOLVED LOCALLY — PRODUCTION SCHEMA STILL UNVERIFIED. REL-03 remains RESOLVED IN WORKTREE — NOT DEPLOYED.** This ledger supersedes Batch 4's local MySQL NOT PROVEN status. Production remains **NOT READY**. No Batch 5 work, production connection, production migration, SSH, commit, push or deployment occurred. Existing Batch 4 production code and the forward migration were reused without redesign.

Runtime discovery checked PATH, Windows services, Program Files/ProgramData/MySQL Installer locations, portable/application/download/TEMP directories, Laragon and XAMPP separately. No MySQL service or usable MySQL 8 binary was found. Laragon's `mysql-8.0.30-winx64` directory contained only `my.ini`; its existing data was not adopted or changed. XAMPP's binary identified itself as **10.4.32-MariaDB** and was excluded from verification. Docker's Linux engine was unavailable.

An isolated [Oracle MySQL Community ZIP](https://dev.mysql.com/downloads/mysql/8.0.html) was downloaded over HTTPS, following the [noinstall ZIP instructions](https://dev.mysql.com/doc/refman/8.0/en/windows-install-archive.html). Published/downloaded MD5 matched `003f527d5df61b663ff191038cd676bd`. Only the new TEMP directory was initialized; no Windows service, system installation, PATH change, firewall rule or existing MySQL/MariaDB configuration was changed. MySQL X Protocol was disabled. `SELECT VERSION(), @@bind_address, @@port` returned **8.0.46 / 127.0.0.1 / 3308**, and netstat confirmed the listener bound only to loopback. A newly generated local test credential was used, with schema permissions restricted to the disposable namespace. Credentials are absent from test evidence and this report.

| MySQL runtime | Result |
|---|---|
| MySQL 8 available for execution | **YES**, portable runtime; stopped after verification |
| Exact version | **8.0.46**, MySQL Community Server GPL |
| Host / port | **127.0.0.1 / 3308** |
| MariaDB used | **NO** |
| Existing application database adopted | **NO** |
| Production database/host accessed | **NO** |

The existing harness was audited before execution. `DisposableMySQL` now requires both `ENVIRONMENT` and `CI_ENVIRONMENT` to be testing, plus explicit enable/port/user. Its host is hardcoded to 127.0.0.1, without application credentials/DSN fallback. It rejects non-8 versions and MariaDB before CREATE. Random `kartar_batch4_test_<16 hex>` names use plain CREATE DATABASE, never IF NOT EXISTS; an existing schema is never adopted. DROP is limited to its validated schema after successful creation. The actual worker is `website/tests/_support/mysql_inventory_worker.php`, not the pasted `tests/mysql_inventory_worker.php` path. Its independent version gate now explicitly rejects MariaDB too.

Narrow test-only completions: record numeric MySQL errors; exercise forced rollback through the real controller with an in-memory notification probe; reuse two existing application cases via a guarded child PHPUnit bootstrap; verify three-tenant settings coexistence and both InnoDB engines; capture before/after upgrade rows and DDL; compare DDL while ignoring constraint/index names and AUTO_INCREMENT counters; add a foreign-event POST negative assertion. No retries or sleeps were added to production code. Existing read barriers remain test orchestration only.

Initial attempts are not final proof: temporary-server localhost account setup first failed, trigger creation initially lacked binary-log permission, and the old participant NOT NULL expectation failed against actual DDL. Only this new TEMP server received `log_bin_trust_function_creators=1`; no unrelated server setting changed. A diagnostic listener initially called a Query error accessor without a populated error and was corrected to read connection error codes during DBQuery. The final six-case run below was clean. Initial logs remain preserved where generated.

| MySQL-only case | Result | Actual evidence |
|---|---|---|
| Approval race | **PASS** | Stock1, two loans/connections; HTTP-equivalent codes409/200; one approved, one pending; final stock0 |
| Duplicate return race | **PASS** | Codes200/200; exactly one changed=true; final returned; final stock1, restored once |
| Approve vs reject | **PASS** | Codes409/200; one decision; final rejected, stock1; no partial stock mutation |
| Forced rollback | **PASS** | Real controller500; trigger error1644; loan still pending, stock still1; notifications empty |
| Entire fresh migration chain | **PASS** | Newly empty schema; complete current App chain; four SHOW CREATE TABLE and SHOW INDEX results preserved |
| Upgrade only new forward migration | **PASS** | Second disposable synthetic legacy schema; seeded rows preserved; final relevant DDL equivalent to separately migrated fresh schema |
| MySQL cases executed / passed / failed / errors / skipped | **6 / 6 / 0 / 0 / 0** | **99 assertions**, 42.712s, 12MiB |
| Additional existing application cases on MySQL | **2 / 2 PASS** | Real create/register and participant FeatureTest paths; **19 assertions**, no skips |

Final race evidence recorded **no lock/deadlock error codes**. The only intentional database error was trigger SIGNAL **1644**. Lock/deadlock codes would be preserved by the same worker diagnostic path; no broad automatic retries hide a failed transition. This run does not claim to have induced a deadlock.

| Real resulting schema / write behavior | Verified result |
|---|---|
| users.karang_taruna_id | Unsigned INT, **nullable/default NULL**; legacy tenant FK and unique(username,tenant) remain. Actual API create/register omit the column, insert successfully and leave it NULL |
| organization_members | Unsigned id PK; required user/tenant; unique(user,tenant), unique(tenant,username); cascade user/tenant FKs; eleven-role ENUM and approval ENUM remain; create membership approved and registration membership pending in tenant101 |
| event_participants | Unsigned id PK; required event/user; unique(event,user); event/user/tenant cascade FKs. Legacy tenant column is **nullable/default NULL in actual MySQL**, but the application always supplied authenticated tenant101 |
| Participant tenant boundaries | Same-tenant member inserted; spoofed body tenant102 ignored; foreign member excluded; foreign viewer403; foreign-event POST403 with zero inserted foreign rows |
| settings | Signed auto INT id PK; unique(setting_key,karang_taruna_id); legacy tenant column unsigned INT nullable/default NULL; no tenant FK. Same key with tenant0/101/102 coexists as three distinct rows/ids |
| inventories engine | **InnoDB** |
| inventory_loans engine | **InnoDB** |
| Fresh / upgrade relevant schema equivalent | **YES**, semantic names normalized; columns, keys and FK definitions retained |
| Existing users/memberships/participants/settings rows preserved | **YES**, exact row-array equality; non-user relevant DDL unchanged |

**Correction to earlier SOURCE assumptions:** historical `AddTenantIdToAllTables` omits an explicit `null` option. In installed CodeIgniter Forge, CREATE TABLE defaults missing nullability to NOT NULL, but ADD COLUMN leaves it unspecified; real MySQL therefore creates these legacy tenant columns as nullable. The earlier assertion that migrated MySQL users/participants/settings necessarily had required tenants was inaccurate. Historical ledgers preserve that assumption as a snapshot; it is not asserted as current measured DDL. The stricter previously deployed users NOT NULL scenario is explicitly synthesized only in the empty baseline fixture, before seeding; its forward migration demonstrably changes Null=NO to YES/default NULL while preserving values and FK/index semantics. This proves the forward repair also handles that stricter deployment, without claiming production actually has it. No participant/settings nullability hardening or historical migration edit was introduced.

The settings proof covers intended global0 and explicit tenant contexts. It does not claim NULL-tenant uniqueness or new global username uniqueness; MySQL nullable composite unique keys retain their existing semantics. Membership remains the application authority.

Evidence retained outside the repository at:

`C:\Users\lenovo\AppData\Local\Temp\kartar-batch41-2e356db4f25e415a9f8f752acd96d3c4`

Core artifacts: `mysql-final.log`, `mysql-final.xml`, `phpunit-full.log/xml`, `jest-full.log/json`, `flutter-full.log`, `flutter-analyze.log`, `runtime-cleanup.txt`, and `evidence/` synthetic JSON/application logs/XML. Final inventory snapshots: `kartar_batch4_barrier_8223e59c6dc49579.json` (approval), `kartar_batch4_barrier_7d1c86e576ecb1a8.json` (return), `kartar_batch4_barrier_48a4496c7543c6fe.json` (opposing decisions). Fresh summary: `kartar_batch4_test_2579b8de9109a7ad_fresh.json`; upgrade rows/DDL: `kartar_batch4_test_08efca4f8b55024a_upgrade.json`. Four-table raw SHOW CREATE/INDEX captures and forced-rollback JSON are preserved alongside them. Synthetic rows only; no real credentials or production data are embedded.

| Regression after successful MySQL proof | Tests / passed | Assertions | Failures | Errors | Skipped |
|---|---:|---:|---:|---:|---:|
| Full backend PHPUnit | **230 / 223** | **1,188** | **0** | **0** | **7** |
| Full Node Jest | **51 / 51**, 5 suites | Not emitted | **0** | **0** | **0** |
| Full Flutter test | **56 / 56** | Not emitted | **0** | **0** | **0** |
| Flutter analyze | 174 existing issues | — | — | **0 errors** | — |

Backend skips are six intentionally opt-in MySQL cases plus the existing remote-IP CLI case; these do not replace the separate successful MySQL run. PHPUnit25.715s/28MiB; Jest6.372s; Flutter7s. Flutter analyze has **16 warnings, 158 infos**, unchanged from Batch 4, and exits1; it is **not** an issue-free analyzer run. Commands retained existing isolation: testing/FCM mock for backend, mocked Node SQL/PHP/FCM and loopback fixtures, Flutter `--no-pub` tests/analyze. **15 Flutter guard summaries** report production_attempts=0, transport_attempts=0, external_successful_requests=0. Backend request guards passed. **Unexpected production application/test requests: 0.** The Oracle runtime download was intentional setup network I/O, not a production application request; no real Firebase delivery occurred. This remains guard/test evidence, not packet capture.

| Security registry | Before Batch 4.1 | After Batch 4.1 |
|---|---:|---:|
| Critical | **0** | **0** |
| High | **6** | **5** |
| Medium | **8** | **8** |
| Low | **2** | **2** |

Only SEC-08 closed in this security count. REL-01/REL-03 belong to the separate reliability registry. No unrelated finding was reduced.

Cleanup: harness-owned databases were dropped after each case. Before shutdown, SHOW DATABASES contained only MySQL system schemas; no disposable schema remained. The TEMP server received a graceful mysqladmin shutdown; its log records **Shutdown complete** and only the existing XAMPP mysqld process remained. **Automatic approval review rejected recursive removal of the TEMP runtime files with the stated reason “blocked by policy”; runtime/archive/data files were retained, with the server stopped.** No attempt was made to bypass that rejection. Evidence is preserved. XAMPP MariaDB, Laragon existing data and Windows services were not changed.

| Final gate | Result |
|---|---|
| REAL MYSQL 8 USED | **PASS** |
| MYSQL CONCURRENCY | **PASS** |
| FRESH MYSQL MIGRATION | **PASS** |
| UPGRADE MYSQL MIGRATION | **PASS** |
| SCHEMA PARITY | **PASS locally**, measured nullability correction disclosed |
| SEC-08 CLOSED | **PASS — RESOLVED IN WORKTREE, NOT DEPLOYED** |
| REL-01 LOCALLY PROVEN | **PASS — PRODUCTION SCHEMA STILL UNVERIFIED** |
| REL-03 | **RESOLVED IN WORKTREE — NOT DEPLOYED** |
| FULL REGRESSION | **PASS tests / zero analyzer errors**; existing analyzer warnings/infos remain |
| git diff --check | **PASS** |
| READY FOR NEXT REMEDIATION BATCH | **YES, subject to review; Batch 5 not started** |
| PRODUCTION READY | **NO** |

Git hygiene: status/name-status/stat/check reviewed; nothing staged, HEAD still **ad4ff37**. Worktree includes the already-present Batch 4 changes; Batch 4.1 adds only test/evidence/documentation completions, including `tests/_support/mysql_application_bootstrap.php`. Nineteen total changed/new paths across Batch 4/4.1; tracked diff statistics exclude new files. Historical migrations, forward migration, application runtime, dependency manifests/locks, both tools directories and Node/mobile source were not modified by Batch 4.1. **No commit. No push. No deployment. Await review.**

### Remediation Batch 5 — Web CSRF + Credential Hardening — 2026-10-05, Asia/Bangkok

**MEASURED: SEC-05 and SEC-06 RESOLVED IN WORKTREE — NOT DEPLOYED.** Security registry **0 critical / 3 high / 8 medium / 2 low**. Only these two HIGH findings close here. SEC-12 remains open: reset/change does not revoke other bearer tokens, and existing browser sessions/virtual superadmin tokens are not retroactively invalidated. SEC-09/10/11/14, the wider SEC-16 logging finding, reliability/performance registries and capacity claims are unchanged. Production remains **NOT READY**. Base HEAD is the reviewed local Batch 4/4.1 checkpoint **ceb64cb**, parent **ad4ff37**; neither commit was modified. Batch 5 is uncommitted, unstaged and awaiting review. No production connection, migration, SSH, commit, push, deployment, MySQL runtime restart or dependency resolution occurred.

The complete canonical audit was read before remediation. `Routes.php` and every `Superadmin` controller were inspected; auto-routing stays disabled. All session-authenticated management lives under `superadmin`; `/` and login are browser login surfaces. The mobile/API routes use bearer authentication, not browser session authority. Event/cash web administration is read-only; no web event/cash mutation route exists. The event-create modal is an existing unimplemented placeholder, with a token added to its form; it does not establish a working event-create endpoint.

Exact browser route/controller inventory follows. Controller names are relative to `App\Controllers\Superadmin`; `(:num)` captures are passed in order. Before: **20 GET routes (12 read-only, 8 unsafe) + 8 POST routes**. After: **12 read-only GET + 16 POST**, **zero GET business/auth mutations**. CSRF hash generation on GET is normal session security bookkeeping.

| Method / exact route after remediation | Controller / method | Classification / prior method |
|---|---|---|
| GET `/` | AuthController::login | READ-ONLY GET |
| GET `superadmin/login` | AuthController::login | READ-ONLY GET |
| POST `superadmin/login` | AuthController::processLogin | MUTATION POST, unchanged method |
| POST `superadmin/logout` | AuthController::logout | MUTATION POST, formerly unsafe GET |
| GET `superadmin/dashboard` | DashboardController::index | READ-ONLY GET |
| GET `superadmin/settings` | SettingController::index | READ-ONLY GET |
| POST `superadmin/settings` | SettingController::update | MUTATION POST, unchanged method |
| GET `superadmin/kelurahan` | KelurahanController::index | READ-ONLY GET |
| POST `superadmin/kelurahan/store` | KelurahanController::store | MUTATION POST, unchanged method |
| POST `superadmin/kelurahan/update/(:num)` | KelurahanController::update/$1 | MUTATION POST, unchanged method |
| POST `superadmin/kelurahan/delete/(:num)` | KelurahanController::delete/$1 | MUTATION POST, formerly unsafe GET |
| GET `superadmin/karang_taruna` | KarangTarunaController::index | READ-ONLY GET |
| POST `superadmin/karang_taruna/create` | KarangTarunaController::create | MUTATION POST, unchanged method |
| POST `superadmin/karang_taruna/update/(:num)` | KarangTarunaController::update/$1 | MUTATION POST, unchanged method |
| POST `superadmin/karang_taruna/delete/(:num)` | KarangTarunaController::delete/$1 | MUTATION POST, formerly unsafe GET |
| GET `superadmin/karang_taruna/(:num)/users` | KarangTarunaController::users/$1 | READ-ONLY GET, legacy route retained |
| GET `superadmin/manage/(:num)` | ManageController::dashboard/$1 | READ-ONLY GET |
| GET `superadmin/manage/(:num)/users` | ManageController::users/$1 | READ-ONLY GET |
| POST `superadmin/manage/(:num)/users/(:num)/role` | ManageController::updateUserRole/$1/$2 | MUTATION POST, unchanged method |
| POST `superadmin/manage/(:num)/users/(:num)/status` | ManageController::toggleUserStatus/$1/$2 | MUTATION POST, formerly unsafe GET |
| POST `superadmin/manage/(:num)/users/(:num)/reset-password` | ManageController::resetPassword/$1/$2 | MUTATION POST, formerly unsafe GET |
| POST `superadmin/manage/(:num)/users/approve/(:num)` | ManageController::approveUser/$1/$2 | MUTATION POST, formerly unsafe GET |
| POST `superadmin/manage/(:num)/users/reject/(:num)` | ManageController::rejectUser/$1/$2 | MUTATION POST, formerly unsafe GET |
| GET `superadmin/manage/(:num)/events` | ManageController::events/$1 | READ-ONLY GET |
| GET `superadmin/manage/(:num)/pengumuman` | ManageController::pengumuman/$1 | READ-ONLY GET |
| POST `superadmin/manage/(:num)/pengumuman/create` | ManageController::createPengumuman/$1 | MUTATION POST, unchanged method; existing schema defect below |
| POST `superadmin/manage/(:num)/pengumuman/delete/(:num)` | ManageController::deletePengumuman/$1/$2 | MUTATION POST, formerly unsafe GET |
| GET `superadmin/manage/(:num)/kas` | ManageController::kas/$1 | READ-ONLY GET |

Framework CSRF is enabled through URI-scoped before filters for `/`, `superadmin` and `superadmin/*`, using CodeIgniter **session** protection and its normal token name/header/hash regeneration. No bearer/internal/public-version CSRF requirement was introduced. Existing SuperadminFilter still supplies management authorization. POST login is public but credential-checked; logout only destroys the calling browser session. Missing/incorrect tokens throw403 in testing (production retains framework redirect-on-failure behavior); either path stops the controller before mutation. Valid tokens from another session fail. Authorized header tokens succeed and rotate. Normal, inline and modal forms carry `csrf_field()`; confirmation JavaScript submits their POST forms through `requestSubmit()`. No separate token system or browser AJAX mutation transport was added. Login regenerates the session ID; logout explicitly clears its auth fields before the existing session destroy.

**Red tests first:** before runtime edits, the existing password characterization was changed to safe expectations and a cross-site GET status test was added. Old code produced **2 tests / 5 assertions / 2 failures / 0 errors**: username authenticated the newly created account, and a request with an attacker Origin/Referer changed membership status1→0. `red.log/xml` retain this evidence. The same safe expectations now pass. The expanded GET tests verify exact row-array equality across seven relevant tables and preserve the browser auth flag, in addition to observing404; this is not merely a missing-route assertion.

| Credential flow | Before | After / delivery / session semantics |
|---|---|---|
| API manager `POST api/users` | Password=username; flag0; client password input ignored | 18 cryptographic random bytes → 24 URL-safe characters (**144 bits**), bcrypt immediately, flag1; `data.username` and `data.temporary_password` delivered only in successful201, `Cache-Control: no-store`; Flutter create dialog displays them separately and no longer asks for an ignored password |
| Membership creation/approval | Links global identity; no separate credential | Remains a link/approval of existing identity; does not generate or expose a password |
| Public registration | User-selected password, minimum6 | User-selected private password under new policy; bcrypt; flag0 because already personally chosen; membership still pending; no password in response |
| API admin reset | Reused global plaintext setting | Fresh independent random credential, bcrypt, flag1, successful response only/no-store; existing permission/tenant/global-identity checks retained |
| Web superadmin reset | Reused global setting, plaintext in success flash/session | Fresh random credential, bcrypt, flag1; direct no-store HTML response shows username/password once; no flash/session storage; subsequent list does not expose it |
| Self change | Minimum6; cleared flag; no other-token revocation | Validates new private password and confirmation, refuses same current credential, hashes before checked write, clears flag only on success; existing token semantics retained |
| Historical privileged migration | Fixed usable superadmin credential | Historical migration unchanged; both browser and API login call the same runtime policy which rejects the known historical seeded/shared constants and password=username outside testing |
| UserSeeder demo admin/pengelola | Callable outside testing with fixed defaults | Guard throws before any write unless ENVIRONMENT=testing; real testing seeder remains usable; fixed hashes still cannot authenticate through production policy |
| Tenant creation | Organization + default chat room, no user password | Unchanged; no tenant bootstrap privileged credential is issued |
| Dummy/system and test fixtures | Empty-password dummy user; synthetic test passwords | Historical dummy migration already testing+SQLite-only, empty hash not authenticatable; AuthTrait/test defaults remain testing fixtures. Diagnostic tools were not run or modified |

Temporary credentials are **displayed once per successful creation/reset response**, not invitation tokens or server-enforced one-use passwords. They may authenticate until the user changes them; the account is restricted during that time. Losing the response requires another authorized reset, which replaces the credential. No plaintext temporary credential is persisted. Legacy `temporary_reset_password` settings rows are left unchanged as historical data, ignored by both reset paths and excluded from the web settings display; the web settings handler no longer accepts/writes that field. This does not claim old plaintext settings were erased from an existing database. Invitations/reset links and a secure removal of retired settings data are future work.

**Password policy:** minimum **12 Unicode characters**, maximum **72 UTF-8 bytes** (bcrypt limit), no required uppercase/symbol composition. New registration/change rejects username and known default values; change also rejects the current password. Flutter validates the same length/byte/default rules. Existing ordinary short passwords still authenticate. The specific legacy defaults and username passwords are intentionally denied outside testing, regardless of account privilege: such identities require a private credential reset before rollout. Strong preconfigured superadmin hashes continue to authenticate. This is the chosen minimal forward-safe mitigation; **no historical migration edit or new production migration**. Operators must provision a private privileged credential before enabling these changes; no bootstrap secret was requested, fetched, logged or embedded. Existing sessions/tokens are not retroactively disabled (SEC-12).

AuthFilter now compares **exact normalized paths and methods**, fixing the previous substring match that inadvertently allowed `api/memberships` via `api/me`. Flag1 permits only GET `api/me`, POST/PATCH `api/profile/password`, and POST `api/logout`, subject to existing identity/membership checks. Normal tenant, membership discovery, profile edits and FCM registration are blocked. Change clears flag0 and normal access resumes with the existing token; this explicitly demonstrates that other-token revocation has not been added.

Touched server credential paths do not log passwords, request bodies, sessions or tokens. The route-scoped `CredentialSafeToolbar` replaces the toolbar alias and prevents its raw POST/header/session/view-variable collection on browser session pages and the touched API credential/me/logout paths, even with CI_DEBUG enabled. Tests prove these paths never reach toolbar preparation/storage, while unrelated paths retain normal toolbar behavior. Tests capture **all logger calls/context** while creating, resetting, logging in and changing passwords, and scan every testing DB table plus session and subsequent lists for the issued secrets. Flutter debug request/response/error/exception logging emits only method/status/general failure for the touched credential URLs, including malformed non-JSON failures. This closes the temporary-response leak introduced by adding credential delivery; it does not close the wider SEC-16 finding. No real secret appears in the ledger/evidence; all inputs are synthetic.

**Existing limitation discovered during the web matrix:** `ManageController::createPengumuman` supplies `penulis_id`, while the migrated `pengumuman` table requires `dibuat_oleh`. A valid authorized CSRF request reaches the controller but fails that NOT NULL write. The test records this existing separate schema defect and unchanged rows; it does **not** claim successful announcement creation. The other fifteen POST actions complete their intended authorized behavior. No announcement author/schema fix was introduced. The legacy `karang_taruna/(:num)/users` route also still refers to its existing missing view; it is classified read-only, not claimed as a working rendered page. These limitations do not authorize unsafe GET or missing-token writes.

| Mandatory matrix | Evidence / result |
|---|---|
| 1,5 GET / cross-site state preservation | Eight former GET URLs404; exact before/after rows and browser auth state preserved; original cross-site status case also passes |
| 2 POST without CSRF | All16 actions blocked before mutation, including login/logout; attacker Origin simulated |
| 3 Valid token + authorized | All16 reach intended controller;15 successful actions, existing announcement schema failure preserved/disclosed |
| 4 Valid token + unauthorized | Management denied, row snapshots unchanged; public login still requires correct credentials |
| 6 Bearer/internal/public regressions | API create/update without browser token pass; public app-version passes; existing internal socket/auth/guard cases pass in full backend |
| 7–10 Manager creation | Password differs from username,24 characters, distinct equivalent account credential, bcrypt matches issued value; actual login succeeds, flag1 |
| 11–14 Forced change | Tenant/membership/profile/FCM blocked; me allowed; valid private change200; hash verified/flag0; inventory/me access200 afterward |
| 15–17 Reset | Fresh value differs from username/old/shared value; repeat reset replaces previous hash; flag1; API and web reset paths proven |
| 18 No persisted/logged plaintext | Every disposable test table/session/logger context scanned; subsequent web/API user list has no credential; Flutter debug log tests exclude synthetic passwords/tokens |
| 19 Production default unavailable | Actual isolated child PHP defines ENVIRONMENT=production: default denied, private credential accepted; no app bootstrap, .env, DB or network |
| 20 Test-only seeding | Production child invokes actual UserSeeder and guard rejects before writes; real testing seeder works; test hashes denied under production policy |

New backend cases: `WebCsrfCredentialTest` **60** and `CredentialHardeningTest` **16**, plus the converted SEC-06 audit expectation and existing identity/settings tests updated for random credentials and real CSRF. The browser form regression renders nine existing pages and checks each form for its token and absence of mutation links. Standalone production-policy probe lives in `tests/_support/production_credential_probe.php`. Eight new Flutter cases cover credential logging and Unicode/bcrypt policy boundaries. Initial fixture/API mistakes (announcement field/timestamp, test response accessor, seeder accessor, lint relative paths) were corrected before the final results; the pre-existing announcement NOT NULL defect was preserved explicitly rather than hidden by a successful-write claim.

| Final verification | Tests / passed | Assertions | Failures | Errors | Skipped |
|---|---:|---:|---:|---:|---:|
| Targeted backend, credential/web + existing auth/tenant/schema/settings guards | **126 /126** | **791** | **0** | **0** | **0** |
| Full backend PHPUnit | **306 /299** | **1,766** | **0** | **0** | **7** |
| Full Node Jest | **51 /51**,5 suites | Not emitted | **0** | **0** | **0** |
| Full Flutter | **64 /64** | Not emitted | **0** | **0** | **0** |
| Flutter analyze |168 existing issues | — | — | **0** | — |
| PHP syntax |28 changed/new PHP files | — | **0** | **0** | — |
| Final rendered-form check after whitespace review |1 /1 |47 |0 |0 |0 |

Full backend elapsed33.543s/30MiB; targeted18.526s/24MiB; Jest4.979s; Flutter7s. The seven backend skips remain six opt-in MySQL cases plus the existing remote-IP CLI case. Batch4.1 real MySQL evidence remains canonical; no MySQL runtime recreation was needed for this batch. Analyzer exits1 with **16 warnings /152 infos /0 errors**; six infos disappeared as a consequence of replacing old password validators, no unrelated analyzer cleanup. This is not an issue-free analyzer run.

Commands retained testing/FCM mocks and the existing request guards, Jest mocked SQL/PHP/FCM and loopback fixtures, Flutter `--no-pub` tests/analyze. **17 Flutter guard summaries** all report **production_attempts=0 / transport_attempts=0 / external_successful_requests=0**. Backend logger probes do not replace its cURL transport guard. Node full suite passes the existing network isolation cases. **Unexpected production application/test requests:0.** No Firebase delivery or setup download occurred. This is guard evidence, not packet capture.

Evidence outside repository:
`C:\Users\lenovo\AppData\Local\Temp\kartar-batch5-7ca55df64dce413d8b199ddb5276bcea`

Artifacts: `red.log/xml`, `targeted.log/xml`, `backend.log/xml`, `node.log/json`, `flutter.log`, `analyze.log`, `mobile-targeted.log`, `forms-final.log`, initial-attempt logs, classified `scope-manifest.txt` and complete reviewed `final.diff`. Tests/log probes use only synthetic inputs; issued random credentials are checked in memory and not printed into evidence. TEMP evidence/scripts are outside Git scope.

| Security registry | Before Batch5 | After Batch5 |
|---|---:|---:|
| Critical |0 |**0** |
| High |5 |**3** |
| Medium |8 |**8** |
| Low |2 |**2** |

Git scope: browser config/routes/forms, credential policy/API/web/seeder/forced-change guard, narrow mobile credential UX/policy/logging, corresponding tests and canonical audit only. Both `website/tools` and `chat-server/tools`, historical/forward migrations, inventory runtime, dependency manifests/locks and Node source are unchanged. Nothing staged; HEAD remains **ceb64cb**. Status/name-status/stat/full diff reviewed and `git diff --check` **PASS**. No commit, push or deployment.

| Final gate | Result |
|---|---|
| SEC-05 CLOSED / NO GET MUTATIONS / SESSION CSRF |**PASS — RESOLVED IN WORKTREE, NOT DEPLOYED** |
| BEARER API REGRESSION |**PASS** |
| SEC-06 CLOSED / NO PREDICTABLE CREATED PASSWORD |**PASS — RESOLVED IN WORKTREE, NOT DEPLOYED** |
| FORCED PASSWORD CHANGE / DEFAULT PRIVILEGED CREDENTIAL SAFE |**PASS locally**, private privileged provisioning required before rollout; existing sessions/tokens remain SEC-12 |
| FULL BACKEND / FULL NODE / FULL FLUTTER |**PASS**; skips and analyzer warnings/infos disclosed |
| TEST NETWORK ISOLATION / git diff --check |**PASS** |
| READY FOR NEXT BATCH |**YES, subject to review**; existing announcement/legacy-view limitation retained |
| PRODUCTION READY / DEPLOYED |**NO / NO** |
| COMMIT / PUSH |**NOT ATTEMPTED / NOT ATTEMPTED** |

### Overnight Remediation — Local Checkpoints and Continuing Verification — 2026-10-05, Asia/Bangkok

This ledger supersedes earlier current-state snapshots without erasing them. Start HEAD **ceb64cb**. All work is local; no production connection/database, SSH, production credentials, deployment or push. Remaining phases continue below. Production remains **NOT READY**.

**Phase 0 — PASS, local checkpoint cd32308 (`fix(web): harden csrf and credential flows`).** The exact reviewed 40-path Batch 5 scope was staged explicitly after manifest hashes, full staged diff and cached whitespace review. Fresh full PHPUnit: **306 tests / 1,766 assertions / 0 failures / 0 errors / 7 skips**; Node **51 passed / 0 failed**; Flutter **64 passed / 0 failed**; analyzer **0 errors / 16 warnings / 152 infos**, exit1. Worktree was clean after commit. SEC-05/06 remain resolved locally, not deployed. Prior Batch 4.1 MySQL evidence remains canonical; runtime not restarted for this checkpoint.

**Phase 1 — SEC-09 RESOLVED IN WORKTREE — NOT DEPLOYED.** Current open security registry: **0 critical / 2 high / 8 medium / 2 low**. Existing login5/60, PIN10/60 and per-socket5/second remain. Browser login now receives login5/60 too. Authenticated API mutations use atomic file-lock user and framework-derived IP windows; categories span resource IDs/methods and bearer devices: password5/minute, admin reset10, attendance20, vote20, upload10, chat/general60. Registration5/IP/minute. IP ceilings are max30 or four times the user ceiling; registration retains5. Browser management also has IP limits. Storage failure returns503; quota429 carries Retry-After60. No forwarded client IP or token is stored in quota files (keys SHA256). Testing opt-in namespaces apply only under ENVIRONMENT=testing and prevent fixture interference; production does not honor this exception.

JSON mutation bodies are capped at1MiB; existing validated upload size/type caps remain. Participants max100 raw IDs, chat-room members max100 raw entries before deduplication, voting2-50 nonempty labels of at most255 characters, wheel max1000 validated candidates / duration10-120 seconds, including the implicit-all-member query (fetch1001 then reject rather than truncate). No permission/tenant checks were removed.

Node retains the per-socket message quota and adds a shared global-user5/second window, max5 sockets per global user across tenants, connect120/IP/minute, auth60/IP/minute, at most5 initial auth calls per IP and50 overall. Authentication has a5-second AbortController deadline and cancels on disconnect; duplicate auth is single-flight. Room/wheel joins share30/user/minute, one in-flight join per socket and max50 extra rooms. Socket IDs are typed/bounded before SQL, tokens are bounded512 characters, REST internal JSON64KiB, pool waiting queue100. Limiter key map is TTL-pruned with a20,000-key fail-closed ceiling. These are single-instance safeguards, not distributed limits or capacity evidence. Expired file-bucket housekeeping is addressed in the later retention phase.

Initial red characterization found three failed expectations (oversized wheel accepted; repeated raw room/voting arrays unbounded); a lowercase test-method setup error was corrected. Final full backend **318 tests / 311 passed / 1,944 assertions / 0 failures / 0 errors / 7 skips** (48.581s,30MiB). Full Node **56 passed / 0 failed**,6 suites (5.601s). The seven backend skips remain six opt-in MySQL plus existing remote-IP CLI. Regression includes eight concurrent child PHP processes competing for capacity3: exactly3 accepted, all children exited0. Normal category requests pass before their ceilings, bursts return429, two bearer devices/methods share a password window, two socket devices/tenants share sends, sixth socket denied, duplicate pending join makes only one query, duplicate auth one upstream request and disconnect aborts it. Existing tenant/privacy/network-isolation suites pass. Mobile/dependencies/migrations/tools unchanged in this phase; phase0 Flutter evidence therefore retained.

Phase1 scope: `chat-server/abuse.js`, `chat-server/server.js`, `chat-server/tests/abuse.test.js`, `chat-server/tests/socket.integration.test.js`, `chat-server/tests/tenant-isolation.test.js`, `chat-server/tests/transport-security.test.js`, `website/app/Config/Filters.php`, `website/app/Config/Routes.php`, `website/app/Controllers/Api/ChatController.php`, `website/app/Controllers/Api/ParticipantController.php`, `website/app/Controllers/Api/VotingController.php`, `website/app/Controllers/Api/WheelController.php`, `website/app/Filters/AuthFilter.php`, `website/app/Filters/AbuseFilter.php`, `website/app/Services/AbuseLimiter.php`, `website/tests/AbuseLimiterTest.php`, `website/tests/Api/AbuseControlTest.php`, and this canonical audit. Full diff/cached check reviewed before local checkpoint. No production capacity or proxy deployment claim.

Evidence root outside Git: `C:\Users\lenovo\AppData\Local\Temp\kartar-overnight-b9ebcb8c5672403d80005471f2848070`; `phase0/` and `phase1/` contain logs/XML/JSON and staged diffs. Synthetic inputs only. Unexpected production application/test requests **0**, guard evidence (not packet capture). No push or deployment.

**Phase 2 — SEC-10 RESOLVED IN WORKTREE — NOT DEPLOYED.** Phase1 local checkpoint **da03e6d** (`fix(security): bound mutation and socket abuse`) left a clean worktree. Open security registry now **0 critical / 1 high / 8 medium / 2 low**. Every REST chat read (rooms, room history, private history, contacts) requires configured `chat.read`; send requires `chat.send`, before resource queries. Existing `chat.manage` gates, tenant boundaries and custom-room membership checks remain. Dashboard announcement previews and counts require `announcement.view` and `target_role=semua` or the authenticated membership role, including management roles. No deputy/seksi permissions were added; Rbac.php is unchanged.

Red characterization: **5 tests / 11 assertions / 5 failures / 0 errors**, four configured denied roles received200 and an ordinary member received a chair-only preview. Final targeted chat/RBAC/tenant tests **69 / 69, 524 assertions, 0 failures/errors/skips** (targeted XML is authoritative for assertions); full backend **323 tests / 316 passed / 1,986 assertions / 0 failures / 0 errors / 7 skips**,49.726s/30MiB. The new matrix tests all four reads and send for each denied role, zero stored chats; member/author/denied dashboard responses prove both preview and count boundaries. Existing authorized chat and tenant cases pass. Node/mobile unchanged; phase1 Node56 and phase0 Flutter64/analyzer0errors remain relevant. Phase2 scope only `website/app/Controllers/Api/ChatController.php`, `website/app/Controllers/Api/DashboardController.php`, `website/tests/Api/ChatDashboardAuthorizationTest.php`, canonical audit. Evidence `phase2/red.log/xml`, `targeted.log/xml`, `backend.log/xml`, reviewed staged diff. No production requests/push/deployment; unexpected guarded requests0.
