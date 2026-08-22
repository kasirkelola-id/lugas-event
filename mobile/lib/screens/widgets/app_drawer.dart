import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';

import '../pengelola/pengelola_home_screen.dart';
import '../pengelola/pengelola_acara_screen.dart';
import '../pengelola/pengelola_riwayat_screen.dart';
import '../pengelola/pengelola_peserta_screen.dart';
import '../pengelola/pengelola_laporan_screen.dart';
import '../pengelola/pengelola_profil_screen.dart';
import '../pengelola/pengelola_pengguna_screen.dart';
import '../shared/user_pengumuman_screen.dart';

import '../admin/admin_home_screen.dart';
import '../admin/admin_pengguna_screen.dart';
import '../admin/admin_role_screen.dart';
import '../admin/admin_acara_screen.dart';
import '../admin/admin_peserta_screen.dart';
import '../admin/admin_laporan_screen.dart';
import '../admin/admin_pengumuman_screen.dart';
import '../admin/admin_printer_screen.dart';
import '../admin/admin_pengaturan_screen.dart';
import '../admin/admin_profil_screen.dart';

class AppDrawer extends StatelessWidget {
  final UserModel user;

  const AppDrawer({super.key, required this.user});

  void _logout(BuildContext context) async {
    await AuthService.logout();
    if (!context.mounted) return;
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _navigate(BuildContext context, Widget screen) {
    Navigator.pop(context); // close drawer
    Navigator.pushReplacement(
      context,
      PageRouteBuilder(
        pageBuilder: (context, animation1, animation2) => screen,
        transitionDuration: Duration.zero,
        reverseTransitionDuration: Duration.zero,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isAdmin = user.roleLevel.toLowerCase() == 'admin';

    return Drawer(
      backgroundColor: AppTheme.background,
      child: Column(
        children: [
          _buildHeader(),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 16),
              children: isAdmin ? _buildAdminMenu(context) : _buildPengelolaMenu(context),
            ),
          ),
          const Divider(height: 1, color: Colors.black12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
            color: AppTheme.surface,
            child: ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.error.withValues(alpha: 0.1),
                  borderRadius: AppTheme.radiusSmall,
                ),
                child: const Icon(Icons.logout, color: AppTheme.error, size: 20),
              ),
              title: const Text('Logout', style: TextStyle(color: AppTheme.error, fontWeight: FontWeight.w600)),
              onTap: () => _logout(context),
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusSmall),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.only(top: 50, bottom: 24, left: 24, right: 24),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(bottom: BorderSide(color: Colors.black12)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
            child: Text(
              user.namaPanggilan.isNotEmpty ? user.namaPanggilan.substring(0, 1).toUpperCase() : 'U',
              style: const TextStyle(fontSize: 28, color: AppTheme.primary, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user.namaLengkap,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.textPrimary),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppTheme.primary,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    user.roleLevel.toUpperCase(),
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(left: 24, top: 16, bottom: 8),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.bold,
          color: AppTheme.textSecondary,
          letterSpacing: 1.2,
        ),
      ),
    );
  }

  List<Widget> _buildPengelolaMenu(BuildContext context) {
    return [
      _buildSectionLabel('Utama'),
      _buildItem(context, Icons.dashboard_outlined, 'Beranda', const PengelolaHomeScreen()),
      
      _buildSectionLabel('Manajemen'),
      _buildItem(context, Icons.manage_accounts_outlined, 'Anggota', const PengelolaPenggunaScreen()),

      _buildSectionLabel('Kegiatan'),
      _buildItem(context, Icons.event_note_outlined, 'Acara', const PengelolaAcaraScreen()),
      _buildItem(context, Icons.history_outlined, 'Riwayat Acara', const PengelolaRiwayatScreen()),
      _buildItem(context, Icons.people_alt_outlined, 'Peserta & Absensi', const PengelolaPesertaScreen()),
      
      _buildSectionLabel('Analisis'),
      _buildItem(context, Icons.insert_chart_outlined, 'Laporan', const PengelolaLaporanScreen()),
      
      _buildSectionLabel('Lainnya'),
      _buildItem(context, Icons.campaign_outlined, 'Pengumuman', const UserPengumumanScreen()),
      _buildItem(context, Icons.person_outline, 'Profil', const PengelolaProfilScreen()),
    ];
  }

  List<Widget> _buildAdminMenu(BuildContext context) {
    return [
      _buildSectionLabel('Utama'),
      _buildItem(context, Icons.dashboard_outlined, 'Dashboard', const AdminHomeScreen()),
      
      _buildSectionLabel('Manajemen'),
      _buildItem(context, Icons.manage_accounts_outlined, 'Pengguna', const AdminPenggunaScreen()),
      _buildItem(context, Icons.admin_panel_settings_outlined, 'Role & Hak Akses', const AdminRoleScreen()),
      _buildItem(context, Icons.event_note_outlined, 'Acara', const AdminAcaraScreen()),
      _buildItem(context, Icons.people_alt_outlined, 'Peserta & Absensi', const AdminPesertaScreen()),
      
      _buildSectionLabel('Analisis'),
      _buildItem(context, Icons.insert_chart_outlined, 'Laporan', const AdminLaporanScreen()),
      
      _buildSectionLabel('Sistem'),
      _buildItem(context, Icons.campaign_outlined, 'Pengumuman', const AdminPengumumanScreen()),
      _buildItem(context, Icons.print_outlined, 'Printer', const AdminPrinterScreen()),
      _buildItem(context, Icons.settings_outlined, 'Pengaturan', const AdminPengaturanScreen()),
      
      _buildSectionLabel('Akun'),
      _buildItem(context, Icons.person_outline, 'Profil', const AdminProfilScreen()),
    ];
  }

  Widget _buildItem(BuildContext context, IconData icon, String title, Widget? targetScreen) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
      child: ListTile(
        leading: Icon(icon, color: AppTheme.primary.withValues(alpha: 0.8)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w500, color: AppTheme.textPrimary)),
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusSmall),
        hoverColor: AppTheme.primary.withValues(alpha: 0.05),
        onTap: () {
          if (targetScreen != null) {
            _navigate(context, targetScreen);
          } else {
            Navigator.pop(context); // close drawer
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Fitur belum tersedia / Coming Soon')),
            );
          }
        },
      ),
    );
  }
}

