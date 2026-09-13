import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/utils/chat_id_deduplicator.dart';

void main() {
  group('ChatIdDeduplicator', () {
    test('keeps one bubble when realtime and history contain the same server ID', () {
      final dedup = ChatIdDeduplicator();

      expect(dedup.add(123), isTrue); // realtime
      expect(dedup.add(123), isFalse); // history refresh
      expect(dedup.add(123), isFalse); // repeated Socket event
    });

    test('keeps different server IDs even when their text/time would match', () {
      final dedup = ChatIdDeduplicator();

      expect(dedup.add(123), isTrue);
      expect(dedup.add(124), isTrue);
    });
  });
}
