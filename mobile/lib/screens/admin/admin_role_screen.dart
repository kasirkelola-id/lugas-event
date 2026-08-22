import 'package:flutter/material.dart';
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
      appBar: AppBar(title: const Text('Role & Hak Akses')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: Colors.grey.shade50,
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSummaryHeader(),
          const SizedBox(height: 24),
          const Text(
            'Informasi Hak Akses',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black87),
          ),
          const SizedBox(height: 16),
          _buildRoleCard(
            title: 'Admin (Administrator)',
            color: Colors.red.shade700,
            icon: Icons.admin_panel_settings,
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
            color: Colors.blue.shade700,
            icon: Icons.manage_accounts,
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
          _buildRoleCard(
            title: 'Anggota',
            color: Colors.green.shade700,
            icon: Icons.people,
            count: _summary?['anggota'] ?? 0,
            description: 'Pengguna reguler yang mengikuti acara.',
            capabilities: [
              'Melihat daftar acara aktif.',
              'Melakukan scan QR untuk presensi (di masa depan).',
              'Melihat riwayat kehadiran pribadinya.',
              'Tidak memiliki akses ke dashboard pengelolaan atau admin.'
            ],
          ),
          const SizedBox(height: 32),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.orange.shade50,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.orange.shade200),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline, color: Colors.orange.shade800),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Sistem keamanan secara otomatis mencegah penghapusan/penonaktifan jika hanya tersisa 1 Admin aktif di dalam sistem (Lockout Prevention).',
                    style: TextStyle(color: Colors.orange.shade900, fontSize: 13),
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
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.indigo.shade800, Colors.indigo.shade500],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.indigo.withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 4)),
        ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Total Pengguna', style: TextStyle(color: Colors.white70, fontSize: 14)),
              const SizedBox(height: 4),
              Text(
                '${_summary?['total'] ?? 0}',
                style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.analytics, color: Colors.white, size: 32),
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
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          collapsedBackgroundColor: Colors.white,
          backgroundColor: Colors.white,
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          title: Text(title, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: color)),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4.0),
            child: Text('$count Akun terdaftar', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
          ),
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              color: Colors.grey.shade50,
              width: double.infinity,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(description, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                  const SizedBox(height: 12),
                  const Text('Hak Akses:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
                  const SizedBox(height: 8),
                  ...capabilities.map((cap) => Padding(
                    padding: const EdgeInsets.only(bottom: 6.0),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.check_circle, color: color, size: 16),
                        const SizedBox(width: 8),
                        Expanded(child: Text(cap, style: const TextStyle(fontSize: 13))),
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
