import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/user_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class AdminRoleScreen extends StatefulWidget {
  const AdminRoleScreen({super.key});

  @override
  State<AdminRoleScreen> createState() => _AdminRoleScreenState();
}

class _AdminRoleScreenState extends State<AdminRoleScreen> {
  UserModel? _user;
  bool _isLoading = true;
  String? _errorMessage;
  Map<String, dynamic>? _summary;

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
    if (mounted && userResult['success']) {
      _user = userResult['user'];
    }

    final summaryResult = await UserService.getRolesSummary();
    if (mounted) {
      if (summaryResult['success']) {
        setState(() {
          _summary = summaryResult['data'];
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = summaryResult['message'] ?? 'Gagal memuat data role';
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Role & Hak Akses'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null) {
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

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSummaryHeader(),
          const SizedBox(height: 32),
          const Text(
            'Informasi Hak Akses',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Admin (Administrator)',
            color: AppTheme.error,
            icon: Icons.admin_panel_settings_outlined,
            count: _summary?['admin'] ?? 0,
            description: 'Memiliki kontrol penuh atas seluruh sistem Lugas.',
            capabilities: [
              'Akses semua menu dan fitur.',
              'Manajemen akun seluruh pengguna.',
              'Melihat dan mengelola seluruh acara dari semua pengelola.',
              'Reset password dan menonaktifkan pengguna.',
              'Hanya Admin yang dapat mengubah role pengguna lain.'
            ],
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Pengelola',
            color: AppTheme.primary,
            icon: Icons.manage_accounts_outlined,
            count: _summary?['pengelola'] ?? 0,
            description: 'Bertanggung jawab mengelola acara masing-masing.',
            capabilities: [
              'Membuat acara baru.',
              'Mengedit, mencetak QR, dan menutup acara miliknya.',
              'Melihat daftar hadir peserta dari acara miliknya.',
              'Melihat laporan dan histori acara miliknya.',
              'Tidak dapat melihat atau mengelola acara pengelola lain.',
              'Tidak memiliki akses ke manajemen pengguna.'
            ],
          ),
          const SizedBox(height: 16),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Anggota',
            color: AppTheme.success,
            icon: Icons.people_outline,
            count: _summary?['anggota'] ?? 0,
            description: 'Pengguna reguler yang mengikuti acara.',
            capabilities: [
              'Melihat daftar acara aktif.',
              'Melakukan scan QR untuk presensi (di masa depan).',
              'Melihat riwayat kehadiran pribadinya.',
              'Tidak memiliki akses ke dashboard pengelolaan atau admin.'
            ],
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Ketua',
            color: Colors.purple,
            icon: Icons.star_outline,
            count: _summary?['ketua'] ?? 0,
            description: 'Pemimpin dengan akses pengawasan penuh.',
            capabilities: [
              'Memiliki hak akses hampir sama dengan Admin.',
              'Melihat semua laporan dan aktivitas pengguna.',
              'Mengelola pengumuman sistem.'
            ],
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Sekretaris',
            color: Colors.blueGrey,
            icon: Icons.edit_document,
            count: _summary?['sekretaris'] ?? 0,
            description: 'Bertanggung jawab atas administrasi.',
            capabilities: [
              'Membuat dan mengelola pengumuman sistem.',
              'Melihat data pengguna dan laporan.'
            ],
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Bendahara',
            color: Colors.orange,
            icon: Icons.account_balance_wallet_outlined,
            count: _summary?['bendahara'] ?? 0,
            description: 'Bertanggung jawab atas keuangan.',
            capabilities: [
              'Mengelola fitur kas warga (pemasukan/pengeluaran).',
              'Melihat data pengguna dan laporan.'
            ],
          ),
          const SizedBox(height: 32),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppTheme.warning.withValues(alpha: 0.1),
              borderRadius: AppTheme.radiusLarge,
              border: Border.all(color: AppTheme.warning.withValues(alpha: 0.3)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.info_outline, color: AppTheme.warning),
                const SizedBox(width: 16),
                Expanded(
                  child: Text(
                    'Sistem keamanan secara otomatis mencegah penghapusan/penonaktifan jika hanya tersisa 1 Admin aktif di dalam sistem (Lockout Prevention).',
                    style: TextStyle(color: Colors.orange.shade900, fontSize: 13, height: 1.5),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildSummaryHeader() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: AppTheme.primaryGradient,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: AppTheme.shadowMedium,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Total Pengguna', style: TextStyle(color: Colors.white70, fontSize: 14, fontWeight: FontWeight.w500)),
              const SizedBox(height: 8),
              Text(
                '${_summary?['total'] ?? 0}',
                style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.analytics_outlined, color: Colors.white, size: 40),
          ),
        ],
      ),
    );
  }

  Widget _buildRoleCard({
    required String title,
    required Color color,
    required IconData icon,
    required int count,
    required String description,
    required List<String> capabilities,
  }) {
    return Container(
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
          tilePadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          leading: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: AppTheme.radiusMedium,
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          title: Text(title, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary)),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 6.0),
            child: Text('$count Akun terdaftar', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13, fontWeight: FontWeight.w500)),
          ),
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                border: Border(top: BorderSide(color: Colors.grey.shade200)),
              ),
              width: double.infinity,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(description, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: AppTheme.textPrimary)),
                  const SizedBox(height: 16),
                  const Text('HAK AKSES', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.textSecondary, letterSpacing: 1)),
                  const SizedBox(height: 12),
                  ...capabilities.map((cap) => Padding(
                    padding: const EdgeInsets.only(bottom: 10.0),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.check_circle_outline, color: color, size: 18),
                        const SizedBox(width: 12),
                        Expanded(child: Text(cap, style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary, height: 1.4))),
                      ],
                    ),
                  )),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

