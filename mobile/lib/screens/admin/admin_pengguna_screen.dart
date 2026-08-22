import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
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
        backgroundColor: isError ? AppTheme.error : AppTheme.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
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
              title: Text(isEditing ? 'Edit Pengguna' : 'Tambah Pengguna', style: const TextStyle(fontWeight: FontWeight.bold)),
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
              content: SingleChildScrollView(
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: _namaLengkapController,
                        decoration: InputDecoration(
                          labelText: 'Nama Lengkap', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Nama lengkap wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _namaPanggilanController,
                        decoration: InputDecoration(
                          labelText: 'Nama Panggilan', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Nama panggilan wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _usernameController,
                        decoration: InputDecoration(
                          labelText: 'Username', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Username wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _whatsappController,
                        decoration: InputDecoration(
                          labelText: 'No. WhatsApp', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                      ),
                      const SizedBox(height: 16),
                      if (!isEditing)
                        TextFormField(
                          controller: _passwordController,
                          decoration: InputDecoration(
                            labelText: 'Password', 
                            border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                          ),
                          obscureText: true,
                          validator: (value) => value == null || value.length < 6 ? 'Password min. 6 karakter' : null,
                        ),
                      if (!isEditing) const SizedBox(height: 16),
                      if (!isEditing)
                        DropdownButtonFormField<String>(
                          initialValue: _selectedRole,
                          decoration: InputDecoration(
                            labelText: 'Role', 
                            border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                          ),
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
                  child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary)),
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
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                  ),
                  child: isLoadingSubmit 
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) 
                      : const Text('Simpan'),
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
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        content: Text(content),
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false), 
            child: const Text('Batalkan', style: TextStyle(color: AppTheme.textSecondary))
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: isDestructive ? AppTheme.error : AppTheme.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
            ),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Konfirmasi', style: TextStyle(fontWeight: FontWeight.bold)),
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
        title: const Text('Ubah Role', style: TextStyle(fontWeight: FontWeight.bold)),
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
        content: DropdownButtonFormField<String>(
          initialValue: selectedRole,
          decoration: InputDecoration(
            labelText: 'Pilih Role Baru', 
            border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium)
          ),
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
          TextButton(
            onPressed: () => Navigator.pop(context), 
            child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary))
          ),
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
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
            ),
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kelola Pengguna'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                children: [
                  TextField(
                    decoration: InputDecoration(
                      hintText: 'Cari nama atau username...',
                      prefixIcon: const Icon(Icons.search, color: AppTheme.textSecondary),
                      filled: true,
                      fillColor: AppTheme.surface,
                      border: OutlineInputBorder(
                        borderRadius: AppTheme.radiusLarge,
                        borderSide: BorderSide.none,
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: AppTheme.radiusLarge,
                        borderSide: BorderSide.none,
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: AppTheme.radiusLarge,
                        borderSide: const BorderSide(color: AppTheme.primary),
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    ),
                    onChanged: (val) {
                      setState(() {
                        _searchQuery = val;
                        _applyFilters();
                      });
                    },
                  ),
                  const SizedBox(height: 16),
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
                            selectedColor: AppTheme.primary.withValues(alpha: 0.15),
                            checkmarkColor: AppTheme.primary,
                            labelStyle: TextStyle(
                              color: isSelected ? AppTheme.primary : AppTheme.textSecondary,
                              fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                            ),
                            backgroundColor: AppTheme.surface,
                            shape: RoundedRectangleBorder(
                              borderRadius: AppTheme.radiusLarge,
                              side: BorderSide(
                                color: isSelected ? AppTheme.primary.withValues(alpha: 0.5) : Colors.grey.shade300,
                              ),
                            ),
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
        backgroundColor: AppTheme.primary,
        icon: const Icon(Icons.person_add_outlined, color: Colors.white),
        label: const Text('Tambah', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _users.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _users.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_filteredUsers.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off_outlined, size: 80, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            const Text('Tidak ada pengguna yang sesuai.', style: TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.only(left: 20, right: 20, bottom: 80),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _filteredUsers.length,
      itemBuilder: (context, index) {
        final user = _filteredUsers[index];
        final isActive = user.statusAktif == 1;
        final isMe = _currentUser?.id == user.id;

        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          clipBehavior: Clip.antiAlias,
          child: Theme(
            data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
            child: ExpansionTile(
              collapsedBackgroundColor: Colors.transparent,
              backgroundColor: Colors.transparent,
              tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: Container(
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: isActive ? AppTheme.primary.withValues(alpha: 0.3) : Colors.grey.shade300, 
                    width: 2
                  ),
                ),
                child: CircleAvatar(
                  backgroundColor: isActive ? AppTheme.primary.withValues(alpha: 0.1) : Colors.grey.shade100,
                  radius: 22,
                  child: Text(
                    user.namaPanggilan.isNotEmpty ? user.namaPanggilan.substring(0, 1).toUpperCase() : '?',
                    style: TextStyle(
                      color: isActive ? AppTheme.primary : Colors.grey.shade500, 
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ),
              ),
              title: Row(
                children: [
                  Expanded(
                    child: Text(
                      '${user.namaLengkap} ${isMe ? '(Anda)' : ''}', 
                      style: TextStyle(fontWeight: FontWeight.bold, color: isActive ? AppTheme.textPrimary : Colors.grey),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (!isActive) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.error.withValues(alpha: 0.1),
                        borderRadius: AppTheme.radiusSmall,
                      ),
                      child: const Text('NONAKTIF', style: TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: AppTheme.error)),
                    )
                  ]
                ],
              ),
              subtitle: Padding(
                padding: const EdgeInsets.only(top: 4.0),
                child: Text(
                  '${user.username} • ${user.roleLevel.toUpperCase()}',
                  style: TextStyle(color: isActive ? AppTheme.textSecondary : Colors.grey.shade400, fontSize: 13),
                ),
              ),
              childrenPadding: const EdgeInsets.all(0),
              children: [
                Container(
                  color: Colors.grey.shade50,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _buildActionButton(Icons.edit_outlined, 'Edit', AppTheme.info, () => _showUserForm(user: user)),
                      _buildActionButton(Icons.manage_accounts_outlined, 'Role', AppTheme.warning, () => _showRoleDialog(user)),
                      _buildActionButton(Icons.lock_reset, 'Password', Colors.brown.shade600, () {
                        _confirmAction(
                          'Reset Password', 
                          'Password pengguna akan direset ke password sementara. Pengguna harus mengganti password saat login berikutnya.', 
                          () => UserService.resetPassword(user.id)
                        );
                      }),
                      _buildActionButton(
                        isActive ? Icons.block : Icons.check_circle_outline, 
                        isActive ? 'Nonaktifkan' : 'Aktifkan', 
                        isActive ? AppTheme.error : AppTheme.success, 
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
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildActionButton(IconData icon, String label, Color color, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppTheme.radiusSmall,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8.0, vertical: 4.0),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(height: 6),
            Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

