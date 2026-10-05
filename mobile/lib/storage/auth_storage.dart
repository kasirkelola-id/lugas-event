import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthStorage {
  static const String _tokenKey = 'auth_token';
  static const String _disabledKey = 'auth_token_disabled';
  static const _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(resetOnError: false),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.unlocked_this_device,
    ),
  );
  static Future<void> _tokenOperations = Future<void>.value();

  // Serialise migration/save/delete so a pending read cannot resurrect logout.
  static Future<T> _serialise<T>(Future<T> Function() operation) {
    final result = _tokenOperations.then((_) => operation());
    _tokenOperations = result.then<void>(
      (_) {},
      onError: (Object _, StackTrace _) {},
    );
    return result;
  }

  static const String _ktIdKey = 'karang_taruna_id';
  static const String _ktNameKey = 'nama_organisasi';
  static const String _ktLogoKey = 'logo_url';

  static Future<void> saveToken(String token) => _serialise(() async {
    if (token.isEmpty) throw ArgumentError('Empty credential');
    final prefs = await SharedPreferences.getInstance();
    // Non-secret tombstone blocks an older credential even if native write fails.
    if (!await prefs.setBool(_disabledKey, true) ||
        !await prefs.remove(_tokenKey)) {
      throw StateError('Credential storage unavailable');
    }
    try {
      await _secure.write(key: _tokenKey, value: token);
      if (await _secure.read(key: _tokenKey) != token) {
        throw StateError('Credential verification failed');
      }
      if (!await prefs.setBool(_disabledKey, false)) {
        throw StateError('Credential activation failed');
      }
    } catch (_) {
      throw StateError('Credential storage unavailable');
    }
  });

  static Future<String?> getToken() => _serialise(() async {
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getBool(_disabledKey) == true) {
      await prefs.remove(_tokenKey);
      return null;
    }
    try {
      final stored = await _secure.read(key: _tokenKey);
      final legacy = prefs.getString(_tokenKey);
      if (stored != null && stored.isNotEmpty) {
        if (legacy != null && !await prefs.remove(_tokenKey)) {
          throw StateError('Legacy credential removal failed');
        }
        return stored;
      }
      if (legacy == null || legacy.isEmpty) return null;
      await _secure.write(key: _tokenKey, value: legacy);
      if (await _secure.read(key: _tokenKey) != legacy) {
        throw StateError('Credential migration failed');
      }
      if (!await prefs.remove(_tokenKey)) {
        throw StateError('Legacy credential removal failed');
      }
      return legacy;
    } catch (_) {
      // Never authenticate from a plaintext fallback on a platform/migration error.
      await prefs.setBool(_disabledKey, true);
      await prefs.remove(_tokenKey);
      return null;
    }
  });

  static Future<void> removeToken() => _serialise(() async {
    final prefs = await SharedPreferences.getInstance();
    if (!await prefs.setBool(_disabledKey, true) ||
        !await prefs.remove(_tokenKey)) {
      throw StateError('Credential storage unavailable');
    }
    try {
      await _secure.delete(key: _tokenKey);
    } catch (_) {
      // Tombstone remains durable: a native delete failure cannot restore a session.
      throw StateError('Credential storage unavailable');
    }
  });

  static Future<bool> hasToken() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  static Future<void> saveTenant(int id, String name, {String? logoUrl}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_ktIdKey, id);
    await prefs.setString(_ktNameKey, name);
    if (logoUrl != null) {
      await prefs.setString(_ktLogoKey, logoUrl);
    } else {
      await prefs.remove(_ktLogoKey);
    }
  }

  static Future<Map<String, dynamic>?> getTenant() async {
    final prefs = await SharedPreferences.getInstance();
    final id = prefs.getInt(_ktIdKey);
    final name = prefs.getString(_ktNameKey);
    final logoUrl = prefs.getString(_ktLogoKey);
    if (id != null && name != null) {
      return {'id': id, 'name': name, 'logo_url': logoUrl};
    }
    return null;
  }

  static Future<void> clearTenant() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_ktIdKey);
    await prefs.remove(_ktNameKey);
    await prefs.remove(_ktLogoKey);
  }
}
