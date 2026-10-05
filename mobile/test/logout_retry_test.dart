import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/services/logout_retry.dart';
import 'package:mobile/storage/auth_storage.dart';

class FailedCleanupStorage extends TestFlutterSecureStoragePlatform {
  FailedCleanupStorage() : super({});
  @override
  Future<void> write({
    required String key,
    required String value,
    required Map<String, String> options,
  }) async {
    throw PlatformException(
      code: 'synthetic',
      message: 'PRIVATE_PROVIDER_SENTINEL',
    );
  }
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test(
    'Native cleanup storage failure has no plaintext fallback or raw exception',
    () async {
      final original = FlutterSecureStoragePlatform.instance;
      final failed = FailedCleanupStorage();
      FlutterSecureStoragePlatform.instance = failed;
      try {
        await expectLater(
          LogoutRetry.enqueue('synthetic-old-session'),
          throwsA(
            isA<StateError>().having(
              (error) => error.message,
              'controlled error',
              'Session cleanup storage unavailable',
            ),
          ),
        );
        expect(failed.data, isEmpty);
        expect((await SharedPreferences.getInstance()).getKeys(), isEmpty);
      } finally {
        FlutterSecureStoragePlatform.instance = original;
      }
    },
  );

  test(
    'Malformed secure outbox fails with controlled text and never restores auth',
    () async {
      await const FlutterSecureStorage().write(
        key: 'pending_session_revocations',
        value: 'synthetic-secret-invalid-json',
      );
      await expectLater(
        LogoutRetry.flush(sender: (_) async => 200),
        throwsA(
          isA<StateError>().having(
            (error) => error.message,
            'controlled error',
            'Session cleanup storage unavailable',
          ),
        ),
      );
      expect(await AuthStorage.hasToken(), isFalse);
    },
  );

  test(
    'Failed revocation is encrypted, survives local logout and retries only old session',
    () async {
      await AuthStorage.saveToken('synthetic-old-session');
      await LogoutRetry.enqueue('synthetic-old-session');
      await AuthStorage.removeToken();
      await LogoutRetry.flush(sender: (_) async => 503, nowMs: 1000);
      expect(await AuthStorage.hasToken(), isFalse);
      final prefs = await SharedPreferences.getInstance();
      expect(
        prefs.getKeys().any(
          (key) => '${prefs.get(key)}'.contains('synthetic-old-session'),
        ),
        isFalse,
      );
      final encrypted = await const FlutterSecureStorage().read(
        key: 'pending_session_revocations',
      );
      expect(encrypted, contains('synthetic-old-session'));
      await AuthStorage.saveToken('synthetic-new-session');
      final received = <String>[];
      await LogoutRetry.flush(
        sender: (token) async {
          received.add(token);
          return 200;
        },
        nowMs: 61000,
      );
      expect(received, ['synthetic-old-session']);
      expect(await AuthStorage.getToken(), 'synthetic-new-session');
      expect(
        await const FlutterSecureStorage().read(
          key: 'pending_session_revocations',
        ),
        isNull,
      );
    },
  );

  test(
    'Duplicate jobs and failed attempts are bounded with one minute backoff',
    () async {
      await LogoutRetry.enqueue('synthetic-old-session');
      await LogoutRetry.enqueue('synthetic-old-session');
      var attempts = 0;
      Future<int> fail(String token) async {
        attempts++;
        return 503;
      }

      await LogoutRetry.flush(sender: fail, nowMs: 1000);
      await LogoutRetry.flush(sender: fail, nowMs: 2000);
      expect(attempts, 1);
      await LogoutRetry.flush(sender: (_) async => 401, nowMs: 61000);
      expect(
        await const FlutterSecureStorage().read(
          key: 'pending_session_revocations',
        ),
        isNull,
      );
    },
  );

  test(
    'Each flush is finite and a full queue gates creation of another session',
    () async {
      for (var i = 0; i < 16; i++) {
        await LogoutRetry.enqueue('synthetic-session-$i');
      }
      expect(await LogoutRetry.canCreateSession(), isFalse);
      await expectLater(
        LogoutRetry.enqueue('synthetic-overflow'),
        throwsStateError,
      );
      var attempts = 0;
      await LogoutRetry.flush(
        sender: (_) async {
          attempts++;
          return 401;
        },
        nowMs: 1000,
      );
      expect(attempts, 3);
      expect(await LogoutRetry.canCreateSession(), isTrue);
      final jobs = jsonDecode(
        (await const FlutterSecureStorage().read(
          key: 'pending_session_revocations',
        ))!,
      );
      expect(jobs, hasLength(13));
    },
  );
}
