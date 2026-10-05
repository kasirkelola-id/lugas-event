import 'dart:async';

/// Shares in-flight setup and retains one owned subscription set across screens.
class SubscriptionLifecycle {
  Future<void>? _initializing;
  bool _ready = false;
  final List<StreamSubscription<dynamic>> _subscriptions = [];

  void track(StreamSubscription<dynamic> subscription) =>
      _subscriptions.add(subscription);

  Future<void> initialize(
    Future<void> Function() setup,
    Future<void> Function() refreshContext,
  ) {
    return _initializing ??= _initialize(setup, refreshContext).whenComplete(
      () {
        _initializing = null;
      },
    );
  }

  Future<void> _initialize(
    Future<void> Function() setup,
    Future<void> Function() refreshContext,
  ) async {
    if (!_ready) {
      try {
        await setup();
        _ready = true;
      } catch (_) {
        final subscriptions = List.of(_subscriptions);
        _subscriptions.clear();
        await Future.wait(
          subscriptions.map((subscription) => subscription.cancel()),
        );
        rethrow;
      }
    }
    // A new login/token still needs registration even though listeners already exist.
    await refreshContext();
  }
}
