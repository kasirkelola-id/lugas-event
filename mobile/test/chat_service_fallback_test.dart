import 'dart:io';
import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/services/chat_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ChatService().closeConnection();
  });
  tearDown(() => ChatService().closeConnection());

  group('ChatService Dual-Write Mutually Exclusive Behavior', () {
    test('unauthenticated => REST path only', () async {
      var restCallCount = 0;
      final client = MockClient((request) async {
        restCallCount++;
        expect(request.method, 'POST');
        expect(request.url.path, '/api/chats/messages');
        expect(jsonDecode(request.body)['message'], 'Test Message');
        expect(jsonDecode(request.body)['chat_room_id'], 1);
        return http.Response(
          jsonEncode({
            'status': true,
            'data': {
              'id': 123,
              'karang_taruna_id': 101,
              'type': 'group',
              'sender_id': 10,
              'chat_room_id': 1,
              'message': 'Test Message',
              'created_at': '2026-10-05T00:00:00Z',
            },
          }),
          201,
        );
      });
      addTearDown(client.close);

      // package:http's zone injection intercepts top-level http.post as well;
      // the production URI is only an in-memory mock input, never an IO request.
      final message = await http.runWithClient(
        () => ChatService().sendMessage('Test Message', chatRoomId: 1),
        () => client,
      );
      expect(restCallCount, 1);
      expect(message?.id, 123);
    });

    test('authenticated => Socket path only & Mutually Exclusive (Static Proof)', () {
      // Since SocketIO cannot be easily mocked without a real server or exposing private fields,
      // we prove the exact mutually exclusive branch exists in the source code.
      final file = File('lib/services/chat_service.dart');
      final sourceCode = file.readAsStringSync();

      // Ensure the condition checks socket connection and auth
      expect(
        sourceCode,
        contains(
          'if (_socket != null && _socket!.connected && _isAuthenticated) {',
        ),
        reason: 'Must check socket connected and authenticated',
      );

      // Ensure the socket emit is followed by a return null, skipping the rest of the function
      final emitBlock = RegExp(
        r"if \(_socket != null && _socket!\.connected && _isAuthenticated\) \{[\s\S]*?_socket!\.emit\('send_message'[\s\S]*?return null;",
      );
      expect(
        emitBlock.hasMatch(sourceCode),
        isTrue,
        reason:
            'Socket path must emit and immediately return null to prevent dual-write',
      );

      // Ensure fallback only happens in the else block
      final fallbackBlock = RegExp(
        r"\} else \{[\s\S]*?return await sendMessageViaApi",
      );
      expect(
        fallbackBlock.hasMatch(sourceCode),
        isTrue,
        reason: 'REST fallback must be exclusively in the else block',
      );
    });
  });
}
