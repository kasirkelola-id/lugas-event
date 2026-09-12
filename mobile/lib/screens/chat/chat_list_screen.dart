import 'package:flutter/material.dart';
import 'dart:async';
import '../../models/chat_model.dart';
import 'chat_room_screen.dart';
import 'create_group_screen.dart';

import '../../core/theme/app_theme.dart';
import '../widgets/animations/fade_in_slide.dart';
import '../../services/chat_service.dart';
import '../../services/auth_service.dart';
import '../../models/chat_room_model.dart';
import '../../models/user_model.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/app_drawer.dart';

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({Key? key}) : super(key: key);

  @override
  _ChatListScreenState createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  bool _isLoadingRooms = true;
  bool _isLoadingPrivate = true;
  List<ChatRoom> _rooms = [];
  List<Map<String, dynamic>> _privateContacts = [];
  UserModel? _currentUser;
  final ChatService _chatService = ChatService();
  StreamSubscription<Chat>? _messageSubscription;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      setState(() {});
    });
    _loadData();

    _messageSubscription = _chatService.messageStream.listen((chat) {
      if (chat.type == 'private') {
        _refreshTimer?.cancel();
        _refreshTimer = Timer(const Duration(milliseconds: 500), () {
          if (mounted) _fetchPrivateContacts();
        });
      }
    });
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoadingRooms = true;
      _isLoadingPrivate = true;
    });
    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'];
    }
    await _fetchRooms();
    await _fetchPrivateContacts();
  }

  Future<void> _fetchRooms() async {
    final rooms = await _chatService.getRooms();
    if (mounted) {
      setState(() {
        _rooms = rooms;
        _isLoadingRooms = false;
      });
    }
  }

  Future<void> _fetchPrivateContacts() async {
    final contacts = await _chatService.getPrivateContacts();
    if (mounted) {
      setState(() {
        _privateContacts = contacts;
        _isLoadingPrivate = false;
      });
    }
  }

  @override
  void dispose() {
    _messageSubscription?.cancel();
    _refreshTimer?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool canCreateGroup =
        _currentUser?.roleLevel == 'ketua' ||
        _currentUser?.roleLevel == 'superadmin';

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
        backgroundColor: AppTheme.background,
        body: NestedScrollView(
          headerSliverBuilder: (context, innerBoxIsScrolled) {
            return [
              SliverAppBar(
                title: const Text(
                  'Forum Diskusi',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                backgroundColor: AppTheme.primary,
                iconTheme: const IconThemeData(color: Colors.white),
                pinned: true,
                floating: true,
                elevation: 0,
                flexibleSpace: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        AppTheme.primary,
                        AppTheme.primary.withOpacity(0.8),
                      ],
                    ),
                  ),
                ),
                bottom: PreferredSize(
                  preferredSize: const Size.fromHeight(60),
                  child: Container(
                    decoration: const BoxDecoration(color: Colors.transparent),
                    child: TabBar(
                      controller: _tabController,
                      indicatorColor: Colors.white,
                      indicatorWeight: 3,
                      labelColor: Colors.white,
                      unselectedLabelColor: Colors.white.withOpacity(0.6),
                      labelStyle: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                      tabs: const [
                        Tab(text: 'Grup'),
                        Tab(text: 'Pesan Pribadi'),
                      ],
                    ),
                  ),
                ),
              ),
            ];
          },
          body: TabBarView(
            controller: _tabController,
            children: [_buildGroupList(), _buildPrivateList()],
          ),
        ),
        floatingActionButton: (_tabController.index == 0 && canCreateGroup)
            ? FloatingActionButton.extended(
                onPressed: () async {
                  final result = await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const CreateGroupScreen(),
                    ),
                  );
                  if (result == true) {
                    _fetchRooms();
                  }
                },
                backgroundColor: AppTheme.primary,
                icon: const Icon(Icons.group_add, color: Colors.white),
                label: const Text(
                  'Buat Grup',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              )
            : null,
      ),
    );
  }

  Widget _buildGroupList() {
    if (_isLoadingRooms) {
      return const Center(child: CustomLoadingIndicator());
    }

    if (_rooms.isEmpty) {
      return const Center(child: Text("Belum ada grup."));
    }

    return RefreshIndicator(
      onRefresh: _fetchRooms,
      child: ListView.builder(
        padding: const EdgeInsets.only(
          top: 16,
          bottom: 80,
          left: 16,
          right: 16,
        ),
        itemCount: _rooms.length,
        itemBuilder: (context, index) {
          final room = _rooms[index];
          final isDefault = room.type == 'default';

          return FadeInSlide(
            delay: 0.1 * index,
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                leading: Container(
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: AppTheme.primary.withOpacity(0.2),
                      width: 2,
                    ),
                  ),
                  child: CircleAvatar(
                    radius: 24,
                    backgroundColor: isDefault
                        ? AppTheme.primary.withOpacity(0.1)
                        : Colors.grey.shade100,
                    child: Icon(
                      isDefault ? Icons.apartment : Icons.group,
                      color: isDefault
                          ? AppTheme.primary
                          : Colors.grey.shade600,
                      size: 26,
                    ),
                  ),
                ),
                title: Text(
                  room.name,
                  style: TextStyle(
                    fontWeight: isDefault ? FontWeight.bold : FontWeight.w600,
                    fontSize: 16,
                  ),
                ),
                subtitle: Text(
                  isDefault ? 'Grup utama Karang Taruna' : 'Grup diskusi',
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppTheme.textSecondary,
                  ),
                ),
                trailing: const Icon(
                  Icons.chevron_right,
                  color: Colors.black26,
                ),
                onTap: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatRoomScreen(
                        roomName: room.name,
                        roomId: room.id,
                        type: 'group',
                        roomType: room.type,
                      ),
                    ),
                  );
                  _fetchRooms();
                },
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildPrivateList() {
    if (_isLoadingPrivate) {
      return const Center(child: CustomLoadingIndicator());
    }

    if (_privateContacts.isEmpty) {
      return const Center(
        child: Text(
          "Belum ada pesan pribadi.",
          style: TextStyle(color: Colors.grey),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchPrivateContacts,
      child: ListView.builder(
        padding: const EdgeInsets.only(
          top: 16,
          bottom: 80,
          left: 16,
          right: 16,
        ),
        itemCount: _privateContacts.length,
        itemBuilder: (context, index) {
          final contact = _privateContacts[index];
          final photoUrl = contact['contact_photo_url'];

          return FadeInSlide(
            delay: 0.1 * index,
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                leading: CircleAvatar(
                  radius: 26,
                  backgroundColor: Colors.grey.shade200,
                  backgroundImage: photoUrl != null
                      ? NetworkImage(photoUrl)
                      : null,
                  child: photoUrl == null
                      ? const Icon(Icons.person, color: Colors.grey, size: 28)
                      : null,
                ),
                title: Text(
                  contact['contact_name'] ?? 'Pengguna',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 16,
                  ),
                ),
                subtitle: Text(
                  contact['last_message'] ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppTheme.textSecondary,
                  ),
                ),
                trailing: const Icon(
                  Icons.chevron_right,
                  color: Colors.black26,
                ),
                onTap: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ChatRoomScreen(
                        receiverId: contact['contact_id'],
                        roomName: contact['contact_name'],
                        type: 'private',
                      ),
                    ),
                  );
                  _fetchPrivateContacts(); // refresh if new message
                },
              ),
            ),
          );
        },
      ),
    );
  }
}
