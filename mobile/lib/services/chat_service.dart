import 'dart:convert';
import 'package:socket_io_client/socket_io_client.dart' as IO;
import '../models/chat_model.dart';
import '../core/network/api_client.dart';
import '../storage/auth_storage.dart';
import '../services/auth_service.dart';

import '../core/config/api_config.dart';
import '../models/user_model.dart';

class ChatService {
  IO.Socket? _socket;
  Function(Chat)? onMessageReceived;

  // Initialize WebSocket connection
  Future<void> initWebSocket() async {
    final token = await AuthStorage.getToken();
    final userResult = await AuthService.getMe();
    final tenant = await AuthStorage.getTenant();
    
    if (token == null || !userResult['success'] || tenant == null) return;
    
    final user = userResult['user'] as UserModel;
    final ktId = tenant['id'];

    _socket = IO.io(ApiConfig.socketUrl, <String, dynamic>{
      'transports': ['websocket'],
      'autoConnect': false,
    });

    _socket!.connect();

    _socket!.onConnect((_) {
      print('Socket.io connected');
      // Send authentication payload
      _socket!.emit('auth', {
        'user_id': user.id,
        'karang_taruna_id': ktId,
      });
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

  // Send message via WebSocket
  void sendMessage(String message, {String type = 'group', int? receiverId}) {
    if (_socket != null && _socket!.connected) {
      _socket!.emit('send_message', {
        'type': type,
        'message': message,
        'receiver_id': receiverId,
      });
    }
  }

  void closeConnection() {
    if (_socket != null) {
      _socket!.disconnect();
      _socket!.dispose();
    }
  }

  // REST API: Get Group Chat History (Still from CodeIgniter)
  Future<List<Chat>> getGroupChatHistory() async {
    try {
      final response = await ApiClient.get('/chats/group');
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return (data['data'] as List).map((c) => Chat.fromJson(c)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // REST API: Get Private Chat History (Still from CodeIgniter)
  Future<List<Chat>> getPrivateChatHistory(int receiverId) async {
    try {
      final response = await ApiClient.get('/chats/private/$receiverId');
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
