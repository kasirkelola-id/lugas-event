import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/models/wheel_model.dart';
import 'package:mobile/screens/widgets/common/wheel_banner.dart';

class MockHttpOverrides extends HttpOverrides {
  final Map<String, dynamic> wheelsResponse;
  final Map<String, dynamic> detailsResponse;

  MockHttpOverrides({
    required this.wheelsResponse,
    required this.detailsResponse,
  });

  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return _MockHttpClient(wheelsResponse, detailsResponse);
  }
}

class _MockHttpClient implements HttpClient {
  final Map<String, dynamic> wheelsResponse;
  final Map<String, dynamic> detailsResponse;

  _MockHttpClient(this.wheelsResponse, this.detailsResponse);

  @override
  void close({bool force = false}) {}

  @override
  dynamic noSuchMethod(Invocation invocation) {
    if (invocation.memberName == #openUrl) {
      final url = invocation.positionalArguments[1] as Uri;
      return Future.value(
        _MockHttpClientRequest(url, wheelsResponse, detailsResponse),
      );
    }
    return super.noSuchMethod(invocation);
  }
}

class _MockHttpClientRequest implements HttpClientRequest {
  final Uri url;
  final Map<String, dynamic> wheelsResponse;
  final Map<String, dynamic> detailsResponse;

  _MockHttpClientRequest(this.url, this.wheelsResponse, this.detailsResponse);

  @override
  set followRedirects(bool follow) {}

  @override
  set maxRedirects(int max) {}

  @override
  set contentLength(int length) {}

  @override
  Future<HttpClientResponse> close() {
    String responseBody = jsonEncode({'success': false});
    if (url.path.endsWith('/wheels')) {
      responseBody = jsonEncode(wheelsResponse);
    } else if (url.path.contains(RegExp(r'/wheels/\d+'))) {
      responseBody = jsonEncode(detailsResponse);
    }
    return Future.value(_MockHttpClientResponse(responseBody, 200));
  }

  @override
  dynamic noSuchMethod(Invocation invocation) {
    if (invocation.memberName == #headers) {
      return _MockHttpHeaders();
    }
    return super.noSuchMethod(invocation);
  }
}

class _MockHttpHeaders implements HttpHeaders {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _MockHttpClientResponse implements HttpClientResponse {
  final String body;
  final int statusCode;
  _MockHttpClientResponse(this.body, this.statusCode);

  @override
  dynamic noSuchMethod(Invocation invocation) {
    if (invocation.memberName == #transform) {
      return Stream.value(utf8.encode(body));
    }
    return super.noSuchMethod(invocation);
  }
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({
      'token': 'mock_token',
      'karang_taruna_id': 1,
    });
  });

  group('WheelBanner Widget Tests', () {
    testWidgets('no active Wheel -> banner hidden', (
      WidgetTester tester,
    ) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {'success': true, 'data': []},
        detailsResponse: {'success': false},
      );

      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: WheelBanner(currentUserId: 1))),
      );
      await tester.pumpAndSettle();

      expect(find.byType(SizedBox), findsWidgets);
      expect(find.text('Lihat Undian'), findsNothing);
    });

    testWidgets('one active Wheel -> banner visible (general label)', (
      WidgetTester tester,
    ) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {
              'id': 1,
              'karang_taruna_id': 1,
              'created_by_user_id': 2,
              'title': 'Undian Test',
              'status': 'active',
              'item_count': 5,
              'creator_id': 2,
            },
          ],
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [],
          },
        },
      );

      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: WheelBanner(currentUserId: 1))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Undian sedang berlangsung'), findsOneWidget);
      expect(find.text('Undian Test'), findsOneWidget);
      expect(find.text('5 Kandidat'), findsOneWidget);
      expect(find.text('Undian aktif'), findsOneWidget);
    });

    testWidgets('creator -> label "Undian Anda sedang berlangsung"', (
      WidgetTester tester,
    ) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {
              'id': 1,
              'karang_taruna_id': 1,
              'created_by_user_id': 1,
              'title': 'Undian Test',
              'status': 'active',
              'item_count': 5,
              'creator_id': 1,
            },
          ],
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [],
          },
        },
      );

      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: WheelBanner(currentUserId: 1))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Undian Anda sedang berlangsung'), findsOneWidget);
    });

    testWidgets('wheel spinning -> status text changes', (
      WidgetTester tester,
    ) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {
              'id': 1,
              'title': 'Undian Test',
              'status': 'active',
              'creator_id': 1,
            },
          ],
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [
              {
                'id': 1,
                'started_at': DateTime.now().toIso8601String(),
                'duration_seconds': 10,
              },
            ],
          },
        },
      );

      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: WheelBanner(currentUserId: 1))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Sedang berputar...'), findsOneWidget);
    });

    testWidgets('wheel result available -> status text changes', (
      WidgetTester tester,
    ) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {
              'id': 1,
              'title': 'Undian Test',
              'status': 'active',
              'creator_id': 1,
            },
          ],
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [
              {
                'id': 1,
                'started_at': DateTime.now()
                    .subtract(const Duration(seconds: 20))
                    .toIso8601String(),
                'duration_seconds': 10,
              },
            ],
          },
        },
      );

      await tester.pumpWidget(
        MaterialApp(home: Scaffold(body: WheelBanner(currentUserId: 1))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Pemenang baru saja dipilih'), findsOneWidget);
    });

    testWidgets('multiple sessions -> shows +N lainnya', (WidgetTester tester) async {
      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {'id': 1, 'title': 'Undian Test', 'status': 'active', 'creator_id': 1},
            {'id': 2, 'title': 'Undian Test 2', 'status': 'active', 'creator_id': 1},
            {'id': 3, 'title': 'Undian Test 3', 'status': 'active', 'creator_id': 1},
          ]
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [],
          }
        },
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: WheelBanner(currentUserId: 1),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('+2 Undian lainnya'), findsOneWidget);
    });

    testWidgets('tenant switch -> recreates widget and uses new context', (WidgetTester tester) async {
      // 1. Initial Tenant A (has active wheel)
      SharedPreferences.setMockInitialValues({
        'token': 'mock_token',
        'karang_taruna_id': 1,
      });

      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {
          'success': true,
          'data': [
            {'id': 1, 'title': 'Undian Tenant A', 'status': 'active', 'creator_id': 1},
          ]
        },
        detailsResponse: {
          'success': true,
          'data': {
            'session': {'id': 1, 'status': 'active'},
            'items': [],
            'results': [],
          }
        },
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: WheelBanner(key: const ValueKey('tenant_1'), currentUserId: 1),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Undian Tenant A'), findsOneWidget);

      // 2. Switch to Tenant B (no active wheel)
      SharedPreferences.setMockInitialValues({
        'token': 'mock_token',
        'karang_taruna_id': 2,
      });

      HttpOverrides.global = MockHttpOverrides(
        wheelsResponse: {'success': true, 'data': []},
        detailsResponse: {'success': false},
      );

      // 3. Recreate widget (simulate pushAndRemoveUntil behavior)
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: WheelBanner(key: const ValueKey('tenant_2'), currentUserId: 1),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // 4. Banner A should disappear, and no new banner should be shown
      expect(find.text('Undian Tenant A'), findsNothing);
      expect(find.byType(SizedBox), findsWidgets);
    });
  });
}
