import 'package:flutter/material.dart';
import 'chat_room_screen.dart';
import 'create_group_screen.dart';
import '../../storage/auth_storage.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/animations/fade_in_slide.dart';
import '../../services/chat_service.dart';
import '../../services/auth_service.dart';
import '../../models/chat_room_model.dart';
import '../../models/user_model.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({Key? key}) : super(key: key);

  @override
  _ChatListScreenState createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  
  bool _isLoadingRooms = true;
  List<ChatRoom> _rooms = [];
  UserModel? _currentUser;
  final ChatService _chatService = ChatService();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      setState(() {});
    });
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoadingRooms = true);
    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'];
    }
    await _fetchRooms();
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

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool canCreateGroup = _currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Forum Diskusi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Grup'),
            Tab(text: 'Pesan Pribadi'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildGroupList(),
          _buildPrivateList(),
        ],
      ),
      floatingActionButton: (_tabController.index == 0 && canCreateGroup)
          ? FloatingActionButton(
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CreateGroupScreen()),
                );
                if (result == true) {
                  _fetchRooms();
                }
              },
              backgroundColor: AppTheme.primary,
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
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
      child: ListView.separated(
        itemCount: _rooms.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final room = _rooms[index];
          final isDefault = room.type == 'default';
          
          return FadeInSlide(
            delay: 0.1 * index,
            child: ListTile(
              leading: CircleAvatar(
                radius: 25,
                backgroundColor: isDefault ? AppTheme.primary : Colors.grey.shade400,
                child: Icon(isDefault ? Icons.apartment : Icons.group, color: Colors.white, size: 28),
              ),
              title: Text(
                room.name, 
                style: TextStyle(fontWeight: isDefault ? FontWeight.bold : FontWeight.w600, fontSize: 16),
              ),
              subtitle: Text(isDefault ? 'Grup utama Karang Taruna' : 'Grup diskusi'),
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
          );
        },
      ),
    );
  }

  Widget _buildPrivateList() {
    return const Center(
      child: Text(
        "Fitur pesan pribadi akan datang.",
        style: TextStyle(color: Colors.grey),
      ),
    );
  }
}
