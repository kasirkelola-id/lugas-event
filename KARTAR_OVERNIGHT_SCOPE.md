# KARTAR overnight checkpoint scope

Start: `ceb64cb`. Source checkpoints through `5949c80`; final documentation checkpoint adds this manifest and the canonical audit completion record. All commits are local. No push/deployment. Both tools directories are absent from this change scope.

The following is the complete unique changed/new path inventory for this overnight run, including the final documentation files. Paths are repository-relative. TEMP runtimes, credentials, raw evidence, generated build outputs and ignored private PHPUnit configuration are excluded.

**255 unique committed paths**. Existing historical migrations and the Batch4 forward alignment file are unchanged; only the six new overnight forward migrations are included.

## Local checkpoints

| Commit | Scope |
|---|---|
| `cd32308` | fix(web): harden csrf and credential flows |
| `da03e6d` | fix(security): bound mutation and socket abuse |
| `b904aee` | fix(security): enforce chat and announcement permissions |
| `5564647` | fix(android): require release signing and prohibit cleartext |
| `6d614e2` | fix(security): revoke stale credentials and bind privileged sessions |
| `048b55b` | fix(mobile): migrate bearer tokens to secure storage |
| `98177fb` | fix(security): sanitize diagnostics and reject internal defaults |
| `cf96764` | fix(uploads): reencode images and verify server protection candidate |
| `20f46ea` | fix(notifications): scope recipients and persist logout cleanup |
| `4474dab` | fix(settings): validate scoped values and trusted update origins |
| `b8d8016` | fix(memberships): make identity and approval writes atomic |
| `4ceae3b` | fix(wheel): serialize session decisions and emit after commit |
| `ff6c490` | fix(uploads): commit owner changes before retiring managed images |
| `6aad3cd` | fix(chat): acknowledge persisted messages and make retries idempotent |
| `63fb093` | perf(notifications): enqueue durable jobs and bound worker dispatch |
| `f17f2d0` | perf(lists): bound collection pages and preserve client navigation |
| `13db667` | perf(queries): batch collection counts and participant writes |
| `fb0db89` | test(readiness): verify mysql races and measured query indexes |
| `034c507` | fix(mobile): own listeners and bound upload transport |
| `0998585` | fix(maintenance): bound cleanup and report upload orphans |
| `6c61256` | test(recovery): verify isolated mysql backup and restore |
| `ed060e9` | feat(operations): add private health and backlog reports |
| `e777aa6` | fix(web): preserve announcement authors and repair legacy views |
| `caa9a23` | fix(mobile): clear analyzer warnings and guard async navigation |
| `5949c80` | perf(chat): use transactional jobs and verify guarded local load |

The final documentation commit records phases27-29 and Git completion; read its hash from `git log`, avoiding a self-referential hash in this file.

## Complete path inventory

