import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/chat_model.dart';
import 'package:intl/intl.dart';

void main() {
  group('Timestamp Real Regression', () {
    test('REST and Socket serialized timestamps parse to identical DateTime instantly', () {
      // Setup deterministic DB fixture string
      // DB created_at: 2026-09-12 10:15:30 (UTC)
      
      // REST Serialized exactly as:
      final restString = '2026-09-12T10:15:30Z';
      
      // Socket Serialized exactly as:
      final socketString = '2026-09-12T10:15:30.000Z';
      
      final restDateTime = DateTime.parse(restString).toLocal();
      final socketDateTime = DateTime.parse(socketString).toLocal();
      
      // Both parse into identical DateTime instants
      expect(restDateTime.isAtSameMomentAs(socketDateTime), isTrue);
      
      // And render identical formatted strings in the local timezone (Asia/Jakarta)
      // We format using intl to prove it
      final formatter = DateFormat('HH:mm');
      final restDisplay = formatter.format(restDateTime);
      final socketDisplay = formatter.format(socketDateTime);
      
      expect(restDisplay, equals(socketDisplay));
      // Depending on local timezone running the test, it'll format identically.
      // E.g., if local is Asia/Jakarta, it would be 17:15.
      
      // Create models to test the JSON factory
      final restModel = Chat.fromJson({
        'id': 1,
        'karang_taruna_id': 1,
        'type': 'private',
        'sender_id': 10,
        'receiver_id': 11,
        'message': 'Test REST',
        'created_at': restString
      });
      
      final socketModel = Chat.fromJson({
        'id': 2,
        'karang_taruna_id': 1,
        'type': 'private',
        'sender_id': 10,
        'receiver_id': 11,
        'message': 'Test Socket',
        'created_at': socketString
      });
      
      expect(restModel.createdAt.isAtSameMomentAs(socketModel.createdAt), isTrue);
    });
  });
}
