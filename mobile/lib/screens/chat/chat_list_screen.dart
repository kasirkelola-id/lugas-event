import 'package:flutter/material.dart';
import 'chat_room_screen.dart';
import '../../storage/auth_storage.dart';

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({Key? key}) : super(key: key);

  @override
  _ChatListScreenState createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> {
  String _ktName = "Grup Karang Taruna";

  @override
  void initState() {
    super.initState();
    _loadKarangTarunaName();
  }

  Future<void> _loadKarangTarunaName() async {
    final tenant = await AuthStorage.getTenant();
    if (tenant != null && tenant['name'] != null) {
      setState(() {
        _ktName = tenant['name'];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Forum Diskusi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        backgroundColor: const Color(0xFF128C7E),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: ListView(
        children: [
          ListTile(
            leading: const CircleAvatar(
              radius: 25,
              backgroundColor: Colors.grey,
              child: Icon(Icons.group, color: Colors.white, size: 30),
            ),
            title: Text(_ktName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            subtitle: const Text('Ketuk untuk masuk ke grup'),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ChatRoomScreen(
                    roomName: _ktName,
                    type: 'group',
                  ),
                ),
              );
            },
          ),
          const Divider(),
          // Future: List private chats here
        ],
      ),
    );
  }
}
