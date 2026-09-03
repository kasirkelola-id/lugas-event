import 'dart:convert';
import 'package:socket_io_client/socket_io_client.dart' as IO;
import '../models/chat_model.dart';
import '../models/chat_room_model.dart';
import '../core/network/api_client.dart';
import '../storage/auth_storage.dart';
import '../services/auth_service.dart';

import '../core/config/api_config.dart';
import '../models/user_model.dart';

class ChatService {
  IO.Socket? _socket;
  Function(Chat)? onMessageReceived;
  Function()? onAuthSuccess;

  // Initialize WebSocket connection
  Future<void> initWebSocket() async {
    final token = await AuthStorage.getToken();
    final tenant = await AuthStorage.getTenant();
    
    if (token == null || tenant == null) return;
    
    final ktId = tenant['id'];

    if (_socket != null && _socket!.connected) return;

    _socket = IO.io(ApiConfig.socketUrl, <String, dynamic>{
      'transports': ['websocket'],
      'autoConnect': false,
    });

    _socket!.connect();

    _socket!.onConnect((_) {
      print('Socket.io connected');
      // Send authentication payload with opaque bearer token
      _socket!.emit('auth', {
        'token': token,
        'tenant_id': ktId,
      });
    });

    _socket!.on('auth_success', (_) {
      print('Socket.io authenticated successfully');
      if (onAuthSuccess != null) {
        onAuthSuccess!();
      }
    });

    _socket!.on('auth_error', (data) {
      print('Socket.io auth error: ${data['message']}');
    });

    _socket!.on('error', (data) {
      print('Socket.io error: ${data['message']}');
    });

    // Listen to incoming messages
    _socket!.on('new_message', (data) {
      try {
        final chat = Chat.fromJson(data);
        if (onMessageReceived != null) {
          onMessageReceived!(chat);
        }
      } catch (e) {
        print("Error parsing chat: $e");
      }
    });

    _socket!.onDisconnect((_) => print('Socket.io disconnected'));
  }

  void joinRoom(int roomId) {
    if (_socket != null) {
      _socket!.emit('join_room', {'room_id': roomId});
    }
  }

  // Send message via REST API (Triggers FCM)
  Future<bool> sendMessageViaApi(String message, {String type = 'group', int? receiverId, int? chatRoomId}) async {
    try {
      final response = await ApiClient.post('/chats/messages', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
        'chat_room_id': chatRoomId,
      });
      return response.statusCode == 200 || response.statusCode == 201;
    } catch (e) {
      return false;
    }
  }

  // Keep WebSocket send as primary, fallback to REST if disconnected
  void sendMessage(String message, {String type = 'group', int? receiverId, int? chatRoomId}) {
    if (_socket != null && _socket!.connected) {
      _socket!.emit('send_message', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
        'chat_room_id': chatRoomId,
      });
      // Do NOT trigger REST API here if socket is connected. Node.js server will handle DB insertion and FCM trigger.
    } else {
      // Fallback to REST API if socket not connected
      sendMessageViaApi(message, type: type, receiverId: receiverId, chatRoomId: chatRoomId);
    }
  }

  void closeConnection() {
    if (_socket != null) {
      _socket!.disconnect();
      _socket!.dispose();
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
  Future<Map<String, dynamic>> createRoom(String name, List<int> memberIds) async {
    try {
      final response = await ApiClient.post('/chats/rooms', {
        'name': name,
        'members': memberIds,
      });
      final data = json.decode(response.body);
      return {'success': data['status'] == true, 'message': data['message'] ?? ''};
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
  Future<List<Chat>> getPrivateChatHistory(int receiverId, {int? beforeId}) async {
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
}
