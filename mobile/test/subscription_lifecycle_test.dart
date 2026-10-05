import 'dart:async';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/subscription_lifecycle.dart';

void main() {
  test(
    'concurrent initialization has one listener set and later login refreshes context',
    () async {
      final lifecycle = SubscriptionLifecycle();
      final gate = Completer<void>();
      var setupCount = 0;
      var refreshCount = 0;
      var deliveries = 0;
      final streams = List.generate(
        3,
        (_) => StreamController<int>.broadcast(),
      );
      Future<void> setup() async {
        setupCount++;
        await gate.future;
        for (final stream in streams) {
          lifecycle.track(stream.stream.listen((_) => deliveries++));
        }
      }

      Future<void> refresh() async {
        refreshCount++;
      }

      final first = lifecycle.initialize(setup, refresh);
      final second = lifecycle.initialize(setup, refresh);
      gate.complete();
      await Future.wait([first, second]);
      await lifecycle.initialize(setup, refresh);
      for (final stream in streams) {
        stream.add(1);
      }
      await Future<void>.delayed(Duration.zero);
      expect(setupCount, 1);
      expect(refreshCount, 2);
      expect(deliveries, 3);
      for (final stream in streams) {
        await stream.close();
      }
    },
  );

  test(
    'partial failed setup cancels its subscriptions before a clean retry',
    () async {
      final lifecycle = SubscriptionLifecycle();
      var cancellations = 0;
      var deliveries = 0;
      final stream = StreamController<int>.broadcast(
        onCancel: () => cancellations++,
      );
      await expectLater(
        lifecycle.initialize(() async {
          lifecycle.track(stream.stream.listen((_) => deliveries++));
          throw StateError('Synthetic setup failure');
        }, () async {}),
        throwsStateError,
      );
      expect(cancellations, 1);
      await lifecycle.initialize(() async {
        lifecycle.track(stream.stream.listen((_) => deliveries++));
      }, () async {});
      stream.add(1);
      await Future<void>.delayed(Duration.zero);
      expect(deliveries, 1);
      await stream.close();
    },
  );

  test(
    'registration failure retries registration without duplicating healthy listeners',
    () async {
      final lifecycle = SubscriptionLifecycle();
      var setups = 0;
      await expectLater(
        lifecycle.initialize(
          () async {
            setups++;
          },
          () async {
            throw StateError('Synthetic registration failure');
          },
        ),
        throwsStateError,
      );
      await lifecycle.initialize(() async {
        setups++;
      }, () async {});
      expect(setups, 1);
    },
  );
}
