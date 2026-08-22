import 'package:flutter/material.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';

import '../pengelola/pengelola_home_screen.dart';
import '../pengelola/pengelola_acara_screen.dart';
import '../pengelola/pengelola_riwayat_screen.dart';
import '../pengelola/pengelola_peserta_screen.dart';
import '../pengelola/pengelola_laporan_screen.dart';
import '../pengelola/pengelola_profil_screen.dart';
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
      child: Column(
        children: [
          UserAccountsDrawerHeader(
            decoration: const BoxDecoration(
              color: Colors.indigo,
            ),
            accountName: Text(user.namaLengkap, style: const TextStyle(fontWeight: FontWeight.bold)),
            accountEmail: Text('Role: ${user.roleLevel.toUpperCase()}'),
            currentAccountPicture: CircleAvatar(
              backgroundColor: Colors.white,
              child: Text(
                user.namaPanggilan.isNotEmpty ? user.namaPanggilan.substring(0, 1).toUpperCase() : 'U',
                style: const TextStyle(fontSize: 24, color: Colors.indigo, fontWeight: FontWeight.bold),
              ),
            ),
          ),
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: isAdmin ? _buildAdminMenu(context) : _buildPengelolaMenu(context),
            ),
          ),
          const Divider(height: 1),
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: const Text('Logout', style: TextStyle(color: Colors.red)),
            onTap: () => _logout(context),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  List<Widget> _buildPengelolaMenu(BuildContext context) {
    return [
      _buildItem(context, Icons.home, 'Beranda', const PengelolaHomeScreen()),
      _buildItem(context, Icons.event, 'Acara', const PengelolaAcaraScreen()),
      _buildItem(context, Icons.history, 'Riwayat Acara', const PengelolaRiwayatScreen()),
      _buildItem(context, Icons.people, 'Peserta', const PengelolaPesertaScreen()),
      // Absensi can be merged into acara or separate. The instructions said Absensi Menu.
      // But we will point Absensi to PengelolaPesertaScreen for now since it handles attendees.
      _buildItem(context, Icons.checklist, 'Absensi', const PengelolaPesertaScreen()),
      _buildItem(context, Icons.bar_chart, 'Laporan', const PengelolaLaporanScreen()),
      _buildItem(context, Icons.campaign, 'Pengumuman', const UserPengumumanScreen()),
      _buildItem(context, Icons.person, 'Profil', const PengelolaProfilScreen()),
    ];
  }

  List<Widget> _buildAdminMenu(BuildContext context) {
    return [
      _buildItem(context, Icons.dashboard, 'Dashboard', const AdminHomeScreen()),
      _buildItem(context, Icons.manage_accounts, 'Pengguna', const AdminPenggunaScreen()),
      _buildItem(context, Icons.security, 'Role & Hak Akses', const AdminRoleScreen()),
      _buildItem(context, Icons.event_note, 'Acara', const AdminAcaraScreen()),
      _buildItem(context, Icons.people_outline, 'Peserta', const AdminPesertaScreen()),
      _buildItem(context, Icons.insert_chart_outlined, 'Laporan', const AdminLaporanScreen()),
      _buildItem(context, Icons.campaign, 'Pengumuman', const AdminPengumumanScreen()),
      _buildItem(context, Icons.print, 'Printer', const AdminPrinterScreen()),
      _buildItem(context, Icons.settings, 'Pengaturan Sistem', const AdminPengaturanScreen()),
      _buildItem(context, Icons.person, 'Profil', const AdminProfilScreen()),
    ];
  }

  Widget _buildItem(BuildContext context, IconData icon, String title, Widget? targetScreen) {
    return ListTile(
      leading: Icon(icon, color: Colors.indigo.shade700),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w500)),
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
    );
  }
}
