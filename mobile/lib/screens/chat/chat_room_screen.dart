import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../models/chat_model.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../../services/chat_service.dart';
import '../../core/theme/app_theme.dart';
import 'group_info_screen.dart';
import 'package:intl/intl.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class ChatRoomScreen extends StatefulWidget {
  final String roomName;
  final int? roomId;
  final String type; // 'group' or 'private'
  final String roomType; // 'default' or 'custom'
  final int? receiverId;

  const ChatRoomScreen({
    Key? key,
    required this.roomName,
    this.roomId,
    this.type = 'group',
    this.roomType = 'default',
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
  final Set<int> _renderedChatIds = {}; // For deduplication
  
  UserModel? _currentUser;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMore = true;
  bool _showNewMessageIndicator = false;
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadUserAndHistory();
  }

  void _onScroll() {
    // If user scrolls up to the top, load more
    if (_scrollController.position.pixels <= 100 && !_isLoadingMore && _hasMore) {
      _loadMoreMessages();
    }
    
    // Hide new message indicator if near bottom
    if (_scrollController.hasClients) {
      if (_scrollController.position.maxScrollExtent - _scrollController.position.pixels < 100) {
        if (_showNewMessageIndicator) {
          setState(() {
            _showNewMessageIndicator = false;
          });
        }
      }
    }
  }

  Future<void> _loadUserAndHistory() async {
    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'] as UserModel;
    }
    
    // Load history
    List<Chat> initialMessages = [];
    if (widget.type == 'group' && widget.roomId != null) {
      initialMessages = await _chatService.getRoomChatHistory(widget.roomId!);
    } else if (widget.receiverId != null) {
      initialMessages = await _chatService.getPrivateChatHistory(widget.receiverId!);
    }

    if (mounted) {
      setState(() {
        _messages = initialMessages;
        for (var msg in _messages) {
          _renderedChatIds.add(msg.id);
        }
        _isLoading = false;
        if (initialMessages.length < 50) _hasMore = false;
      });
      _scrollToBottom(force: true);
    }

    // Init websocket
    _chatService.onAuthSuccess = () {
      if (widget.type == 'group' && widget.roomId != null) {
        _chatService.joinRoom(widget.roomId!);
      }
    };
    await _chatService.initWebSocket();
    _chatService.onMessageReceived = (Chat chat) {
      if (!mounted) return;
      if (_renderedChatIds.contains(chat.id)) return; // Deduplicate

      // Filter message for this room
      if (widget.type == 'group' && chat.chatRoomId == widget.roomId) {
        _addNewMessage(chat);
      } else if (widget.type == 'private' && (chat.senderId == widget.receiverId || chat.receiverId == widget.receiverId)) {
        _addNewMessage(chat);
      }
    };
  }

  void _addNewMessage(Chat chat) {
    setState(() {
      _messages.add(chat);
      _renderedChatIds.add(chat.id);
    });

    // Auto scroll logic
    if (_scrollController.hasClients) {
      final isNearBottom = _scrollController.position.maxScrollExtent - _scrollController.position.pixels < 200;
      final isMe = _currentUser != null && chat.senderId == _currentUser!.id;
      
      if (isNearBottom || isMe) {
        _scrollToBottom();
      } else {
        setState(() {
          _showNewMessageIndicator = true;
        });
      }
    }
  }

  Future<void> _loadMoreMessages() async {
    if (_messages.isEmpty) return;
    
    setState(() {
      _isLoadingMore = true;
    });

    final beforeId = _messages.first.id;
    List<Chat> olderMessages = [];
    
    if (widget.type == 'group' && widget.roomId != null) {
      olderMessages = await _chatService.getRoomChatHistory(widget.roomId!, beforeId: beforeId);
    } else if (widget.receiverId != null) {
      olderMessages = await _chatService.getPrivateChatHistory(widget.receiverId!, beforeId: beforeId);
    }

    if (!mounted) return;

    if (olderMessages.isEmpty) {
      setState(() {
        _hasMore = false;
        _isLoadingMore = false;
      });
      return;
    }

    // Remember scroll position to prevent jumping
    final currentExtent = _scrollController.position.maxScrollExtent;
    
    setState(() {
      for (var msg in olderMessages) {
        _renderedChatIds.add(msg.id);
      }
      _messages.insertAll(0, olderMessages);
      if (olderMessages.length < 50) _hasMore = false;
      _isLoadingMore = false;
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        final newExtent = _scrollController.position.maxScrollExtent;
        _scrollController.jumpTo(_scrollController.position.pixels + (newExtent - currentExtent));
      }
    });
  }

  void _scrollToBottom({bool force = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        if (force) {
          _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
        } else {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent + 100,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      }
    });
  }

  void _sendMessage() {
    if (_msgController.text.trim().isEmpty) return;
    if (_isSending) return; // Prevent rapid duplicate
    
    setState(() { _isSending = true; });
    
    _chatService.sendMessage(
      _msgController.text.trim(),
      type: widget.type,
      receiverId: widget.receiverId,
      chatRoomId: widget.roomId,
    );
    
    _msgController.clear();
    setState(() { _isSending = false; });
  }

  Color _getColorForUser(int userId) {
    final List<Color> colors = [
      Colors.red, Colors.pink, Colors.purple, Colors.deepPurple,
      Colors.indigo, Colors.blue, Colors.lightBlue, Colors.cyan,
      Colors.teal, Colors.green, Colors.lightGreen, Colors.lime,
      Colors.orange, Colors.deepOrange, Colors.brown, Colors.blueGrey,
    ];
    return colors[userId % colors.length];
  }

  void _showUserDetails(BuildContext context, int userId, String name, String role, {String? photoUrl}) {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          contentPadding: const EdgeInsets.all(24),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircleAvatar(
                radius: 40,
                backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                backgroundImage: photoUrl != null ? NetworkImage(photoUrl) : null,
                child: photoUrl == null ? const Icon(Icons.person, size: 40, color: AppTheme.primary) : null,
              ),
              const SizedBox(height: 16),
              Text(
                name,
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  role.toUpperCase(),
                  style: const TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.w600),
                ),
              ),
              if (userId != _currentUser?.id) ...[
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: const Text('Kirim Pesan Pribadi'),
                    onPressed: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ChatRoomScreen(
                            roomName: name,
                            type: 'private',
                            receiverId: userId,
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Tutup'),
            ),
          ],
        );
      },
    );
  }

  String _formatDateSeparator(DateTime date) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final targetDate = DateTime(date.year, date.month, date.day);

    if (targetDate == today) {
      return "Hari ini";
    } else if (targetDate == yesterday) {
      return "Kemarin";
    } else {
      return DateFormat('d MMM yyyy', 'id_ID').format(date);
    }
  }

  void _copyToClipboard(String text) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Pesan disalin'), duration: Duration(seconds: 1)),
    );
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
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.primary,
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: Colors.white24,
              child: Icon(widget.type == 'group' ? Icons.group : Icons.person, color: Colors.white, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                widget.roomName,
                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        actions: [
          if (widget.type == 'group' && widget.roomId != null)
            IconButton(
              icon: const Icon(Icons.info_outline, color: Colors.white),
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => GroupInfoScreen(
                      roomId: widget.roomId!,
                      roomName: widget.roomName,
                      roomType: widget.roomType,
                    ),
                  ),
                ).then((value) {
                  if (value == true) { // Room was deleted
                    Navigator.pop(context, true);
                  }
                });
              },
            ),
        ],
      ),
      body: _isLoading 
        ? const Center(child: CustomLoadingIndicator())
        : Column(
            children: [
              Expanded(
                child: Stack(
                  children: [
                    if (_messages.isEmpty)
                      Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.chat_bubble_outline, size: 64, color: Colors.grey[400]),
                            const SizedBox(height: 16),
                            Text("Belum ada percakapan.", style: TextStyle(color: Colors.grey[600], fontSize: 16)),
                            const SizedBox(height: 4),
                            Text("Mulai kirim pesan pertama.", style: TextStyle(color: Colors.grey[500], fontSize: 14)),
                          ],
                        ),
                      )
                    else
                      ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 20),
                        itemCount: _messages.length + (_isLoadingMore ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (_isLoadingMore && index == 0) {
                            return const Padding(
                              padding: EdgeInsets.symmetric(vertical: 10),
                              child: Center(child: CustomLoadingIndicator(size: 24, )),
                            );
                          }

                          final messageIndex = _isLoadingMore ? index - 1 : index;
                          final chat = _messages[messageIndex];
                          final isMe = _currentUser != null && chat.senderId == _currentUser!.id;
                          
                          bool showDateSeparator = false;
                          bool isSameSenderAsPrevious = false;
                          
                          if (messageIndex == 0) {
                            showDateSeparator = true;
                          } else {
                            final previousChat = _messages[messageIndex - 1];
                            final currentDate = DateTime(chat.createdAt.year, chat.createdAt.month, chat.createdAt.day);
                            final previousDate = DateTime(previousChat.createdAt.year, previousChat.createdAt.month, previousChat.createdAt.day);
                            if (currentDate != previousDate) {
                              showDateSeparator = true;
                            } else {
                              // Grouping: same sender and within 5 minutes
                              if (chat.senderId == previousChat.senderId &&
                                  chat.createdAt.difference(previousChat.createdAt).inMinutes < 5) {
                                isSameSenderAsPrevious = true;
                              }
                            }
                          }

                          // Format time
                          final time = "${chat.createdAt.hour.toString().padLeft(2, '0')}:${chat.createdAt.minute.toString().padLeft(2, '0')}";

                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              if (showDateSeparator)
                                Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 16),
                                  child: Center(
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: Colors.grey[300],
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Text(
                                        _formatDateSeparator(chat.createdAt),
                                        style: TextStyle(fontSize: 12, color: Colors.grey[700], fontWeight: FontWeight.w600),
                                      ),
                                    ),
                                  ),
                                ),
                              Padding(
                                padding: EdgeInsets.only(bottom: isSameSenderAsPrevious ? 2 : 8),
                                child: Row(
                                  mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    if (!isMe) ...[
                                      if (isSameSenderAsPrevious)
                                        const SizedBox(width: 32)
                                      else
                                        GestureDetector(
                                          onTap: () => _showUserDetails(context, chat.senderId, chat.namaLengkap ?? 'User', chat.roleLevel ?? 'Anggota', photoUrl: chat.senderPhotoUrl),
                                          child: CircleAvatar(
                                            radius: 16,
                                            backgroundColor: _getColorForUser(chat.senderId),
                                            backgroundImage: chat.senderPhotoUrl != null
                                                ? NetworkImage(chat.senderPhotoUrl!)
                                                : null,
                                            child: chat.senderPhotoUrl == null
                                                ? Text(
                                                    (chat.namaLengkap != null && chat.namaLengkap!.isNotEmpty) ? chat.namaLengkap![0].toUpperCase() : 'U',
                                                    style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                                                  )
                                                : null,
                                          ),
                                        ),
                                      const SizedBox(width: 8),
                                    ],
                                    Flexible(
                                      child: GestureDetector(
                                        onLongPress: () => _copyToClipboard(chat.message),
                                        child: Container(
                                          constraints: BoxConstraints(
                                            maxWidth: MediaQuery.of(context).size.width * 0.70,
                                          ),
                                          decoration: BoxDecoration(
                                            color: isMe ? const Color(0xFFDCF8C6) : Colors.white,
                                            borderRadius: BorderRadius.only(
                                              topLeft: const Radius.circular(12),
                                              topRight: const Radius.circular(12),
                                              bottomLeft: Radius.circular(isMe || isSameSenderAsPrevious ? 12 : 0),
                                              bottomRight: Radius.circular(!isMe || isSameSenderAsPrevious ? 12 : 0),
                                            ),
                                            boxShadow: [
                                              BoxShadow(
                                                color: Colors.black.withOpacity(0.05),
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
                                                  left: 12,
                                                  right: isMe ? 50 : 40,
                                                  top: (!isMe && widget.type == 'group' && !isSameSenderAsPrevious) ? 6 : 8,
                                                  bottom: 12,
                                                ),
                                                child: Column(
                                                  crossAxisAlignment: CrossAxisAlignment.start,
                                                  children: [
                                                    if (!isMe && widget.type == 'group' && !isSameSenderAsPrevious)
                                                      Padding(
                                                        padding: const EdgeInsets.only(bottom: 2),
                                                        child: Text(
                                                          chat.namaLengkap ?? 'User',
                                                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: _getColorForUser(chat.senderId)),
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
                                                right: 8,
                                                child: Row(
                                                  children: [
                                                    Text(
                                                      time,
                                                      style: TextStyle(fontSize: 10, color: Colors.grey[600]),
                                                    ),
                                                    if (isMe) ...[
                                                      const SizedBox(width: 4),
                                                      const Icon(Icons.done_all, size: 14, color: Colors.blue), // Hardcoded to read for MVP
                                                    ]
                                                  ],
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          );
                        },
                      ),
                    
                    if (_showNewMessageIndicator)
                      Positioned(
                        bottom: 10,
                        right: 20,
                        child: FloatingActionButton.small(
                          onPressed: () {
                            _scrollToBottom(force: true);
                            setState(() {
                              _showNewMessageIndicator = false;
                            });
                          },
                          backgroundColor: Colors.white,
                          child: const Icon(Icons.keyboard_arrow_down, color: AppTheme.primary),
                        ),
                      ),
                  ],
                ),
              ),
              SafeArea(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  color: Colors.transparent,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(24),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.05),
                                spreadRadius: 1,
                                blurRadius: 1,
                              )
                            ]
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _msgController,
                                  maxLines: 6,
                                  minLines: 1,
                                  textCapitalization: TextCapitalization.sentences,
                                  decoration: const InputDecoration(
                                    hintText: "Ketik pesan",
                                    border: InputBorder.none,
                                    contentPadding: EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                                  ),
                                  onSubmitted: (_) => _sendMessage(),
                                ),
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
                          backgroundColor: AppTheme.primary,
                          child: IconButton(
                            icon: const Icon(Icons.send, color: Colors.white, size: 20),
                            onPressed: _sendMessage,
                          ),
                        ),
                      )
                    ],
                  ),
                ),
              ),
            ],
          ),
    );
  }
}
