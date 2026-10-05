import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../core/network/api_client.dart';

/// Encrypted logout cleanup outbox. It never restores an authenticated session.
class LogoutRetry {
  static const _key = 'pending_session_revocations';
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(resetOnError: false),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.unlocked_this_device,
    ),
  );
  static Future<void> _operations = Future<void>.value();

  static Future<T> _serialise<T>(Future<T> Function() task) {
    final result = _operations.then((_) => task());
    _operations = result.then<void>(
      (_) {},
      onError: (Object _, StackTrace _) {},
    );
    return result;
  }

  static Future<List<Map<String, dynamic>>> _read() async {
    try {
      final value = await _storage.read(key: _key);
      if (value == null) return [];
      final decoded = jsonDecode(value);
      if (decoded is! List || decoded.length > 16) {
        throw StateError('Session cleanup storage unavailable');
      }
      final jobs = decoded
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      for (final job in jobs) {
        if (job['token'] is! String ||
            (job['token'] as String).isEmpty ||
            (job['token'] as String).length > 512 ||
            job['next_attempt'] is! int ||
            (job['next_attempt'] as int) < 0) {
          throw StateError('Session cleanup storage unavailable');
        }
      }
      return jobs;
    } catch (_) {
      throw StateError('Session cleanup storage unavailable');
    }
  }

  static Future<void> _save(List<Map<String, dynamic>> jobs) async {
    try {
      if (jobs.isEmpty) {
        await _storage.delete(key: _key);
      } else {
        final value = jsonEncode(jobs);
        await _storage.write(key: _key, value: value);
        if (await _storage.read(key: _key) != value) {
          throw StateError('Session cleanup storage unavailable');
        }
      }
    } catch (_) {
      throw StateError('Session cleanup storage unavailable');
    }
  }

  static Future<void> enqueue(String token) => _serialise(() async {
    if (token.isEmpty || token.length > 512) {
      throw StateError('Invalid cleanup credential');
    }
    final jobs = await _read();
    if (jobs.any((job) => job['token'] == token)) return;
    if (jobs.length >= 16) throw StateError('Session cleanup queue is full');
    jobs.add({'token': token, 'next_attempt': 0});
    await _save(jobs);
  });

  static Future<bool> canCreateSession() =>
      _serialise(() async => (await _read()).length < 16);

  static Future<void> flush({
    Future<int> Function(String)? sender,
    int? nowMs,
  }) => _serialise(() async {
    final jobs = await _read();
    final now = nowMs ?? DateTime.now().millisecondsSinceEpoch;
    var attempted = 0;
    // Newest logout first; bounded requests with native client cancellation.
    for (final job in jobs.reversed.toList()) {
      final next = job['next_attempt'] as int;
      if ((next > now && next <= now + 60000) || attempted >= 3) continue;
      attempted++;
      int status;
      try {
        status = await (sender ?? ApiClient.revokeSession)(
          job['token'] as String,
        );
      } catch (_) {
        status = 503;
      }
      if (status == 200 || status == 401) {
        jobs.remove(job); // Revoked, expired or already invalid: safe terminal.
      } else {
        job['next_attempt'] = now + 60000;
      }
    }
    await _save(jobs);
  });
}
