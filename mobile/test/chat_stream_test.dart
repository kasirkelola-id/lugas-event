import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/services/chat_service.dart';

void main() {
  group('ChatService Global Stream', () {
    late ChatService chatService;

    setUp(() {
      chatService = ChatService();
    });

    test('is broadcast stream', () {
      expect(chatService.messageStream.isBroadcast, isTrue);
    });
  });
}
