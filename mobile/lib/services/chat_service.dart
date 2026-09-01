import 'dart:convert';
import 'package:socket_io_client/socket_io_client.dart' as IO;
import '../models/chat_model.dart';
import '../core/network/api_client.dart';
import '../storage/auth_storage.dart';

class ChatService {
  final ApiClient _apiClient = ApiClient();
  IO.Socket? _socket;
  Function(Chat)? onMessageReceived;

  // Initialize WebSocket connection
  Future<void> initWebSocket() async {
    final token = await AuthStorage.getToken();
    final user = await AuthStorage.getUser();
    final ktId = await AuthStorage.getKarangTarunaId();
    
    if (token == null || user == null || ktId == null) return;

    // Use 10.0.2.2 for Android emulator to connect to localhost Node.js server (port 3000)
    _socket = IO.io('http://10.0.2.2:3000', <String, dynamic>{
      'transports': ['websocket'],
      'autoConnect': false,
    });

    _socket!.connect();

    _socket!.onConnect((_) {
      print('Socket.io connected');
      // Send authentication payload
      _socket!.emit('auth', {
        'user_id': user.id,
        'karang_taruna_id': int.parse(ktId),
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
      final response = await _apiClient.get('/chats/group');
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
      final response = await _apiClient.get('/chats/private/$receiverId');
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
