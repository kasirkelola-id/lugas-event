import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_client.dart';

void main() {
  for (final path in [
    '/api/login',
    '/api/register',
    '/api/profile/password',
    '/api/users',
    '/api/users/17/reset-password',
    '/api/chats?search=synthetic-query-secret',
    '/api/profile/fcm-token',
    '/api/wheels',
  ]) {
    test('Credential logs omit payloads, headers and exceptions: $path', () {
      final messages = <String>[];
      final original = debugPrint;
      debugPrint = (String? message, {int? wrapWidth}) {
        messages.add(message ?? '');
      };
      try {
        final url = 'https://example.invalid$path';
        final body = jsonEncode({
          'password': 'synthetic-password-secret',
          'data': {
            'temporary_password': 'synthetic-temp-secret',
            'token': 'synthetic-token-secret',
          },
        });
        ApiClient.logRequest('POST', url, {
          'Authorization': 'Bearer synthetic-token-secret',
        }, body);
        ApiClient.logResponse('POST', url, 201, body);
        ApiClient.logResponse(
          'POST',
          url,
          500,
          'non-json synthetic-temp-secret',
        );
        ApiClient.logException(url, Exception('synthetic-password-secret'));
        expect(messages, hasLength(4));
        expect(messages.join(), isNot(contains('secret')));
        expect(messages.join(), isNot(contains('Bearer')));
      } finally {
        debugPrint = original;
      }
    });
  }
}
