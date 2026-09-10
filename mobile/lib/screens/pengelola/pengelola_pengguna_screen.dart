import 'package:flutter/material.dart';
import 'dart:async';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/feedback_dialogs.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import 'package:mobile/screens/widgets/common/app_dialog.dart';
import '../widgets/animations/fade_in_slide.dart';

class PengelolaPenggunaScreen extends StatefulWidget {
  const PengelolaPenggunaScreen({super.key});

  @override
  State<PengelolaPenggunaScreen> createState() =>
      _PengelolaPenggunaScreenState();
}

class _PengelolaPenggunaScreenState extends State<PengelolaPenggunaScreen> {
  UserModel? _currentUser;
  List<String> _rtOptions = [];

  String _searchQuery = '';
  String? _rtFilter;
  Timer? _debounce;
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _initLoad();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _initLoad() async {
    final optionsResult = await UserService.getFilterOptions();
    if (optionsResult['success'] && mounted) {
      setState(() {
        _rtOptions = List<String>.from(optionsResult['data']['rt'] ?? []);
      });
    }

    final userResult = await AuthService.getMe();
    if (userResult['success'] && mounted) {
      setState(() {
        _currentUser = userResult['user'];
      });
    }
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      setState(() {
        _searchQuery = query;
      });
    });
  }

  void _onRtFilterChanged(String? rt) {
    setState(() {
      _rtFilter = rt;
    });
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: null,
        drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
        backgroundColor: AppTheme.background,
        body: NestedScrollView(
          headerSliverBuilder: (context, innerBoxIsScrolled) {
            return [
              SliverAppBar(
                title: const Text(
                  'Kelola Anggota',
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
                  preferredSize: const Size.fromHeight(180),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Search Bar
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                        child: TextField(
                          controller: _searchController,
                          decoration: InputDecoration(
                            hintText: 'Cari nama atau username...',
                            prefixIcon: const Icon(Icons.search, color: AppTheme.textSecondary),
                            suffixIcon: _searchQuery.isNotEmpty
                                ? IconButton(
                                    icon: const Icon(Icons.clear, color: AppTheme.textSecondary),
                                    onPressed: () {
                                      _searchController.clear();
                                      _onSearchChanged('');
                                    },
                                  )
                                : null,
                            filled: true,
                            fillColor: Colors.white,
                            border: OutlineInputBorder(
                              borderRadius: AppTheme.radiusLarge,
                              borderSide: BorderSide.none,
                            ),
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                          ),
                          onChanged: _onSearchChanged,
                        ),
                      ),

                      // RT Filter Horizontal Scroll
                      if (_rtOptions.isNotEmpty)
                        SizedBox(
                          height: 40,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            children: [null, ..._rtOptions].map((rt) {
                              final isSelected = _rtFilter == rt;
                              final label = rt == null ? 'Semua RT' : 'RT 0$rt';
                              return Padding(
                                padding: const EdgeInsets.only(right: 8.0),
                                child: FilterChip(
                                  label: Text(label),
                                  selected: isSelected,
                                  selectedColor: Colors.white.withOpacity(0.2),
                                  checkmarkColor: Colors.white,
                                  labelStyle: TextStyle(
                                    color: isSelected ? Colors.white : Colors.white.withOpacity(0.8),
                                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                                  ),
                                  backgroundColor: Colors.white.withOpacity(0.1),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: AppTheme.radiusLarge,
                                    side: BorderSide(
                                      color: isSelected
                                          ? Colors.white
                                          : Colors.white.withOpacity(0.3),
                                    ),
                                  ),
                                  onSelected: (_) => _onRtFilterChanged(rt?.toString()),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      const SizedBox(height: 10),

                      // Tab Bar
                      TabBar(
                        indicatorColor: Colors.white,
                        indicatorWeight: 3,
                        labelColor: Colors.white,
                        unselectedLabelColor: Colors.white.withOpacity(0.6),
                        labelStyle: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 15,
                        ),
                        tabs: const [
                          Tab(text: 'Aktif'),
                          Tab(text: 'Nonaktif'),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ];
          },
          body: TabBarView(
            children: [
              _UserListTab(
                isActiveTab: true,
                searchQuery: _searchQuery,
                rtFilter: _rtFilter,
                currentUser: _currentUser,
              ),
              _UserListTab(
                isActiveTab: false,
                searchQuery: _searchQuery,
                rtFilter: _rtFilter,
                currentUser: _currentUser,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _UserListTab extends StatefulWidget {
  final bool isActiveTab;
  final String searchQuery;
  final String? rtFilter;
  final UserModel? currentUser;

  const _UserListTab({
    required this.isActiveTab,
    required this.searchQuery,
    this.rtFilter,
    required this.currentUser,
  });

  @override
  State<_UserListTab> createState() => _UserListTabState();
}

class _UserListTabState extends State<_UserListTab>
    with AutomaticKeepAliveClientMixin {
  List<UserModel> _users = [];
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _hasMoreData = true;
  String? _errorMessage;

  int _currentPage = 1;
  final int _limit = 20;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void didUpdateWidget(covariant _UserListTab oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.searchQuery != widget.searchQuery ||
        oldWidget.rtFilter != widget.rtFilter) {
      _loadData();
    }
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    _currentPage = 1;
    _hasMoreData = true;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _users.clear();
    });
    await _fetchUsers(isLoadMore: false);
  }

  Future<void> _loadMoreData() async {
    if (_isLoadingMore || !_hasMoreData || _isLoading) return;
    setState(() {
      _isLoadingMore = true;
    });
    _currentPage++;
    await _fetchUsers(isLoadMore: true);
  }

  Future<void> _fetchUsers({required bool isLoadMore}) async {
    final statusQuery = widget.isActiveTab ? 'aktif' : 'nonaktif';
    final usersResult = await UserService.getUsers(
      page: _currentPage,
      limit: _limit,
      search: widget.searchQuery,
      status: statusQuery,
      role: 'Semua',
    );

    if (!mounted) return;

    if (usersResult['success']) {
      List<UserModel> fetchedUsers = usersResult['users'] as List<UserModel>;

      // Apply RT filter locally if set (since API might not support RT filtering directly)
      if (widget.rtFilter != null) {
        fetchedUsers = fetchedUsers
            .where((u) => u.rt.toString() == widget.rtFilter)
            .toList();
      }

      if ((usersResult['users'] as List).length < _limit) {
        _hasMoreData = false;
      }

      setState(() {
        if (isLoadMore) {
          _users.addAll(fetchedUsers);
        } else {
          _users = fetchedUsers;
        }
        _isLoading = false;
        _isLoadingMore = false;
      });
    } else {
      if (!isLoadMore) {
        setState(() {
          _isLoading = false;
          _errorMessage = usersResult['message'] ?? 'Data gagal dimuat.';
        });
      } else {
        setState(() => _isLoadingMore = false);
        FeedbackDialogs.showSnackbar(
          context,
          'Gagal memuat lebih banyak data',
          isError: true,
        );
      }
    }
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

  @override
  Widget build(BuildContext context) {
    super.build(context); // Required for AutomaticKeepAliveClientMixin

    return NotificationListener<ScrollNotification>(
      onNotification: (ScrollNotification scrollInfo) {
        if (scrollInfo.metrics.pixels >=
            scrollInfo.metrics.maxScrollExtent - 200) {
          _loadMoreData();
        }
        return false;
      },
      child: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildList(),
      ),
    );
  }

  Widget _buildList() {
    if (_isLoading && _users.isEmpty) {
      return const Center(
        child: CustomLoadingIndicator(color: AppTheme.primary),
      );
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
            ElevatedButton(
              onPressed: _loadData,
              child: const Text('Coba Lagi'),
            ),
          ],
        ),
      );
    }

    if (_users.isEmpty) {
      return Center(
        child: ListView(
          shrinkWrap: true,
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.search_off_outlined,
                  size: 80,
                  color: Colors.grey.shade300,
                ),
                const SizedBox(height: 16),
                Text(
                  'Tidak ada pengguna ${widget.isActiveTab ? "aktif" : "nonaktif"}.',
                  style: const TextStyle(
                    color: AppTheme.textSecondary,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.only(top: 20, left: 20, right: 20, bottom: 80),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _users.length + (_isLoadingMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index == _users.length) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 20),
            child: CustomLoadingIndicator(color: AppTheme.primary),
          );
        }

        final user = _users[index];
        return FadeInSlide(
          delay: 0.1 * (index % 10),
          child: _buildUserCard(user),
        );
      },
    );
  }

  Widget _buildUserCard(UserModel user) {
    final isActive = user.statusAktif == 1;
    final isMe = widget.currentUser?.id == user.id;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 15,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          collapsedBackgroundColor: Colors.transparent,
          backgroundColor: Colors.transparent,
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          leading: Container(
            padding: const EdgeInsets.all(2),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: isActive
                    ? AppTheme.primary.withOpacity(0.3)
                    : Colors.grey.shade300,
                width: 2,
              ),
            ),
            child: CircleAvatar(
              backgroundColor: isActive
                  ? AppTheme.primary.withOpacity(0.1)
                  : Colors.grey.shade100,
              radius: 22,
              child: Text(
                user.namaPanggilan.isNotEmpty
                    ? user.namaPanggilan.substring(0, 1).toUpperCase()
                    : '?',
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
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isActive ? AppTheme.textPrimary : Colors.grey,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (!isActive) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppTheme.error.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Text(
                    'NONAKTIF',
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.error,
                    ),
                  ),
                ),
              ],
            ],
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 6.0),
            child: Text(
              '${user.username} • RT 0${user.rt} • ${user.roleLevel.toUpperCase()}',
              style: TextStyle(
                color: isActive ? AppTheme.textSecondary : Colors.grey.shade400,
                fontSize: 13,
              ),
            ),
          ),
          childrenPadding: const EdgeInsets.all(0),
          children: [
            Container(
              color: Colors.grey.shade50,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  if (widget.currentUser?.roleLevel == 'ketua' && !isMe)
                    _buildActionButton(
                      Icons.manage_accounts,
                      'Ubah Role',
                      AppTheme.primary,
                      () {
                        _showChangeRoleDialog(user);
                      },
                    ),
                  if (widget.currentUser?.roleLevel == 'ketua' && !isMe)
                    _buildActionButton(
                      Icons.lock_reset,
                      'Reset Pass',
                      Colors.purple,
                      () => _resetPassword(user),
                    ),
                  if (widget.currentUser?.roleLevel == 'ketua' && !isMe)
                    _buildActionButton(
                      isActive ? Icons.person_off : Icons.person_add,
                      isActive ? 'Nonaktifkan' : 'Aktifkan',
                      isActive ? AppTheme.error : AppTheme.success,
                      () {
                        _confirmAction(
                          isActive
                              ? 'Nonaktifkan Pengguna'
                              : 'Aktifkan Pengguna',
                          isActive
                              ? 'Pengguna ini tidak akan bisa login lagi.'
                              : 'Pengguna akan kembali bisa login.',
                          () => UserService.toggleStatus(user.id),
                          isDestructive: isActive,
                        );
                      },
                    ),
                  if (widget.currentUser?.roleLevel != 'ketua' || isMe)
                    const Text(
                      'Tidak ada aksi lanjutan tersedia.',
                      style: TextStyle(
                        color: Colors.grey,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmAction(
    String title,
    String content,
    Future<Map<String, dynamic>> Function() action, {
    bool isDestructive = false,
  }) async {
    final confirm = await AppDialog.showConfirmation(
      context: context,
      title: title,
      content: content,
      type: isDestructive ? DialogType.error : DialogType.warning,
    );

    if (confirm == true) {
      setState(() => _isLoading = true);
      final result = await action();
      if (result['success']) {
        _showSnackbar(result['message'] ?? 'Aksi berhasil dilakukan');
        _loadData(); // Re-fetch to apply status changes immediately
      } else {
        setState(() {
          _isLoading = false;
        });
        _showSnackbar(result['message'] ?? 'Aksi gagal.', isError: true);
      }
    }
  }

  void _resetPassword(UserModel user) async {
    final confirm = await FeedbackDialogs.showConfirmation(
      context: context,
      title: 'Reset Password',
      content:
          'Anda yakin ingin mereset password ${user.namaLengkap}? Pengguna akan dipaksa mengganti password pada login berikutnya.',
      isDestructive: true,
    );

    if (confirm == true) {
      setState(() => _isLoading = true);
      final result = await UserService.resetPassword(user.id);

      if (result['success']) {
        if (!mounted) return;
        final tempPass = result['temporary_password'] ?? '-';
        await AppDialog.showResult(
          context: context,
          title: 'Password Direset',
          type: DialogType.success,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Silakan berikan password sementara ini kepada ${user.namaLengkap}:',
                textAlign: TextAlign.center,
              ),
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
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.primary,
                      letterSpacing: 2,
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
        setState(() => _isLoading = false);
      } else {
        setState(() => _isLoading = false);
        _showSnackbar(result['message'] ?? 'Gagal reset.', isError: true);
      }
    }
  }

  Widget _buildActionButton(
    IconData icon,
    String label,
    Color color,
    VoidCallback onTap,
  ) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppTheme.radiusSmall,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 24),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
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
          return Dialog(
            shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
            insetPadding: const EdgeInsets.all(20),
            child: Container(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Ubah Role',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
                  ),
                  const SizedBox(height: 16),
                  SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: roles.map((role) {
                        return RadioListTile<String>(
                          title: Text(
                            role.toUpperCase(),
                            style: const TextStyle(fontSize: 14),
                          ),
                          value: role,
                          groupValue: selectedRole,
                          contentPadding: EdgeInsets.zero,
                          activeColor: AppTheme.primary,
                          onChanged: (val) {
                            if (val != null) {
                              setDialogState(() => selectedRole = val);
                            }
                          },
                        );
                      }).toList(),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text(
                          'Batal',
                          style: TextStyle(color: AppTheme.textSecondary),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: AppTheme.radiusMedium,
                          ),
                        ),
                        onPressed: () async {
                          Navigator.pop(context);
                          setState(() => _isLoading = true);
                          final result = await UserService.changeRole(
                            user.id,
                            selectedRole,
                          );
                          if (result['success']) {
                            _showSnackbar('Role berhasil diubah');
                            _loadData();
                          } else {
                            setState(() => _isLoading = false);
                            _showSnackbar(
                              result['message'] ?? 'Gagal ubah role',
                              isError: true,
                            );
                          }
                        },
                        child: const Text(
                          'Simpan',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
