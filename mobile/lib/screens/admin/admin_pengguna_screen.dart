import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class AdminPenggunaScreen extends StatefulWidget {
  const AdminPenggunaScreen({super.key});

  @override
  State<AdminPenggunaScreen> createState() => _AdminPenggunaScreenState();
}

class _AdminPenggunaScreenState extends State<AdminPenggunaScreen> {
  UserModel? _currentUser;
  List<UserModel> _users = [];
  List<UserModel> _filteredUsers = [];
  
  bool _isLoading = true;
  String? _errorMessage;
  
  String _searchQuery = '';
  String _roleFilter = 'Semua';

  final _formKey = GlobalKey<FormState>();
  final _namaLengkapController = TextEditingController();
  final _namaPanggilanController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _whatsappController = TextEditingController();
  String _selectedRole = 'pengelola';

  @override
  void initState() {
    super.initState();
    _loadData();
  }
  
  @override
  void dispose() {
    _namaLengkapController.dispose();
    _namaPanggilanController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _whatsappController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    if (!userResult['success']) {
      if (mounted) _handleError(userResult['message']);
      return;
    }

    final usersResult = await UserService.getUsers();
    if (!mounted) return;

    if (usersResult['success']) {
      setState(() {
        _currentUser = userResult['user'];
        _users = usersResult['users'] as List<UserModel>;
        _applyFilters();
        _isLoading = false;
      });
    } else {
      _handleError(usersResult['message'] ?? 'Data pengguna gagal dimuat.');
    }
  }

  void _applyFilters() {
    setState(() {
      _filteredUsers = _users.where((user) {
        final matchesSearch = user.namaLengkap.toLowerCase().contains(_searchQuery.toLowerCase()) || 
                              user.username.toLowerCase().contains(_searchQuery.toLowerCase());
        final matchesRole = _roleFilter == 'Semua' || 
                            (_roleFilter == 'Admin' && user.roleLevel == 'admin') ||
                            (_roleFilter == 'Pengelola' && user.roleLevel == 'pengelola') ||
                            (_roleFilter == 'Anggota' && user.roleLevel == 'anggota');
        return matchesSearch && matchesRole;
      }).toList();
    });
  }

  void _handleError(String message) {
    setState(() {
      _isLoading = false;
      _errorMessage = message;
    });
    _showSnackbar(message, isError: true);
  }
  
