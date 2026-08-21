import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../auth/login_screen.dart';
import 'scan_qr_screen.dart';
import 'attendance_history_screen.dart';

class AnggotaHomeScreen extends StatefulWidget {
  const AnggotaHomeScreen({super.key});

  @override
  State<AnggotaHomeScreen> createState() => _AnggotaHomeScreenState();
}

class _AnggotaHomeScreenState extends State<AnggotaHomeScreen> {
  UserModel? _user;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    final result = await AuthService.getMe();
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _user = result['user'];
        _isLoading = false;
      });
    } else {
      if (result['message'].toString().toLowerCase().contains('sesi') || 
          result['message'].toString().toLowerCase().contains('berakhir')) {
        _logout();
      }
    }
  }

  void _logout() async {
    await AuthService.logout();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Beranda Anggota'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
            tooltip: 'Logout',
          )
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Selamat datang,\n${_user?.namaPanggilan ?? 'Anggota'}',
                    style: Theme.of(context).textTheme.headlineMedium,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 48),
                  ElevatedButton.icon(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const ScanQrScreen()),
                      );
                    },
                    icon: const Icon(Icons.camera_alt, size: 32),
                    label: const Text('SCAN QR', style: TextStyle(fontSize: 20)),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 24),
                  OutlinedButton.icon(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const AttendanceHistoryScreen()),
                      );
                    },
                    icon: const Icon(Icons.history, size: 24),
                    label: const Text('Riwayat Absensi', style: TextStyle(fontSize: 18)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