- `KARTAR_OVERNIGHT_SCOPE.md`
- `KARTAR_PRODUCTION_READINESS_AUDIT.md`
- `chat-server/abuse.js`
- `chat-server/chat-fanout.js`
- `chat-server/chat-persistence.js`
- `chat-server/health.js`
- `chat-server/internal-secret.js`
- `chat-server/package-lock.json`
- `chat-server/server.js`
- `chat-server/tests/abuse.test.js`
- `chat-server/tests/chat-persistence.test.js`
- `chat-server/tests/health.test.js`
- `chat-server/tests/internal-secret.test.js`
- `chat-server/tests/local-load-runner.js`
- `chat-server/tests/production-readiness.audit.test.js`
- `chat-server/tests/socket.integration.test.js`
- `chat-server/tests/support/chat-row.js`
- `chat-server/tests/support/chat-store.js`
- `chat-server/tests/tenant-isolation.test.js`
- `chat-server/tests/transport-security.test.js`
- `deploy/ANNOUNCEMENT_AUTHORS.md`
- `deploy/BACKUP_RESTORE.md`
- `deploy/CHAT_DELIVERY.md`
- `deploy/COLLECTION_PAGINATION.md`
- `deploy/HEALTH_RECOVERY.md`
- `deploy/LOCAL_LOAD_VERIFICATION.md`
- `deploy/MIGRATION_SAFETY.md`
- `deploy/MYSQL_QUERY_VERIFICATION.md`
- `deploy/NOTIFICATION_WORKER.md`
- `deploy/RETENTION_MAINTENANCE.md`
- `deploy/SERVER_SECURITY.md`
- `deploy/nginx/kartar-locations.conf`
- `deploy/nginx/kartar-site.conf.example`
- `deploy/verify_nginx_local.py`
- `mobile/RELEASE_SECURITY.md`
- `mobile/android/app/build.gradle.kts`
- `mobile/android/app/src/debug/res/xml/network_security_config.xml`
- `mobile/android/app/src/main/AndroidManifest.xml`
- `mobile/android/app/src/main/res/xml/backup_rules.xml`
- `mobile/android/app/src/main/res/xml/data_extraction_rules.xml`
- `mobile/android/app/src/main/res/xml/network_security_config.xml`
- `mobile/lib/core/config/api_config.dart`
- `mobile/lib/core/network/api_client.dart`
- `mobile/lib/core/security/password_policy.dart`
- `mobile/lib/main.dart`
- `mobile/lib/models/chat_model.dart`
- `mobile/lib/screens/admin/admin_acara_screen.dart`
- `mobile/lib/screens/admin/admin_home_screen.dart`
- `mobile/lib/screens/admin/admin_pengguna_screen.dart`
- `mobile/lib/screens/admin/admin_pengumuman_screen.dart`
- `mobile/lib/screens/admin/admin_peserta_screen.dart`
- `mobile/lib/screens/admin/admin_profil_screen.dart`
- `mobile/lib/screens/anggota/anggota_home_screen.dart`
- `mobile/lib/screens/anggota/anggota_profil_screen.dart`
- `mobile/lib/screens/anggota/attendance_geofence_screen.dart`
- `mobile/lib/screens/anggota/attendance_history_screen.dart`
- `mobile/lib/screens/auth/force_change_password_screen.dart`
- `mobile/lib/screens/auth/pin_screen.dart`
- `mobile/lib/screens/auth/register_screen.dart`
- `mobile/lib/screens/chat/chat_list_screen.dart`
- `mobile/lib/screens/chat/chat_pagination_controller.dart`
- `mobile/lib/screens/chat/chat_room_screen.dart`
- `mobile/lib/screens/chat/create_group_screen.dart`
- `mobile/lib/screens/inventory/inventory_main_screen.dart`
- `mobile/lib/screens/kas/kas_screen.dart`
- `mobile/lib/screens/pengelola/attendance_list_screen.dart`
- `mobile/lib/screens/pengelola/participant_list_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_acara_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_home_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_pengguna_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_peserta_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_profil_screen.dart`
- `mobile/lib/screens/pengelola/pengelola_riwayat_screen.dart`
- `mobile/lib/screens/shared/map_picker_screen.dart`
- `mobile/lib/screens/shared/user_pengumuman_screen.dart`
- `mobile/lib/screens/undian/create_wheel_screen.dart`
- `mobile/lib/screens/undian/wheel_list_screen.dart`
- `mobile/lib/screens/undian/wheel_session_screen.dart`
- `mobile/lib/screens/voting/voting_list_screen.dart`
- `mobile/lib/screens/widgets/common/collection_pager.dart`
- `mobile/lib/screens/widgets/common/community_activity_section.dart`
- `mobile/lib/services/announcement_service.dart`
- `mobile/lib/services/attendance_service.dart`
- `mobile/lib/services/auth_service.dart`
- `mobile/lib/services/chat_service.dart`
- `mobile/lib/services/event_service.dart`
- `mobile/lib/services/inventory_service.dart`
- `mobile/lib/services/kas_service.dart`
- `mobile/lib/services/logout_retry.dart`
- `mobile/lib/services/notification_service.dart`
- `mobile/lib/services/participant_service.dart`
- `mobile/lib/services/profile_service.dart`
- `mobile/lib/services/voting_service.dart`
- `mobile/lib/services/wheel_service.dart`
- `mobile/lib/storage/auth_storage.dart`
- `mobile/lib/utils/owned_socket_listeners.dart`
- `mobile/lib/utils/subscription_lifecycle.dart`
- `mobile/lib/widgets/app_update_banner.dart`
- `mobile/linux/flutter/generated_plugin_registrant.cc`
- `mobile/linux/flutter/generated_plugins.cmake`
- `mobile/macos/Flutter/GeneratedPluginRegistrant.swift`
- `mobile/pubspec.lock`
- `mobile/pubspec.yaml`
- `mobile/test/auth_storage_test.dart`
- `mobile/test/chat_service_fallback_test.dart`
- `mobile/test/collection_pagination_test.dart`
- `mobile/test/credential_logging_test.dart`
- `mobile/test/flutter_test_config.dart`
- `mobile/test/logout_retry_test.dart`
- `mobile/test/owned_socket_listeners_test.dart`
- `mobile/test/password_policy_test.dart`
- `mobile/test/profile_upload_transport_test.dart`
- `mobile/test/secure_token_storage_test.dart`
- `mobile/test/subscription_lifecycle_test.dart`
- `mobile/windows/flutter/generated_plugin_registrant.cc`
- `mobile/windows/flutter/generated_plugins.cmake`
- `website/app/Commands/ChatCleanupCommand.php`
- `website/app/Commands/HealthReportCommand.php`
- `website/app/Commands/MaintenanceReportCommand.php`
- `website/app/Commands/NotificationWork.php`
- `website/app/Config/Filters.php`
- `website/app/Config/Routes.php`
- `website/app/Config/Security.php`
- `website/app/Config/Services.php`
- `website/app/Config/UpdatePolicy.php`
- `website/app/Controllers/Api/AbsensiController.php`
- `website/app/Controllers/Api/AnnouncementController.php`
- `website/app/Controllers/Api/AppVersionController.php`
- `website/app/Controllers/Api/AuthController.php`
- `website/app/Controllers/Api/BaseApiController.php`
- `website/app/Controllers/Api/ChatController.php`
- `website/app/Controllers/Api/DashboardController.php`
- `website/app/Controllers/Api/EventController.php`
- `website/app/Controllers/Api/InternalApiController.php`
- `website/app/Controllers/Api/InventoryController.php`
- `website/app/Controllers/Api/KasController.php`
- `website/app/Controllers/Api/MembershipController.php`
- `website/app/Controllers/Api/ParticipantController.php`
- `website/app/Controllers/Api/ProfileController.php`
- `website/app/Controllers/Api/SettingController.php`
- `website/app/Controllers/Api/UserController.php`
- `website/app/Controllers/Api/VotingController.php`
- `website/app/Controllers/Api/WheelController.php`
- `website/app/Controllers/Superadmin/AuthController.php`
- `website/app/Controllers/Superadmin/KarangTarunaController.php`
- `website/app/Controllers/Superadmin/KelurahanController.php`
- `website/app/Controllers/Superadmin/ManageController.php`
- `website/app/Controllers/Superadmin/SettingController.php`
- `website/app/Database/Migrations/2026-10-05-000002_BindPrivilegedBearerSessions.php`
- `website/app/Database/Migrations/2026-10-05-000003_BindPushDevicesToBearerSessions.php`
- `website/app/Database/Migrations/2026-10-05-000004_AddChatIdempotency.php`
- `website/app/Database/Migrations/2026-10-05-000005_AddDurableNotificationJobs.php`
- `website/app/Database/Migrations/2026-10-06-000006_AddMeasuredQueryIndexes.php`
- `website/app/Database/Migrations/2026-10-06-000007_AddSuperadminAnnouncementAuthor.php`
- `website/app/Database/SafeMigrationRunner.php`
- `website/app/Database/Seeds/UserSeeder.php`
- `website/app/Filters/AbuseFilter.php`
- `website/app/Filters/AuthFilter.php`
- `website/app/Filters/CredentialSafeToolbar.php`
- `website/app/Filters/SuperadminFilter.php`
- `website/app/Log/SafeLogger.php`
- `website/app/Models/ChatModel.php`
- `website/app/Models/ChatRoomModel.php`
- `website/app/Models/PengumumanModel.php`
- `website/app/Models/UserDeviceModel.php`
- `website/app/Models/UserTokenModel.php`
- `website/app/Services/AbuseLimiter.php`
- `website/app/Services/BearerLogoutService.php`
- `website/app/Services/ChatCleanupService.php`
- `website/app/Services/ChatFanout.php`
- `website/app/Services/ChatPersistenceService.php`
- `website/app/Services/CollectionPage.php`
- `website/app/Services/CredentialPolicy.php`
- `website/app/Services/CredentialSessionService.php`
- `website/app/Services/DomainNotification.php`
- `website/app/Services/IdentityCreationService.php`
- `website/app/Services/InternalSecret.php`
- `website/app/Services/MaintenanceLock.php`
- `website/app/Services/ManagedImageService.php`
- `website/app/Services/MembershipApprovalService.php`
- `website/app/Services/NotificationService.php`
- `website/app/Services/NotificationWorker.php`
- `website/app/Services/OperationalHealth.php`
- `website/app/Services/OrphanUploadReport.php`
- `website/app/Services/ParticipantBatchService.php`
- `website/app/Services/PushTransport.php`
- `website/app/Services/SafeImageUpload.php`
- `website/app/Services/SessionCleanupService.php`
- `website/app/Services/SettingsPolicy.php`
- `website/app/Services/WebInputPolicy.php`
- `website/app/Views/superadmin/dashboard.php`
- `website/app/Views/superadmin/karang_taruna/index.php`
- `website/app/Views/superadmin/kelurahan/index.php`
- `website/app/Views/superadmin/manage/events.php`
- `website/app/Views/superadmin/manage/kas.php`
- `website/app/Views/superadmin/manage/pengumuman.php`
- `website/app/Views/superadmin/manage/users.php`
- `website/app/Views/superadmin/partials/collection_page.php`
- `website/app/Views/superadmin/partials/sweetalert.php`
- `website/app/Views/superadmin/settings/index.php`
- `website/app/Views/superadmin/temporary_credential.php`
- `website/phpunit.xml.dist`
- `website/tests/AbuseLimiterTest.php`
- `website/tests/Api/AbuseControlTest.php`
- `website/tests/Api/AnnouncementAuthorMigrationTest.php`
- `website/tests/Api/AppVersionTest.php`
- `website/tests/Api/AuthTest.php`
- `website/tests/Api/ChatDashboardAuthorizationTest.php`
- `website/tests/Api/ChatPersistenceTest.php`
- `website/tests/Api/ChatTest.php`
- `website/tests/Api/CollectionPaginationTest.php`
- `website/tests/Api/CredentialHardeningTest.php`
- `website/tests/Api/DashboardTest.php`
- `website/tests/Api/DiscoveredDefectTest.php`
- `website/tests/Api/IdentitySemanticsTest.php`
- `website/tests/Api/InventoryAtomicityTest.php`
- `website/tests/Api/MaintenanceSafetyTest.php`
- `website/tests/Api/ManagedImageOrderingTest.php`
- `website/tests/Api/MeasuredIndexMigrationTest.php`
- `website/tests/Api/MembershipApprovalTest.php`
- `website/tests/Api/MembershipAtomicityTest.php`
- `website/tests/Api/NotificationEligibilityTest.php`
- `website/tests/Api/NotificationWorkerTest.php`
- `website/tests/Api/OperationalHealthTest.php`
- `website/tests/Api/ProductionReadinessAuditTest.php`
- `website/tests/Api/ProfilePhotoTest.php`
- `website/tests/Api/QueryBatchingTest.php`
- `website/tests/Api/SessionRevocationTest.php`
- `website/tests/Api/SettingsValidationTest.php`
- `website/tests/Api/TenantAuthorizationRegressionTest.php`
- `website/tests/Api/WebCsrfCredentialTest.php`
- `website/tests/Api/WheelAtomicityTest.php`
- `website/tests/Api/WheelFeatureTest.php`
- `website/tests/ChatNotificationSpoofTest.php`
- `website/tests/ChatNotificationTest.php`
- `website/tests/Feature/WheelTest.php`
- `website/tests/IsolatedDocumentRootTest.php`
- `website/tests/MigrationSafetyTest.php`
- `website/tests/MySQL/OvernightConcurrencyMySQLTest.php`
- `website/tests/MySQL/README.md`
- `website/tests/SafeImageUploadTest.php`
- `website/tests/SecurityDiagnosticsTest.php`
- `website/tests/_support/AuthTrait.php`
- `website/tests/_support/BaseTest.php`
- `website/tests/_support/local_load_bootstrap.php`
- `website/tests/_support/local_load_fixture.php`
- `website/tests/_support/mysql_application_bootstrap.php`
- `website/tests/_support/mysql_inventory_worker.php`
- `website/tests/_support/mysql_overnight_worker.php`
- `website/tests/_support/mysql_query_probe.php`
- `website/tests/_support/mysql_restore_probe.php`
- `website/tests/_support/production_credential_probe.php`
- `website/tests/_support/production_internal_secret_probe.php`
- `website/tests/_support/production_migration_probe.php`
- `website/tests/_support/testing_bootstrap.php`

## Excluded artifacts

- Both website/tools and chat-server/tools: unchanged and not executed.
- TEMP MySQL archive/binary/data, test-only signing material, local credentials and evidence directories: outside repository, not staged.
- vendor/node_modules, Flutter/Gradle build outputs, APKs and caches: excluded. Required tracked native plugin registrants are explicitly listed in the committed inventory.
- Ignored website/phpunit.xml: private local bootstrap setting; tracked website/phpunit.xml.dist is the canonical reviewed test configuration.
- No production data, production credentials or signing private key were committed.
