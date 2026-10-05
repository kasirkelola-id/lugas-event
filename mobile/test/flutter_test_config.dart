import 'dart:async';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'support/network_guard.dart';

Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  // Initialize first: Flutter's permissive HTTP-400 mock must not replace our
  // stricter boundary. Tests run in runner-owned zones, so install globally.
  TestWidgetsFlutterBinding.ensureInitialized();
  final guard = TestNetworkGuard(
    onViolation: (error, stack) {
      // Report into the test runner immediately, even when ApiClient catches the
      // StateError thrown by the transport. Teardown remains a second check.
      Zone.current.handleUncaughtError(error, stack);
    },
  );
  final previous = HttpOverrides.current;
  HttpOverrides.global = GuardHttpOverrides(guard);
  tearDown(guard.assertNoRequests);
  tearDownAll(() {
    try {
      guard.assertNoRequests();
      // Counters are per test-file isolate. No client here can perform I/O.
      // Intentional negative probes use a separate guard in network_guard_test.
      stdout.writeln(
        'TEST_NETWORK_GUARD production_attempts=${guard.productionAttempts} '
        'transport_attempts=${guard.blockedRequests.length} '
        'external_successful_requests=0',
      );
    } finally {
      HttpOverrides.global = previous;
    }
  });
  // testMain registers tests; it does not execute them. Keep the guard installed
  // until tearDownAll, rather than restoring it when registration finishes.
  await testMain();
}
