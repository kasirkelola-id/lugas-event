import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/chat_service.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/common/custom_button.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class CreateGroupScreen extends StatefulWidget {
  const CreateGroupScreen({Key? key}) : super(key: key);

  @override
  _CreateGroupScreenState createState() => _CreateGroupScreenState();
}

class _CreateGroupScreenState extends State<CreateGroupScreen> {
  final TextEditingController _nameController = TextEditingController();
  final ChatService _chatService = ChatService();
  
  bool _isLoading = false;
  bool _isLoadingUsers = true;
  List<UserModel> _users = [];
  final List<int> _selectedUserIds = [];

  @override
  void initState() {
    super.initState();
    _fetchUsers();
  }

  Future<void> _fetchUsers() async {
    setState(() => _isLoadingUsers = true);
    final result = await UserService.getUsers();
    if (result['success']) {
      setState(() {
        _users = result['users'];
        _isLoadingUsers = false;
      });
    } else {
      setState(() => _isLoadingUsers = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'])),
        );
      }
    }
  }

  Future<void> _createGroup() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nama grup tidak boleh kosong')),
      );
      return;
    }

    setState(() => _isLoading = true);
    final result = await _chatService.createRoom(name, _selectedUserIds);
    setState(() => _isLoading = false);

    if (result['success']) {
      if (mounted) {
        Navigator.pop(context, true);
      }
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'])),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buat Grup Baru', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _nameController,
              decoration: InputDecoration(
                labelText: 'Nama Grup',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                prefixIcon: const Icon(Icons.group),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
            alignment: Alignment.centerLeft,
            child: const Text('Pilih Anggota:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ),
          Expanded(
            child: _isLoadingUsers
                ? const Center(child: CustomLoadingIndicator())
                : ListView.builder(
                    itemCount: _users.length,
                    itemBuilder: (context, index) {
                      final user = _users[index];
                      final isSelected = _selectedUserIds.contains(user.id);
                      
                      return CheckboxListTile(
                        value: isSelected,
                        title: Text(user.namaLengkap),
                        subtitle: Text(user.roleLevel),
                        activeColor: AppTheme.primary,
                        onChanged: (bool? value) {
                          setState(() {
                            if (value == true) {
                              _selectedUserIds.add(user.id!);
                            } else {
                              _selectedUserIds.remove(user.id);
                            }
                          });
                        },
                      );
                    },
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: SizedBox(
              width: double.infinity,
              height: 48,
              child: CustomButton(
                text: 'Buat Grup',
                onPressed: _isLoading ? null : _createGroup,
                isLoading: _isLoading,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
