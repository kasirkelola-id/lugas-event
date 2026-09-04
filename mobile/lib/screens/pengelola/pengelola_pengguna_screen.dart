import 'package:flutter/material.dart';
import 'dart:async';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/feedback_dialogs.dart';
import '../widgets/common/custom_button.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class PengelolaPenggunaScreen extends StatefulWidget {
  const PengelolaPenggunaScreen({super.key});

  @override
  State<PengelolaPenggunaScreen> createState() => _PengelolaPenggunaScreenState();
}

class _PengelolaPenggunaScreenState extends State<PengelolaPenggunaScreen> {
  UserModel? _currentUser;
  List<UserModel> _users = [];
  List<UserModel> _filteredUsers = [];
  
  bool _isLoading = true;
  String? _errorMessage;
  
  String _searchQuery = '';
  String _roleFilter = 'Semua';
  int? _rtFilter;
  
  Timer? _debounce;
  int _currentPage = 1;
  int _limit = 100; // MVP simple pagination

  final _namaLengkapController = TextEditingController();
  final _namaPanggilanController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _whatsappController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadData();
  }
  
  @override
  void dispose() {
    _debounce?.cancel();
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

    final usersResult = await UserService.getUsers(
      page: _currentPage,
      limit: _limit,
      search: _searchQuery,
      role: _roleFilter,
      status: '', // Not filtering by status in MVP for simplicity, or we could add a tab
    );
    if (!mounted) return;

    if (usersResult['success']) {
      setState(() {
        _currentUser = userResult['user'];
        _users = usersResult['users'] as List<UserModel>;
        
        // Local RT filter application since backend doesn't filter RT currently
        if (_rtFilter != null) {
          _filteredUsers = _users.where((u) => u.rt == _rtFilter).toList();
        } else {
          _filteredUsers = _users;
        }
        
        _isLoading = false;
      });
    } else {
      _handleError(usersResult['message'] ?? 'Data pengguna gagal dimuat.');
    }
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      setState(() {
        _searchQuery = query;
      });
      _loadData();
    });
  }

  void _applyFilters() {
    _loadData(); // Re-fetch from server when roles/search change
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
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kelola Anggota'),
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
                    onChanged: _onSearchChanged,
                  ),
                  const SizedBox(height: 16),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: ['Semua', 'Admin', 'Ketua', 'Sekretaris', 'Bendahara', 'Pengelola', 'Anggota'].map((role) {
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
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [null, 1, 2, 3, 4].map((rt) {
                        final isSelected = _rtFilter == rt;
                        final label = rt == null ? 'Semua RT' : 'RT 0$rt';
                        return Padding(
                          padding: const EdgeInsets.only(right: 8.0),
                          child: FilterChip(
                            label: Text(label),
                            selected: isSelected,
                            selectedColor: AppTheme.info.withValues(alpha: 0.15),
                            checkmarkColor: AppTheme.info,
                            labelStyle: TextStyle(
                              color: isSelected ? AppTheme.info : AppTheme.textSecondary,
                              fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                            ),
                            backgroundColor: AppTheme.surface,
                            shape: RoundedRectangleBorder(
                              borderRadius: AppTheme.radiusLarge,
                              side: BorderSide(
                                color: isSelected ? AppTheme.info.withValues(alpha: 0.5) : Colors.grey.shade300,
                              ),
                            ),
                            onSelected: (selected) {
                              setState(() {
                                _rtFilter = rt;
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
    );
  }

  Widget _buildBody() {
    if (_isLoading && _users.isEmpty) {
      return const Center(child: CustomLoadingIndicator(color: AppTheme.primary));
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
                  '${user.username} • RT 0${user.rt} • ${user.roleLevel.toUpperCase()}',
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
                      if (_currentUser?.roleLevel == 'ketua' && !isMe)
                        _buildActionButton(Icons.manage_accounts, 'Ubah Role', AppTheme.primary, () {
                          _showChangeRoleDialog(user);
                        }),
                      if (_currentUser?.roleLevel == 'ketua' && !isMe)
                        _buildActionButton(Icons.lock_reset, 'Reset', Colors.purple, () => _resetPassword(user)),
                      if (_currentUser?.roleLevel == 'ketua' && !isMe)
                        _buildActionButton(
                          isActive ? Icons.person_off : Icons.person, 
                          isActive ? 'Nonaktifkan' : 'Aktifkan', 
                          isActive ? AppTheme.error : AppTheme.success, 
                          () {
                          _confirmAction(
                            isActive ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna', 
                            isActive ? 'Pengguna ini tidak akan bisa login lagi.' : 'Pengguna akan kembali bisa login.', 
                            () => UserService.toggleStatus(user.id),
                            isDestructive: isActive
                          );
                        }),
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

  void _resetPassword(UserModel user) async {
    final confirm = await FeedbackDialogs.showConfirmation(
      context: context,
      title: 'Reset Password',
      content: 'Anda yakin ingin mereset password ${user.namaLengkap}? Pengguna akan dipaksa mengganti password pada login berikutnya.',
      isDestructive: true,
    );

    if (confirm == true) {
      setState(() => _isLoading = true);
      final result = await UserService.resetPassword(user.id);
      
      if (result['success']) {
        if (!mounted) return;
        final tempPass = result['temporary_password'] ?? '-';
        await showDialog(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Password Berhasil Direset', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.success)),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Silakan berikan password sementara ini kepada ${user.namaLengkap}:'),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.surface,
                    borderRadius: AppTheme.radiusMedium,
                    border: Border.all(color: AppTheme.primary),
                  ),
                  child: Center(
                    child: SelectableText(
                      tempPass,
                      style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.primary, letterSpacing: 2),
                    ),
                  ),
                ),
              ],
            ),
            actions: [
              CustomButton(
                text: 'Tutup',
                onPressed: () => Navigator.pop(context),
                isFullWidth: true,
              )
            ],
          )
        );
        _loadData();
      } else {
        _handleError(result['message']);
      }
    }
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

  void _showChangeRoleDialog(UserModel user) {
    String selectedRole = user.roleLevel;
    final roles = ['ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota'];
    
    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: const Text('Ubah Role', style: TextStyle(fontWeight: FontWeight.bold)),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: roles.map((role) {
                  return RadioListTile<String>(
                    title: Text(role.toUpperCase(), style: const TextStyle(fontSize: 14)),
                    value: role,
                    groupValue: selectedRole,
                    contentPadding: EdgeInsets.zero,
                    onChanged: (val) {
                      if (val != null) {
                        setDialogState(() => selectedRole = val);
                      }
                    },
                  );
                }).toList(),
              ),
            ),
            shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary)),
              ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                ),
                onPressed: () async {
                  Navigator.pop(context);
                  setState(() => _isLoading = true);
                  final result = await UserService.changeRole(user.id, selectedRole);
                  if (result['success']) {
                    _showSnackbar('Role berhasil diubah');
                    _loadData();
                  } else {
                    _handleError(result['message']);
                  }
                },
                child: const Text('Simpan', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ],
          );
        }
      ),
    );
  }
}



