import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import 'support/network_guard.dart';

void main() {
  test('suite installs a transport that cannot open real connections', () {
    final client = HttpClient();
    expect(client, isA<NoNetworkHttpClient>());
    client.close();
  });

  test(
    'production and external HTTP/FCM requests fail before network I/O',
    () async {
      // A separate deny-only guard permits assertions about intentional probes
      // without confusing them with unexpected suite transport attempts.
      final probe = TestNetworkGuard();
      await HttpOverrides.runWithHttpOverrides(() async {
        for (final url in [
          'https://kartar.kelolakasir.id/api/chats/messages',
          'https://KARTAR.KELOLAKASIR.ID./api/me',
          'https://example.com/anything',
          'https://fcm.googleapis.com/v1/projects/test/messages:send',
        ]) {
          await expectLater(http.get(Uri.parse(url)), throwsStateError);
        }
      }, GuardHttpOverrides(probe));
      expect(probe.blockedRequests, hasLength(4));
      expect(probe.productionAttempts, 2);
      // Even swallowing an exception cannot make an unexpected request pass.
      expect(probe.assertNoRequests, throwsA(isA<TestFailure>()));
    },
  );

  test('production WebSocket handshake fails before network I/O', () async {
    final probe = TestNetworkGuard();
    await HttpOverrides.runWithHttpOverrides(() async {
      await expectLater(
        WebSocket.connect('wss://kartar.kelolakasir.id/socket.io/'),
        throwsStateError,
      );
    }, GuardHttpOverrides(probe));
    expect(probe.productionAttempts, 1);
  });

  test(
    'a caught transport exception still reports a test failure immediately',
    () {
      final failures = <Object>[];
      final probe = TestNetworkGuard(
        onViolation: (error, stack) {
          failures.add(error);
        },
      );
      try {
        probe.createHttpClient(null).getUrl(Uri.parse('https://example.com/'));
      } on StateError {
        // Model an application catch-all, which must not hide the failure signal.
      }
      expect(failures.single, isA<TestFailure>());
      expect(probe.assertNoRequests, throwsA(isA<TestFailure>()));
    },
  );
}
