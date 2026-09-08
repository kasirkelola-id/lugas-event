import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_dialog.dart';
import '../../services/auth_service.dart';
import '../widgets/app_drawer.dart';

class PengelolaApprovalScreen extends StatefulWidget {
  const PengelolaApprovalScreen({super.key});

  @override
  State<PengelolaApprovalScreen> createState() =>
      _PengelolaApprovalScreenState();
}

class _PengelolaApprovalScreenState extends State<PengelolaApprovalScreen> {
  List<UserModel> _pendingUsers = [];
  bool _isLoading = true;
  String? _errorMessage;
  UserModel? _currentUser;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    if (userResult['success']) {
      _currentUser = userResult['user'];
    }

    final result = await UserService.getPendingMembers();
    if (!mounted) return;

    if (result['success']) {
      final List<dynamic> list = result['data'];
      setState(() {
        _pendingUsers = list.map((e) => UserModel.fromJson(e)).toList();
        _isLoading = false;
      });
    } else {
      setState(() {
        _errorMessage = result['message'] ?? 'Gagal memuat data';
        _isLoading = false;
      });
    }
  }

  void _handleApprove(UserModel user) async {
    final confirm = await AppDialog.showConfirmation(
      context: context,
      title: 'Setujui Pendaftaran',
      content: 'Setujui ${user.namaLengkap} sebagai anggota?',
      type: DialogType.info,
    );

    if (confirm == true) {
      if (!mounted) return;
      AppDialog.showLoading(context, message: 'Menyetujui...');

      final result = await UserService.approveMember(user.id);
      if (!mounted) return;
      Navigator.pop(context); // close loading

      if (result['success']) {
        await AppDialog.showResult(
          context: context,
          title: 'Berhasil',
          content: 'Anggota disetujui',
          type: DialogType.success,
        );
        _loadData();
      } else {
        await AppDialog.showResult(
          context: context,
          title: 'Gagal',
          content: result['message'] ?? 'Gagal menyetujui anggota',
          type: DialogType.error,
        );
      }
    }
  }

  void _handleReject(UserModel user) async {
    final confirm = await AppDialog.showConfirmation(
      context: context,
      title: 'Tolak Pendaftaran',
      content: 'Tolak pendaftaran ${user.namaLengkap}?',
      type: DialogType.error,
    );

    if (confirm == true) {
      if (!mounted) return;
      AppDialog.showLoading(context, message: 'Menolak...');

      final result = await UserService.rejectMember(user.id);
      if (!mounted) return;
      Navigator.pop(context); // close loading

      if (result['success']) {
        await AppDialog.showResult(
          context: context,
          title: 'Pendaftaran Ditolak',
          content: 'Pendaftaran anggota telah ditolak',
          type: DialogType.success,
        );
        _loadData();
      } else {
        await AppDialog.showResult(
          context: context,
          title: 'Gagal',
          content: result['message'] ?? 'Gagal menolak anggota',
          type: DialogType.error,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
      appBar: AppBar(
        title: const Text('Persetujuan Anggota'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _pendingUsers.isEmpty) {
      return const Center(
        child: CustomLoadingIndicator(color: AppTheme.primary),
      );
    }

    if (_errorMessage != null && _pendingUsers.isEmpty) {
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

    if (_pendingUsers.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.check_circle_outline,
              size: 80,
              color: Colors.grey.shade300,
            ),
            const SizedBox(height: 16),
            const Text(
              'Tidak ada pendaftaran yang menunggu persetujuan.',
              style: TextStyle(color: AppTheme.textSecondary, fontSize: 14),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      color: AppTheme.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(20),
        itemCount: _pendingUsers.length,
        itemBuilder: (context, index) {
          final user = _pendingUsers[index];
          return Card(
            elevation: 2,
            margin: const EdgeInsets.only(bottom: 16),
            shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: AppTheme.primary.withValues(
                          alpha: 0.1,
                        ),
                        child: Text(
                          user.namaPanggilan.isNotEmpty
                              ? user.namaPanggilan[0].toUpperCase()
                              : '?',
                          style: const TextStyle(
                            color: AppTheme.primary,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user.namaLengkap,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                            Text(
                              '@${user.username} • RT ${user.rt.toString().padLeft(2, '0')}',
                              style: const TextStyle(
                                color: AppTheme.textSecondary,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'No. WhatsApp: ${user.noWhatsapp}',
                    style: const TextStyle(fontSize: 13),
                  ),
                  // Text('Tgl Daftar: ${user.createdAt}', style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      OutlinedButton(
                        onPressed: () => _handleReject(user),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.error,
                          side: const BorderSide(color: AppTheme.error),
                        ),
                        child: const Text('Tolak'),
                      ),
                      const SizedBox(width: 12),
                      ElevatedButton(
                        onPressed: () => _handleApprove(user),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                        ),
                        child: const Text('Setujui'),
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
