import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/chat_service.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class GroupInfoScreen extends StatefulWidget {
  final int roomId;
  final String roomName;
  final String roomType;

  const GroupInfoScreen({
    Key? key,
    required this.roomId,
    required this.roomName,
    required this.roomType,
  }) : super(key: key);

  @override
  _GroupInfoScreenState createState() => _GroupInfoScreenState();
}

class _GroupInfoScreenState extends State<GroupInfoScreen> {
  final ChatService _chatService = ChatService();
  bool _isLoading = false;
  UserModel? _currentUser;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    final result = await AuthService.getMe();
    if (result['success'] && mounted) {
      setState(() {
        _currentUser = result['user'];
      });
    }
  }

  Future<void> _deleteGroup() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus Grup'),
        content: const Text('Apakah Anda yakin ingin menghapus grup ini? Semua pesan akan terhapus dan tidak bisa dikembalikan.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Hapus', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isLoading = true);
    final success = await _chatService.deleteRoom(widget.roomId);
    setState(() => _isLoading = false);

    if (success) {
      if (mounted) {
        Navigator.pop(context, true); // return true to indicate deletion
      }
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gagal menghapus grup')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool canDelete = widget.roomType == 'custom' && 
                           (_currentUser?.roleLevel == 'ketua' || _currentUser?.roleLevel == 'superadmin');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Info Grup', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: _isLoading 
        ? const Center(child: CustomLoadingIndicator())
        : Column(
            children: [
              const SizedBox(height: 32),
              CircleAvatar(
                radius: 50,
                backgroundColor: widget.roomType == 'default' ? AppTheme.primary : Colors.grey.shade400,
                child: Icon(widget.roomType == 'default' ? Icons.apartment : Icons.group, color: Colors.white, size: 50),
              ),
              const SizedBox(height: 16),
              Text(
                widget.roomName,
                style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),
              Text(
                widget.roomType == 'default' ? 'Grup utama Karang Taruna' : 'Grup Custom',
                style: const TextStyle(color: Colors.grey),
              ),
              const SizedBox(height: 32),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.people),
                title: const Text('Anggota Grup'),
                subtitle: const Text('Fitur manajemen anggota akan datang'),
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Segera hadir!')),
                  );
                },
              ),
              const Divider(),
              if (canDelete)
                ListTile(
                  leading: const Icon(Icons.delete, color: Colors.red),
                  title: const Text('Hapus Grup', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                  onTap: _deleteGroup,
                ),
            ],
          ),
    );
  }
}
