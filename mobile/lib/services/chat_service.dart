import 'dart:math';
import 'dart:convert';
import 'dart:async';
import 'package:socket_io_client/socket_io_client.dart' as IO;
import '../models/chat_model.dart';
import '../models/chat_room_model.dart';
import '../core/network/api_client.dart';
import '../storage/auth_storage.dart';

import '../core/config/api_config.dart';
import 'package:flutter/foundation.dart';

class ChatService {
  static final ChatService _instance = ChatService._internal();
  factory ChatService() => _instance;
  ChatService._internal();

  IO.Socket? _socket;
  IO.Socket? get socket => _socket;
  final _socketChanges = StreamController<IO.Socket?>.broadcast();
  Stream<IO.Socket?> get socketChanges => _socketChanges.stream;
  bool get isAuthenticated => _isAuthenticated;
  int? get activeTenantId => _socketTenant;

  final StreamController<Chat> _messageStreamController =
      StreamController<Chat>.broadcast();
  Stream<Chat> get messageStream => _messageStreamController.stream;

  Function()? onAuthSuccess;

  bool _isAuthenticated = false;
  int? _activeRoomId;
  final Set<int> _joinedRooms = {};
  int? _socketTenant;
  String? _socketToken;
  int _contextGeneration = 0;
  int _initialization = 0;
  final Map<String, String> _pendingIds = {};
  String? _pendingToken;
  int? _pendingTenant;

  static String _messageId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes
        .map((byte) => byte.toRadixString(16).padLeft(2, '0'))
        .join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  bool isCurrentTenant(Chat chat) =>
      _isAuthenticated && chat.karangTarunaId == _socketTenant;

  Future<void> switchTenant(
    int id,
    String name, {
    String? logoUrl,
    IO.Socket Function(String, Map<String, dynamic>)? socketFactory,
  }) async {
    closeConnection();
    await AuthStorage.saveTenant(id, name, logoUrl: logoUrl);
    await initWebSocket(socketFactory: socketFactory);
  }

  // Initialize WebSocket connection
  // The optional factory provides an in-memory transport for tests.
  Future<void> initWebSocket({
    IO.Socket Function(String, Map<String, dynamic>)? socketFactory,
  }) async {
    final initialization = ++_initialization;
    final token = await AuthStorage.getToken();
    final tenant = await AuthStorage.getTenant();
    if (initialization != _initialization) return;
    if (token == null || tenant == null) {
      closeConnection();
      return;
    }
    final ktId = tenant['id'] as int;
    final sameContext = _socketTenant == ktId && _socketToken == token;
    if (sameContext && _socket?.connected == true) return;
    final previousRoom = sameContext ? _activeRoomId : null;
    closeConnection();
    _activeRoomId = previousRoom;
    _socketTenant = ktId;
    _socketToken = token;
    final generation = _contextGeneration;
    final IO.Socket socket = (socketFactory ?? IO.io)(
      ApiConfig.socketBaseUrl,
      IO.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .disableAutoConnect()
          .enableReconnection()
          .enableForceNew()
          .setPath('/socket.io/')
          .build(),
    );
    _socket = socket;
    _socketChanges.add(socket);
    bool current() =>
        generation == _contextGeneration && identical(_socket, socket);

    socket.onConnect((_) async {
      if (!current()) return;
      _isAuthenticated = false;
      final currentToken = await AuthStorage.getToken();
      final currentTenant = await AuthStorage.getTenant();
      if (!current()) return;
      if (currentToken != token || currentTenant?['id'] != ktId) {
        // A reconnect must not authenticate captured credentials from an old tenant.
        await initWebSocket(socketFactory: socketFactory);
        return;
      }
      debugPrint('Socket.io connected');
      // Send authentication payload with opaque bearer token
      socket.emit('auth', {
        'token': currentToken,
        'tenant_id': currentTenant!['id'],
      });
    });

    // Prevent duplicate listeners by clearing first
    _socket!.off('auth_success');
    _socket!.off('auth_error');
    _socket!.off('connect_error');
    _socket!.off('error');
    _socket!.off('new_message');
    _socket!.off('room_joined');
    _socket!.off('disconnect');

    _socket!.on('auth_success', (_) {
      if (!current()) return;
      debugPrint('Socket.io authenticated successfully');
      _isAuthenticated = true;
      if (_activeRoomId != null) {
        _socket!.emit('join_room', {'room_id': _activeRoomId});
      }
      if (onAuthSuccess != null) {
        onAuthSuccess!();
      }
    });

    _socket!.on('auth_error', (data) {
      if (!current()) return;
      debugPrint('Socket.io authentication failed');
      _isAuthenticated = false;
    });

    _socket!.onConnectError((data) {
      if (!current()) return;
      debugPrint('Socket.io connection failed');
      _isAuthenticated = false;
    });

    _socket!.on('error', (data) {
      debugPrint('Socket.io request failed');
    });

    // Listen to incoming messages
    _socket!.on('new_message', (data) async {
      try {
        if (!current() || !_isAuthenticated) return;
        final chat = Chat.fromJson(data);
        if (!isCurrentTenant(chat)) return;
        final activeTenant = await AuthStorage.getTenant();
        if (!current() ||
            !isCurrentTenant(chat) ||
            chat.karangTarunaId != activeTenant?['id']) {
          return;
        }
        _messageStreamController.add(chat);
      } catch (e) {
        debugPrint("Chat response invalid");
      }
    });

    _socket!.on('room_joined', (data) {
      if (!current()) return;
      if (data['room_id'] != null) {
        _joinedRooms.add(data['room_id'] as int);
      }
    });

    _socket!.onDisconnect((_) {
      if (!current()) return;
      debugPrint('Socket.io disconnected');
      _isAuthenticated = false;
      _joinedRooms.clear();
    });
    // Register handlers before connecting (also safe for synchronous mocks).
    socket.connect();
  }

