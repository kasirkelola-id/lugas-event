import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/test/test_flutter_secure_storage_platform.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

class StorageProbe extends TestFlutterSecureStoragePlatform {
  StorageProbe() : super({});
  bool failWrite = false;
  bool failRead = false;
  bool failDelete = false;
  int writes = 0;
  Completer<void>? writeBarrier;

  @override
  Future<void> write({
    required String key,
    required String value,
    required Map<String, String> options,
  }) async {
    writes++;
    await writeBarrier?.future;
    if (failWrite) {
      throw PlatformException(
        code: 'synthetic',
        message: 'PRIVATE_PROVIDER_SENTINEL',
      );
    }
    return super.write(key: key, value: value, options: options);
  }

  @override
  Future<String?> read({
    required String key,
    required Map<String, String> options,
  }) async {
    if (failRead) {
      throw PlatformException(
        code: 'synthetic',
        message: 'PRIVATE_PROVIDER_SENTINEL',
      );
    }
    return super.read(key: key, options: options);
  }

  @override
  Future<void> delete({
    required String key,
    required Map<String, String> options,
  }) async {
    if (failDelete) {
      throw PlatformException(
        code: 'synthetic',
        message: 'PRIVATE_PROVIDER_SENTINEL',
      );
    }
    return super.delete(key: key, options: options);
  }
}

void main() {
  late StorageProbe probe;
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    probe = StorageProbe();
    FlutterSecureStoragePlatform.instance = probe;
  });

  test('secure read/write/delete never persists a plaintext token', () async {
    await AuthStorage.saveToken('synthetic-one');
    expect(probe.data['auth_token'], 'synthetic-one');
    expect(await AuthStorage.getToken(), 'synthetic-one');
    expect(
      (await SharedPreferences.getInstance()).getString('auth_token'),
      isNull,
    );
    await AuthStorage.removeToken();
    expect(probe.data, isEmpty);
    expect(await AuthStorage.getToken(), isNull);
  });

  test(
    'legacy migration writes once and prefers existing secure identity',
    () async {
      SharedPreferences.setMockInitialValues({
        'auth_token': 'synthetic-legacy',
      });
      expect(await AuthStorage.getToken(), 'synthetic-legacy');
      expect(await AuthStorage.getToken(), 'synthetic-legacy');
      expect(probe.writes, 1);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', 'stale-synthetic');
      expect(await AuthStorage.getToken(), 'synthetic-legacy');
      expect(prefs.getString('auth_token'), isNull);
      expect(probe.writes, 1);
    },
  );

  test(
    'failed migration removes plaintext and cannot authenticate from fallback',
    () async {
      SharedPreferences.setMockInitialValues({
        'auth_token': 'synthetic-legacy',
      });
      probe.failWrite = true;
      expect(await AuthStorage.getToken(), isNull);
      expect(
        (await SharedPreferences.getInstance()).getString('auth_token'),
        isNull,
      );
      probe.failWrite = false;
      expect(await AuthStorage.getToken(), isNull);
      await AuthStorage.saveToken('new-synthetic');
      expect(await AuthStorage.getToken(), 'new-synthetic');
    },
  );

  test('native read failure fails closed without plaintext fallback', () async {
    SharedPreferences.setMockInitialValues({'auth_token': 'synthetic-legacy'});
    probe.failRead = true;
    expect(await AuthStorage.getToken(), isNull);
    expect(
      (await SharedPreferences.getInstance()).getString('auth_token'),
      isNull,
    );
  });

  test(
    'failed account-switch save cannot restore the preceding identity',
    () async {
      await AuthStorage.saveToken('old-synthetic');
      probe.failWrite = true;
      await expectLater(
        AuthStorage.saveToken('new-synthetic'),
        throwsA(
          isA<StateError>().having(
            (e) => e.message,
            'safe message',
            'Credential storage unavailable',
          ),
        ),
      );
      expect(await AuthStorage.getToken(), isNull);
    },
  );

  test(
    'native delete failure still durably disables the local session',
    () async {
      await AuthStorage.saveToken('synthetic-one');
      probe.failDelete = true;
      await expectLater(AuthStorage.removeToken(), throwsA(isA<StateError>()));
      expect(probe.data['auth_token'], 'synthetic-one');
      expect(await AuthStorage.getToken(), isNull);
      probe.failDelete = false;
      await AuthStorage.removeToken();
      expect(probe.data, isEmpty);
    },
  );

  test(
    'logout queued during migration cannot resurrect the old session',
    () async {
      SharedPreferences.setMockInitialValues({
        'auth_token': 'synthetic-legacy',
      });
      probe.writeBarrier = Completer<void>();
      final migration = AuthStorage.getToken();
      await Future<void>.delayed(Duration.zero);
      final logout = AuthStorage.removeToken();
      probe.writeBarrier!.complete();
      await migration;
      await logout;
      expect(await AuthStorage.getToken(), isNull);
      expect(probe.data, isEmpty);
    },
  );

  test(
    'tenant switch preserves global credential and logout preserves tenant',
    () async {
      await AuthStorage.saveToken('synthetic-one');
      await AuthStorage.saveTenant(101, 'A');
      await AuthStorage.saveTenant(102, 'B');
      expect(await AuthStorage.getToken(), 'synthetic-one');
      await AuthStorage.removeToken();
      expect((await AuthStorage.getTenant())?['id'], 102);
      expect(await AuthStorage.getToken(), isNull);
    },
  );
}
