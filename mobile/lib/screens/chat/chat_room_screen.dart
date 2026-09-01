import 'package:flutter/material.dart';
import '../../models/chat_model.dart';
import '../../models/user_model.dart';
import '../../services/chat_service.dart';
import '../../storage/auth_storage.dart';

class ChatRoomScreen extends StatefulWidget {
  final String roomName;
  final String type; // 'group' or 'private'
  final int? receiverId;

  const ChatRoomScreen({
    Key? key,
    required this.roomName,
    this.type = 'group',
    this.receiverId,
  }) : super(key: key);

  @override
  _ChatRoomScreenState createState() => _ChatRoomScreenState();
}

class _ChatRoomScreenState extends State<ChatRoomScreen> {
  final ChatService _chatService = ChatService();
  final TextEditingController _msgController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  
  List<Chat> _messages = [];
  User? _currentUser;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUserAndHistory();
  }

  Future<void> _loadUserAndHistory() async {
    _currentUser = await AuthStorage.getUser();
    
    // Load history
    if (widget.type == 'group') {
      _messages = await _chatService.getGroupChatHistory();
    } else {
      _messages = await _chatService.getPrivateChatHistory(widget.receiverId!);
    }

    setState(() {
      _isLoading = false;
    });

    _scrollToBottom();

    // Init websocket
    await _chatService.initWebSocket();
    _chatService.onMessageReceived = (Chat chat) {
      if (mounted) {
        setState(() {
          _messages.add(chat);
        });
        _scrollToBottom();
      }
    };
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent + 100,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _sendMessage() {
    if (_msgController.text.trim().isEmpty) return;
    
    _chatService.sendMessage(
      _msgController.text.trim(),
      type: widget.type,
      receiverId: widget.receiverId,
    );
    
    _msgController.clear();
  }

  @override
  void dispose() {
    _chatService.closeConnection();
    _msgController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFE5DDD5),
      appBar: AppBar(
        backgroundColor: const Color(0xFF128C7E),
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(
          children: [
            const CircleAvatar(
              radius: 20,
              backgroundColor: Colors.white24,
              child: Icon(Icons.group, color: Colors.white),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.roomName,
                    style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  const Text(
                    "Ketuk untuk info grup",
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  )
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(icon: const Icon(Icons.videocam, color: Colors.white), onPressed: () {}),
          IconButton(icon: const Icon(Icons.call, color: Colors.white), onPressed: () {}),
          IconButton(icon: const Icon(Icons.more_vert, color: Colors.white), onPressed: () {}),
        ],
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator())
        : Column(
            children: [
              Expanded(
                child: ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
                  itemCount: _messages.length,
                  itemBuilder: (context, index) {
                    final chat = _messages[index];
                    final isMe = _currentUser != null && chat.senderId == _currentUser!.id;
                    
                    // Format time
                    final time = "${chat.createdAt.hour.toString().padLeft(2, '0')}:${chat.createdAt.minute.toString().padLeft(2, '0')}";

                    return Align(
                      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        constraints: BoxConstraints(
                          maxWidth: MediaQuery.of(context).size.width * 0.75,
                        ),
                        decoration: BoxDecoration(
                          color: isMe ? const Color(0xFFDCF8C6) : Colors.white,
                          borderRadius: BorderRadius.only(
                            topLeft: const Radius.circular(12),
                            topRight: const Radius.circular(12),
                            bottomLeft: Radius.circular(isMe ? 12 : 0),
                            bottomRight: Radius.circular(isMe ? 0 : 12),
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              spreadRadius: 1,
                              blurRadius: 1,
                              offset: const Offset(0, 1),
                            ),
                          ],
                        ),
                        child: Stack(
                          children: [
                            Padding(
                              padding: EdgeInsets.only(
                                left: 10,
                                right: isMe ? 60 : 50,
                                top: 8,
                                bottom: 20,
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (!isMe && widget.type == 'group')
                                    Padding(
                                      padding: const EdgeInsets.only(bottom: 4),
                                      child: Text(
                                        chat.namaLengkap ?? 'User',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF075E54)),
                                      ),
                                    ),
                                  Text(
                                    chat.message,
                                    style: const TextStyle(fontSize: 15, color: Colors.black87),
                                  ),
                                ],
                              ),
                            ),
                            Positioned(
                              bottom: 4,
                              right: 10,
                              child: Row(
                                children: [
                                  Text(
                                    time,
                                    style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                                  ),
                                  if (isMe) ...[
                                    const SizedBox(width: 4),
                                    const Icon(Icons.done_all, size: 14, color: Colors.blue),
                                  ]
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                color: Colors.transparent,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(25),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              spreadRadius: 1,
                              blurRadius: 1,
                            )
                          ]
                        ),
                        child: Row(
                          children: [
                            IconButton(
                              icon: const Icon(Icons.emoji_emotions_outlined, color: Colors.grey),
                              onPressed: () {},
                            ),
                            Expanded(
                              child: TextField(
                                controller: _msgController,
                                maxLines: 6,
                                minLines: 1,
                                decoration: const InputDecoration(
                                  hintText: "Ketik pesan",
                                  border: InputBorder.none,
                                  contentPadding: EdgeInsets.symmetric(vertical: 10),
                                ),
                                onSubmitted: (_) => _sendMessage(),
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.attach_file, color: Colors.grey),
                              onPressed: () {},
                            ),
                            IconButton(
                              icon: const Icon(Icons.camera_alt, color: Colors.grey),
                              onPressed: () {},
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      margin: const EdgeInsets.only(bottom: 2),
                      child: CircleAvatar(
                        radius: 24,
                        backgroundColor: const Color(0xFF128C7E),
                        child: IconButton(
                          icon: const Icon(Icons.send, color: Colors.white, size: 20),
                          onPressed: _sendMessage,
                        ),
                      ),
                    )
                  ],
                ),
              ),
            ],
          ),
    );
  }
}
