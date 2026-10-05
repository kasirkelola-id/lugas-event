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
  Future<Chat?> sendMessageViaApi(
    String message, {
    String type = 'group',
    int? receiverId,
    int? chatRoomId,
  }) async {
    try {
      final response = await ApiClient.post('/chats/messages', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
        'chat_room_id': chatRoomId,
      });
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

  // Keep WebSocket send as primary, fallback to REST if disconnected
  Future<Chat?> sendMessage(
    String message, {
    String type = 'group',
    int? receiverId,
    int? chatRoomId,
  }) async {
    final tenant = await AuthStorage.getTenant();
    final token = await AuthStorage.getToken();
    if (_socket != null &&
        (_socketTenant != tenant?['id'] || _socketToken != token)) {
      closeConnection();
    }
    bool canSocketSend = false;
    if (_socket != null && _socket!.connected && _isAuthenticated) {
      if (type == 'private') {
        canSocketSend = true;
      } else if (type == 'group' &&
          chatRoomId != null &&
          _joinedRooms.contains(chatRoomId)) {
        canSocketSend = true;
      }
    }

    if (canSocketSend) {
      _socket!.emit('send_message', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
        'chat_room_id': chatRoomId,
      });
      // Do NOT trigger REST API here if socket is connected. Node.js server will handle DB insertion and FCM trigger.
      return null; // Local UI will wait for websocket broadcast
    } else {
      // Fallback to REST API if socket not connected
      return await sendMessageViaApi(
        message,
        type: type,
        receiverId: receiverId,
        chatRoomId: chatRoomId,
      );
    }
  }

  void closeConnection() {
    _initialization++;
    _contextGeneration++;
    if (_socket != null) {
      _socket!.clearListeners();
      _socket!.disconnect();
      _socket!.dispose();
      _socket = null;
    }
    _isAuthenticated = false;
    _socketTenant = null;
    _socketToken = null;
    _activeRoomId = null;
    _joinedRooms.clear();
  }

  // REST API: Get Rooms
  Future<List<ChatRoom>> getRooms() async {
    try {
      final response = await ApiClient.get('/chats/rooms');
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
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
