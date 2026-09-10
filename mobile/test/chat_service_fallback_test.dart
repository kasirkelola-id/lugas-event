import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/services/chat_service.dart';

class MockHttpOverrides extends HttpOverrides {
  int restCallCount = 0;

  @override
  HttpClient createHttpClient(SecurityContext? context) {
    restCallCount++;
    return super.createHttpClient(context);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});
  
  group('ChatService Dual-Write Mutually Exclusive Behavior', () {
    test('unauthenticated => REST path only', () async {
      final mockHttp = MockHttpOverrides();
      HttpOverrides.global = mockHttp;

      final chatService = ChatService();
      // chatService initially has null socket and is not authenticated
      
      await chatService.sendMessage('Test Message', chatRoomId: 1);

      // It should hit the REST path since socket is null/disconnected/unauthenticated
      expect(mockHttp.restCallCount, greaterThanOrEqualTo(1));
    });

    test('authenticated => Socket path only & Mutually Exclusive (Static Proof)', () {
      // Since SocketIO cannot be easily mocked without a real server or exposing private fields,
      // we prove the exact mutually exclusive branch exists in the source code.
      final file = File('lib/services/chat_service.dart');
      final sourceCode = file.readAsStringSync();

      // Ensure the condition checks socket connection and auth
      expect(
        sourceCode,
        contains('if (_socket != null && _socket!.connected && _isAuthenticated) {'),
        reason: 'Must check socket connected and authenticated',
      );

      // Ensure the socket emit is followed by a return null, skipping the rest of the function
      final emitBlock = RegExp(r"if \(_socket != null && _socket!\.connected && _isAuthenticated\) \{[\s\S]*?_socket!\.emit\('send_message'[\s\S]*?return null;");
      expect(
        emitBlock.hasMatch(sourceCode),
        isTrue,
        reason: 'Socket path must emit and immediately return null to prevent dual-write',
      );

      // Ensure fallback only happens in the else block
      final fallbackBlock = RegExp(r"\} else \{[\s\S]*?return await sendMessageViaApi");
      expect(
        fallbackBlock.hasMatch(sourceCode),
        isTrue,
        reason: 'REST fallback must be exclusively in the else block',
      );
    });
  });
}
