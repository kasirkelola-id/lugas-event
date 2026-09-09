import 'dart:convert';
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

  Function(Chat)? onMessageReceived;
  Function()? onAuthSuccess;

  // Initialize WebSocket connection
  Future<void> initWebSocket() async {
    final token = await AuthStorage.getToken();
    final tenant = await AuthStorage.getTenant();

    if (token == null || tenant == null) return;

    final ktId = tenant['id'];

    if (_socket != null && _socket!.connected) return;

    _socket = IO.io(
      ApiConfig.socketBaseUrl,
      IO.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .disableAutoConnect()
          .enableReconnection()
          .setPath('/socket.io/')
          .build(),
    );

    _socket!.connect();

    _socket!.onConnect((_) {
      debugPrint('Socket.io connected');
      // Send authentication payload with opaque bearer token
      _socket!.emit('auth', {'token': token, 'tenant_id': ktId});
    });

    // Prevent duplicate listeners by clearing first
    _socket!.off('auth_success');
    _socket!.off('auth_error');
    _socket!.off('connect_error');
    _socket!.off('error');
    _socket!.off('new_message');
    _socket!.off('disconnect');

    _socket!.on('auth_success', (_) {
      debugPrint('Socket.io authenticated successfully');
      if (onAuthSuccess != null) {
        onAuthSuccess!();
      }
    });

    _socket!.on('auth_error', (data) {
      debugPrint('Socket.io auth error: ${data['message']}');
    });

    _socket!.onConnectError((data) {
      debugPrint('Socket.io connect error: $data');
    });

    _socket!.on('error', (data) {
      debugPrint('Socket.io error: ${data['message']}');
    });

    // Listen to incoming messages
    _socket!.on('new_message', (data) {
      try {
        final chat = Chat.fromJson(data);
        if (onMessageReceived != null) {
          onMessageReceived!(chat);
        }
      } catch (e) {
        debugPrint("Error parsing chat: $e");
      }
    });

    _socket!.onDisconnect((_) => debugPrint('Socket.io disconnected'));
  }

  void joinRoom(int roomId) {
    if (_socket != null) {
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
      debugPrint("Error sending message via API: $e");
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
    if (_socket != null && _socket!.connected) {
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
    if (_socket != null) {
      _socket!.disconnect();
      _socket!.dispose();
      _socket = null;
    }
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
  Future<List<Chat>> getRoomChatHistory(int roomId, {int? beforeId}) async {
    try {
      String url = '/chats/rooms/$roomId/messages';
      if (beforeId != null) {
        url += '?before_id=$beforeId';
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
  }) async {
    try {
      String url = '/chats/private/$receiverId';
      if (beforeId != null) {
        url += '?before_id=$beforeId';
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
  Future<List<Map<String, dynamic>>> getPrivateContacts() async {
    try {
      final response = await ApiClient.get('/chats/private-contacts');
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