  void _showSnackbar(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red.shade700 : Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _showUserForm({UserModel? user}) async {
    final isEditing = user != null;
    
    if (isEditing) {
      _namaLengkapController.text = user.namaLengkap;
      _namaPanggilanController.text = user.namaPanggilan;
      _usernameController.text = user.username;
      _whatsappController.text = user.noWhatsapp;
      _selectedRole = user.roleLevel;
    } else {
      _namaLengkapController.clear();
      _namaPanggilanController.clear();
      _usernameController.clear();
      _passwordController.clear();
      _whatsappController.clear();
      _selectedRole = 'pengelola';
    }

    bool isLoadingSubmit = false;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: Text(isEditing ? 'Edit Pengguna' : 'Tambah Pengguna'),
              content: SingleChildScrollView(
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: _namaLengkapController,
                        decoration: const InputDecoration(labelText: 'Nama Lengkap', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Nama lengkap wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _namaPanggilanController,
                        decoration: const InputDecoration(labelText: 'Nama Panggilan', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Nama panggilan wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _usernameController,
                        decoration: const InputDecoration(labelText: 'Username', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Username wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _whatsappController,
                        decoration: const InputDecoration(labelText: 'No. WhatsApp', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      if (!isEditing)
                        TextFormField(
                          controller: _passwordController,
                          decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder()),
                          obscureText: true,
                          validator: (value) => value == null || value.length < 6 ? 'Password min. 6 karakter' : null,
                        ),
                      if (!isEditing) const SizedBox(height: 12),
                      if (!isEditing)
                        DropdownButtonFormField<String>(
                          initialValue: _selectedRole,
                          decoration: const InputDecoration(labelText: 'Role', border: OutlineInputBorder()),
                          items: const [
                            DropdownMenuItem(value: 'admin', child: Text('Admin')),
                            DropdownMenuItem(value: 'pengelola', child: Text('Pengelola')),
                            DropdownMenuItem(value: 'anggota', child: Text('Anggota')),
                          ],
                          onChanged: (val) {
                            if (val != null) setStateDialog(() => _selectedRole = val);
                          },
                        ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoadingSubmit ? null : () => Navigator.pop(context),
                  child: const Text('Batal'),
                ),
                ElevatedButton(
                  onPressed: isLoadingSubmit
                      ? null
                      : () async {
                          if (_formKey.currentState!.validate()) {
                            setStateDialog(() => isLoadingSubmit = true);
                            
                            final data = {
                              'nama_lengkap': _namaLengkapController.text,
                              'nama_panggilan': _namaPanggilanController.text,
                              'username': _usernameController.text,
                              'no_whatsapp': _whatsappController.text,
                            };

                            Map<String, dynamic> result;
                            if (isEditing) {
                              result = await UserService.updateUser(user.id, data);
                            } else {
                              data['password'] = _passwordController.text;
                              data['role_level'] = _selectedRole;
                              result = await UserService.createUser(data);
                            }

                            if (result['success']) {
                              if (context.mounted) {
                                Navigator.pop(context);
                                _showSnackbar(isEditing ? 'Pengguna berhasil diupdate' : 'Pengguna berhasil dibuat');
                                _loadData();
                              }
                            } else {
                              setStateDialog(() => isLoadingSubmit = false);
                              _showSnackbar(result['message'], isError: true);
                            }
                          }
                        },
                  child: isLoadingSubmit ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Simpan'),
                ),
              ],
            );
          }
        );
      },
    );
  }

  Future<void> _confirmAction(String title, String content, Future<Map<String, dynamic>> Function() action, {bool isDestructive = false}) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: Text(content),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: isDestructive ? Colors.red : Colors.indigo),
            onPressed: () => Navigator.pop(context, true),
            child: Text('Ya, Lanjutkan', style: TextStyle(color: isDestructive ? Colors.white : Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      setState(() => _isLoading = true);
      final result = await action();
      if (result['success']) {
        _showSnackbar(result['message'] ?? 'Aksi berhasil dilakukan');
        _loadData();
      } else {
        _handleError(result['message']);
      }
    }
  }
  
  void _showRoleDialog(UserModel user) {
    String selectedRole = user.roleLevel;
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Ubah Role'),
        content: DropdownButtonFormField<String>(
          initialValue: selectedRole,
          decoration: const InputDecoration(labelText: 'Pilih Role Baru', border: OutlineInputBorder()),
          items: const [
            DropdownMenuItem(value: 'admin', child: Text('Admin')),
            DropdownMenuItem(value: 'pengelola', child: Text('Pengelola')),
            DropdownMenuItem(value: 'anggota', child: Text('Anggota')),
          ],
          onChanged: (val) {
            if (val != null) selectedRole = val;
          },
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              if (selectedRole != user.roleLevel) {
                _confirmAction(
                  'Ubah Role', 
                  'Anda yakin ingin mengubah role ${user.namaLengkap} menjadi $selectedRole?', 
                  () => UserService.changeRole(user.id, selectedRole)
                );
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Kelola Pengguna')),
      drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                children: [
                  TextField(
                    decoration: InputDecoration(
                      hintText: 'Cari nama atau username...',
                      prefixIcon: const Icon(Icons.search),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                    onChanged: (val) {
                      setState(() {
                        _searchQuery = val;
                        _applyFilters();
                      });
                    },
                  ),
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: ['Semua', 'Admin', 'Pengelola', 'Anggota'].map((role) {
                        final isSelected = _roleFilter == role;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8.0),
                          child: FilterChip(
                            label: Text(role),
                            selected: isSelected,
                            onSelected: (selected) {
                              setState(() {
                                _roleFilter = role;
                                _applyFilters();
                              });
                            },
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showUserForm(),
        icon: const Icon(Icons.person_add),
        label: const Text('Tambah Pengguna'),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _users.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _users.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_filteredUsers.isEmpty) {
      return const Center(child: Text('Tidak ada pengguna yang sesuai.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.only(left: 16, right: 16, bottom: 80),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _filteredUsers.length,
      itemBuilder: (context, index) {
        final user = _filteredUsers[index];
        final isActive = user.statusAktif == 1;
        final isMe = _currentUser?.id == user.id;

        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: ExpansionTile(
            leading: CircleAvatar(
              backgroundColor: isActive ? Colors.indigo.shade100 : Colors.grey.shade300,
              child: Text(
                user.namaPanggilan.isNotEmpty ? user.namaPanggilan.substring(0, 1).toUpperCase() : '?',
                style: TextStyle(color: isActive ? Colors.indigo : Colors.grey.shade600, fontWeight: FontWeight.bold),
              ),
            ),
            title: Text(
              '${user.namaLengkap} ${isMe ? '(Anda)' : ''}', 
              style: TextStyle(fontWeight: FontWeight.bold, color: isActive ? Colors.black87 : Colors.grey),
            ),
            subtitle: Text(
              '${user.username} • ${user.roleLevel.toUpperCase()}',
              style: TextStyle(color: isActive ? Colors.grey.shade700 : Colors.grey),
            ),
            childrenPadding: const EdgeInsets.all(16),
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _buildActionButton(Icons.edit, 'Edit', Colors.blue, () => _showUserForm(user: user)),
                  _buildActionButton(Icons.manage_accounts, 'Ubah Role', Colors.orange, () => _showRoleDialog(user)),
                  _buildActionButton(Icons.lock_reset, 'Reset Pass', Colors.brown, () {
                    _confirmAction(
                      'Reset Password', 
                      'Anda yakin ingin mengembalikan password ${user.namaLengkap} ke "lugasjosjis"?', 
                      () => UserService.resetPassword(user.id)
                    );
                  }),
                  _buildActionButton(
                    isActive ? Icons.block : Icons.check_circle, 
                    isActive ? 'Nonaktifkan' : 'Aktifkan', 
                    isActive ? Colors.red : Colors.green, 
                    () {
                      _confirmAction(
                        isActive ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna', 
                        'Anda yakin ingin ${isActive ? 'menonaktifkan' : 'mengaktifkan'} ${user.namaLengkap}?', 
                        () => UserService.toggleStatus(user.id),
                        isDestructive: isActive,
                      );
                    }
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildActionButton(IconData icon, String label, Color color, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.all(8.0),
        child: Column(
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 4),
            Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}