  void joinRoom(int roomId) {
    _activeRoomId = roomId;
    if (_socket != null && _socket!.connected && _isAuthenticated) {
      _socket!.emit('join_room', {'room_id': roomId});
    }
  }

  // Send message via REST API (Triggers FCM)
  Future<Chat?> _sendMessageViaApi(
    String message, {
    String type = 'group',
    int? receiverId,
    int? chatRoomId,
    required String clientId,
    required Map<String, String> headers,
  }) async {
    try {
      final response = await ApiClient.post('/chats/messages', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
        'chat_room_id': chatRoomId,
        'client_message_id': clientId,
      }, requestHeaders: headers);
      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = json.decode(response.body);
        if (data['data'] != null) {
          return Chat.fromJson(data['data']);
        }
      }
      return null;
    } catch (e) {
      debugPrint("Chat request failed");
      return null;
    }
  }

  // One logical ID survives a lost ACK and an explicit same-message retry.
  Future<Chat?> sendMessage(
    String message, {
    String type = 'group',
    int? receiverId,
    int? chatRoomId,
    Duration acknowledgementTimeout = const Duration(seconds: 5),
  }) async {
    if (message.trim().isEmpty || message.runes.length > 2000) return null;
    final tenant = await AuthStorage.getTenant();
    final token = await AuthStorage.getToken();
    final tenantId = tenant?['id'] as int?;
    if (_pendingToken != token || _pendingTenant != tenantId) {
      _pendingIds.clear();
      _pendingToken = token;
      _pendingTenant = tenantId;
    }
    final key = jsonEncode([type, receiverId, chatRoomId, message]);
    if (!_pendingIds.containsKey(key) && _pendingIds.length >= 50) return null;
    final clientId = _pendingIds.putIfAbsent(key, _messageId);
    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
      if (tenantId != null) 'X-Karang-Taruna-ID': '$tenantId',
    };
    Future<bool> current() async =>
        await AuthStorage.getToken() == token &&
        (await AuthStorage.getTenant())?['id'] == tenantId;
    if (_socket != null &&
        (_socketTenant != tenantId || _socketToken != token)) {
      closeConnection();
    }
    final canSend =
        _socket?.connected == true &&
        _isAuthenticated &&
        (type == 'private' ||
            (chatRoomId != null && _joinedRooms.contains(chatRoomId)));
    Chat? result;
    if (canSend) {
      final ack = Completer<Chat?>();
      try {
        _socket!
            .timeout(acknowledgementTimeout.inMilliseconds)
            .emitWithAck(
              'send_message',
              {
                'type': type,
                'message': message,
                'receiver_id': receiverId,
                'chat_room_id': chatRoomId,
                'client_message_id': clientId,
              },
              ack: (dynamic error, [dynamic response]) {
                if (ack.isCompleted) return;
                try {
                  if (error == null &&
                      response is Map &&
                      response['success'] == true &&
                      response['message'] is Map &&
                      response['message']['client_message_id'] == clientId) {
                    ack.complete(
                      Chat.fromJson(
                        Map<String, dynamic>.from(response['message']),
                      ),
                    );
                  } else {
                    ack.complete(null);
                  }
                } catch (_) {
                  ack.complete(null);
                }
              },
            );
      } catch (_) {
        if (!ack.isCompleted) ack.complete(null);
      }
      result = await ack.future.timeout(
        acknowledgementTimeout,
        onTimeout: () => null,
      );
    }
    if (!await current()) return null;
    result ??= await _sendMessageViaApi(
      message,
      type: type,
      receiverId: receiverId,
      chatRoomId: chatRoomId,
      clientId: clientId,
      headers: headers,
    );
    if (!await current() ||
        (result != null &&
            tenantId != null &&
            result.karangTarunaId != tenantId)) {
      return null;
    }
    if (result != null) _pendingIds.remove(key);
    return result;
  }

  void closeConnection() {
    _initialization++;
    _contextGeneration++;
    if (_socket != null) {
      _socket!.clearListeners();
      _socket!.disconnect();
      _socket!.dispose();
      _socket = null;
      _socketChanges.add(null);
    }
    _isAuthenticated = false;
    _socketTenant = null;
    _socketToken = null;
    _activeRoomId = null;
    _joinedRooms.clear();
  }

  // REST API: Get Rooms
  Map<String, dynamic>? roomsPagination;
  Future<List<ChatRoom>> getRooms({int page = 1}) async {
    roomsPagination = null;
    try {
      final response = await ApiClient.get('/chats/rooms?page=$page&limit=50');
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        roomsPagination = data['pagination'];
        return (data['data'] as List).map((c) => ChatRoom.fromJson(c)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // REST API: Create Room
  Future<Map<String, dynamic>> createRoom(
    String name,
    List<int> memberIds,
  ) async {
    try {
      final response = await ApiClient.post('/chats/rooms', {
        'name': name,
        'members': memberIds,
      });
      final data = json.decode(response.body);
      return {
        'success': data['status'] == true,
        'message': data['message'] ?? '',
      };
    } catch (e) {
      return {'success': false, 'message': 'Kesalahan jaringan'};
    }
  }

  // REST API: Delete Room
  Future<bool> deleteRoom(int roomId) async {
    try {
      final response = await ApiClient.delete('/chats/rooms/$roomId');
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  // REST API: Get Room Chat History
  Future<List<Chat>> getRoomChatHistory(
    int roomId, {
    int? beforeId,
    int? limit,
  }) async {
    try {
      String url = '/chats/rooms/$roomId/messages';
      final query = <String>[];
      if (beforeId != null) query.add('before_id=$beforeId');
      if (limit != null) query.add('limit=$limit');
      if (query.isNotEmpty) {
        url += '?${query.join('&')}';
      }
      final response = await ApiClient.get(url);
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return (data['data'] as List).map((c) => Chat.fromJson(c)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // REST API: Get Private Chat History
  Future<List<Chat>> getPrivateChatHistory(
    int receiverId, {
    int? beforeId,
    int? limit,
  }) async {
    try {
      String url = '/chats/private/$receiverId';
      final query = <String>[];
      if (beforeId != null) query.add('before_id=$beforeId');
      if (limit != null) query.add('limit=$limit');
      if (query.isNotEmpty) {
        url += '?${query.join('&')}';
      }
      final response = await ApiClient.get(url);
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return (data['data'] as List).map((c) => Chat.fromJson(c)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // REST API: Get Private Chat Contacts
  Future<List<Map<String, dynamic>>> getPrivateContacts({
    int? limit,
    int offset = 0,
  }) async {
    try {
      final query = <String>[];
      if (limit != null) query.add('limit=$limit');
      if (offset > 0) query.add('offset=$offset');
      final suffix = query.isEmpty ? '' : '?${query.join('&')}';
      final response = await ApiClient.get('/chats/private-contacts$suffix');
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == true && data['data'] != null) {
          return List<Map<String, dynamic>>.from(data['data']);
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }
}
