import 'dart:async';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/chat_model.dart';
import 'package:mobile/services/chat_service.dart';
import 'package:mobile/storage/auth_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:socket_io_client/socket_io_client.dart' as io;
import 'package:socket_io_client/src/manager.dart';

// Real event emitter, in-memory transport: no Manager/Engine network is opened.
class MemorySocket extends io.Socket {
  MemorySocket()
    : super(
        Manager(uri: 'http://127.0.0.1', options: {'autoConnect': false}),
        '/',
        {},
      );
  final sent = <List<dynamic>>[];
  bool disposed = false;
  @override
  io.Socket connect() {
    connected = true;
    return this;
  }

  @override
  void emit(String event, [dynamic data]) {
    sent.add([event, data]);
  }

  @override
  io.Socket disconnect() {
    connected = false;
    return this;
  }

  @override
  void dispose() {
    disposed = true;
    disconnect();
    clearListeners();
  }

  void receive(String event, [dynamic data]) => emitEvent([event, data]);
}

void main() {
  final service = ChatService();
  late MemorySocket socket;
  late List<Chat> received;
  late StreamSubscription<Chat> subscription;
  Future<void> settle() => Future<void>.delayed(Duration.zero);
  Map<String, dynamic> message(int tenant, {String type = 'private'}) => {
    'id': 1,
    'karang_taruna_id': tenant,
    'type': type,
    'sender_id': 10,
    'receiver_id': 20,
    'chat_room_id': 7,
    'message': 'same peer',
    'created_at': '2026-10-05T00:00:00Z',
  };
  Future<void> initialize() async {
    await service.initWebSocket(
      socketFactory: (_, options) {
        socket = MemorySocket();
        return socket;
      },
    );
    socket.receive('connect');
    await settle();
    socket.receive('auth_success');
  }

  setUp(() async {
    service.closeConnection();
    SharedPreferences.setMockInitialValues({
      'auth_token': 'synthetic',
      'karang_taruna_id': 102,
      'nama_organisasi': 'B',
    });
    received = [];
    subscription = service.messageStream.listen(received.add);
    await initialize();
  });
  tearDown(() async {
    service.closeConnection();
    await subscription.cancel();
  });

  test('correct tenant private message reaches stream', () async {
    socket.receive('new_message', message(102));
    await settle();
    expect(received, hasLength(1));
  });
  for (final type in ['private', 'group']) {
    test(
      'wrong tenant $type same peer causes zero stream/UI state mutation',
      () async {
        socket.receive('new_message', message(101, type: type));
        await settle();
        expect(received, isEmpty);
      },
    );
  }
  test(
    'tenant switch replaces connected old socket and clears old group',
    () async {
      service.joinRoom(7);
      final old = socket;
      await AuthStorage.saveTenant(101, 'A');
      await initialize();
      expect(old.disposed, isTrue);
      expect(identical(old, socket), isFalse);
      expect(socket.sent.where((e) => e[0] == 'join_room'), isEmpty);
      expect(
        socket.sent.where((e) => e[0] == 'auth').last[1]['tenant_id'],
        101,
      );
      old.receive('new_message', message(102));
      socket.receive('new_message', message(102));
      socket.receive('new_message', message(101));
      await settle();
      expect(received.map((m) => m.karangTarunaId), [101]);
    },
  );
  test(
    'reconnect reads current tenant rather than captured old tenant',
    () async {
      final old = socket;
      await AuthStorage.saveTenant(101, 'A');
      old.receive('disconnect');
      old.receive('connect');
      await settle();
      expect(old.disposed, isTrue);
      expect(identical(old, socket), isFalse);
      socket.receive('connect');
      await settle();
      expect(
        socket.sent.where((e) => e[0] == 'auth').last[1]['tenant_id'],
        101,
      );
    },
  );
  test(
    'canonical switch disposes before saving tenant and creates fresh manager',
    () async {
      final old = socket;
      await service.switchTenant(
        101,
        'A',
        socketFactory: (_, options) {
          expect(old.disposed, isTrue);
          expect(old.hasListeners('new_message'), isFalse);
          expect(options['forceNew'], isTrue);
          socket = MemorySocket();
          return socket;
        },
      );
      socket.receive('connect');
      await settle();
      socket.receive('auth_success');
      socket.receive('new_message', message(101));
      await settle();
      expect(received.map((m) => m.karangTarunaId), [101]);
    },
  );
  test('notification and selector both use canonical switch', () {
    for (final path in [
      'lib/services/notification_service.dart',
      'lib/screens/auth/tenant_selector_screen.dart',
    ]) {
      final source = File(path).readAsStringSync();
      expect(source, contains('await ChatService().switchTenant('));
      expect(source, isNot(contains('await AuthStorage.saveTenant(')));
    }
  });
  test(
    'queued old callback and stream state cannot survive tenant switch',
    () async {
      socket.receive('new_message', message(102));
      service.closeConnection();
      await AuthStorage.saveTenant(101, 'A');
      await initialize();
      await settle();
      expect(received, isEmpty);
      expect(service.isCurrentTenant(Chat.fromJson(message(102))), isFalse);
    },
  );
  test(
    'storage changed before reinitialization rejects old tenant immediately',
    () async {
      await AuthStorage.saveTenant(101, 'A');
      socket.receive('new_message', message(102));
      await settle();
      expect(received, isEmpty);
    },
  );
  test(
    'same-tenant reconnect reauthenticates and rejoins current group only',
    () async {
      service.joinRoom(7);
      socket.sent.clear();
      socket.receive('disconnect');
      socket.receive('connect');
      await settle();
      expect(socket.sent.single, [
        'auth',
        {'token': 'synthetic', 'tenant_id': 102},
      ]);
      socket.receive('auth_success');
      expect(socket.sent.last, [
        'join_room',
        {'room_id': 7},
      ]);
    },
  );
  test(
    'repeated init in same connected context preserves one listener',
    () async {
      final original = socket;
      await service.initWebSocket(
        socketFactory: (_, options) => throw StateError('duplicate'),
      );
      expect(service.socket, same(original));
      socket.receive('new_message', message(102));
      await settle();
      expect(received, hasLength(1));
    },
  );
  test(
    'disconnected instance is disposed before replacement in same tenant',
    () async {
      final old = socket;
      old.connected = false;
      old.receive('disconnect');
      await initialize();
      expect(old.disposed, isTrue);
      socket.receive('new_message', message(102));
      await settle();
      expect(received, hasLength(1));
    },
  );
  test(
    'logout during initialization cannot resurrect socket context',
    () async {
      service.closeConnection();
      final initializing = service.initWebSocket(
        socketFactory: (_, options) => throw StateError('resurrected'),
      );
      service.closeConnection();
      await AuthStorage.removeToken();
      await initializing;
      expect(service.socket, isNull);
    },
  );
}
