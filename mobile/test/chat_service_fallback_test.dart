import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/services/chat_service.dart';
import 'chat_tenant_isolation_test.dart' show MemorySocket;

class AckSocket extends MemorySocket {
  bool loseAck = false;
  @override
  void emitWithAck(
    String event,
    dynamic data, {
    Function? ack,
    bool binary = false,
  }) {
    sent.add([event, data]);
    if (!loseAck) ack?.call(null, {'success': true, 'message': stored(data)});
  }
}

Map<String, dynamic> stored(Map data) => {
  'id': 123,
  'karang_taruna_id': 101,
  'sender_id': 10,
  'type': data['type'],
  'receiver_id': data['receiver_id'],
  'chat_room_id': data['chat_room_id'],
  'message': data['message'],
  'client_message_id': data['client_message_id'],
  'created_at': '2026-10-05T00:00:00Z',
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final service = ChatService();
  setUp(() {
    service.closeConnection();
    SharedPreferences.setMockInitialValues({
      'auth_token': 'synthetic',
      'karang_taruna_id': 101,
      'nama_organisasi': 'Synthetic',
    });
  });
  tearDown(service.closeConnection);
  Future<AckSocket> connect() async {
    final socket = AckSocket();
    await service.initWebSocket(socketFactory: (_, _) => socket);
    socket.receive('auth_success');
    return socket;
  }

  http.Response response(Map data) =>
      http.Response(jsonEncode({'status': true, 'data': stored(data)}), 200);

  test('disconnected send uses one UUID on REST', () async {
    var calls = 0;
    final client = MockClient((request) async {
      calls++;
      final data = jsonDecode(request.body) as Map;
      expect(
        data['client_message_id'],
        matches(
          RegExp(
            r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
          ),
        ),
      );
      expect(request.headers['X-Karang-Taruna-ID'], '101');
      return response(data);
    });
    addTearDown(client.close);
    final result = await http.runWithClient(
      () => service.sendMessage('Synthetic', type: 'private', receiverId: 20),
      () => client,
    );
    expect(calls, 1);
    expect(result?.id, 123);
  });

  test('persisted socket ACK completes without REST', () async {
    final socket = await connect();
    final client = MockClient((_) async => throw StateError('Unexpected REST'));
    addTearDown(client.close);
    final result = await http.runWithClient(
      () => service.sendMessage('Socket', type: 'private', receiverId: 20),
      () => client,
    );
    expect(result?.id, 123);
    expect(result?.clientMessageId, socket.sent.last[1]['client_message_id']);
  });

  test('lost ACK retries REST with exactly the same logical ID', () async {
    final socket = await connect();
    socket.loseAck = true;
    final client = MockClient((request) async {
      final data = jsonDecode(request.body) as Map;
      expect(
        data['client_message_id'],
        socket.sent.last[1]['client_message_id'],
      );
      return response(data);
    });
    addTearDown(client.close);
    final result = await http.runWithClient(
      () => service.sendMessage(
        'Lost ACK',
        type: 'private',
        receiverId: 20,
        acknowledgementTimeout: const Duration(milliseconds: 5),
      ),
      () => client,
    );
    expect(result?.id, 123);
  });

  test(
    'failed send and reconnect retain ID until confirmed, then next send gets a new ID',
    () async {
      final ids = <String>[];
      var fail = true;
      final client = MockClient((request) async {
        final data = jsonDecode(request.body) as Map;
        ids.add(data['client_message_id']);
        return fail ? http.Response('{}', 503) : response(data);
      });
      addTearDown(client.close);
      await http.runWithClient(() async {
        expect(
          await service.sendMessage('Retry', type: 'private', receiverId: 20),
          isNull,
        );
        service.closeConnection();
        fail = false;
        expect(
          (await service.sendMessage(
            'Retry',
            type: 'private',
            receiverId: 20,
          ))?.id,
          123,
        );
        await service.sendMessage('Retry', type: 'private', receiverId: 20);
      }, () => client);
      expect(ids[0], ids[1]);
      expect(ids[1], isNot(ids[2]));
    },
  );
}
