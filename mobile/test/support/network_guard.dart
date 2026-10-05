import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Test-only transport boundary. Never constructs or delegates to a real client.
class TestNetworkGuard {
  TestNetworkGuard({this.onViolation});

  final void Function(Object, StackTrace)? onViolation;
  final List<Uri> blockedRequests = [];

  int get productionAttempts => blockedRequests.where((uri) {
    final host = uri.host.toLowerCase().replaceFirst(RegExp(r'\.$'), '');
    return host == 'kartar.kelolakasir.id' ||
        host.endsWith('.kartar.kelolakasir.id');
  }).length;

  HttpClient createHttpClient(SecurityContext? context) =>
      NoNetworkHttpClient(this);

  Never block(Uri uri) {
    blockedRequests.add(uri);
    onViolation?.call(
      TestFailure('Unexpected test network request: $uri'),
      StackTrace.current,
    );
    throw StateError('Test network I/O blocked before connection: $uri');
  }

  // ApiClient and other services catch exceptions. A caught guard violation must
  // still fail the test, rather than look like a successfully handled outage.
  void assertNoRequests() {
    expect(
      blockedRequests,
      isEmpty,
      reason: 'Tests must use fake transports; network requests are forbidden',
    );
  }
}

class GuardHttpOverrides extends HttpOverrides {
  GuardHttpOverrides(this.guard);
  final TestNetworkGuard guard;

  @override
  HttpClient createHttpClient(SecurityContext? context) =>
      guard.createHttpClient(context);

  // dart:io caches a default WebSocket HttpClient before test startup. Its
  // proxy resolver consults the current overrides before opening a connection,
  // so this also blocks that already-created client's handshake before DNS/I/O.
  @override
  String findProxyFromEnvironment(Uri url, Map<String, String>? environment) =>
      guard.block(url);
}

class NoNetworkHttpClient implements HttpClient {
  NoNetworkHttpClient(this.guard);
  final TestNetworkGuard guard;

  @override
  Future<HttpClientRequest> openUrl(String method, Uri url) => guard.block(url);

  @override
  Future<HttpClientRequest> getUrl(Uri url) => openUrl('GET', url);
  @override
  Future<HttpClientRequest> postUrl(Uri url) => openUrl('POST', url);
  @override
  Future<HttpClientRequest> putUrl(Uri url) => openUrl('PUT', url);
  @override
  Future<HttpClientRequest> patchUrl(Uri url) => openUrl('PATCH', url);
  @override
  Future<HttpClientRequest> deleteUrl(Uri url) => openUrl('DELETE', url);
  @override
  Future<HttpClientRequest> headUrl(Uri url) => openUrl('HEAD', url);

  @override
  Future<HttpClientRequest> open(
    String method,
    String host,
    int port,
    String path,
  ) => openUrl(method, Uri(scheme: 'http', host: host, port: port, path: path));

  @override
  Future<HttpClientRequest> get(String host, int port, String path) =>
      open('GET', host, port, path);
  @override
  Future<HttpClientRequest> post(String host, int port, String path) =>
      open('POST', host, port, path);
  @override
  Future<HttpClientRequest> put(String host, int port, String path) =>
      open('PUT', host, port, path);
  @override
  Future<HttpClientRequest> patch(String host, int port, String path) =>
      open('PATCH', host, port, path);
  @override
  Future<HttpClientRequest> delete(String host, int port, String path) =>
      open('DELETE', host, port, path);
  @override
  Future<HttpClientRequest> head(String host, int port, String path) =>
      open('HEAD', host, port, path);

  @override
  void close({bool force = false}) {}

  // Configuration setters used by HTTP/WebSocket clients need no backing
  // sockets. Unsupported operations fail rather than opening a connection.
  @override
  dynamic noSuchMethod(Invocation invocation) {
    if (invocation.isSetter) return null;
    return super.noSuchMethod(invocation);
  }
}
