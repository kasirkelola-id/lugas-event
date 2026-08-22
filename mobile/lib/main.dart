import 'package:flutter/material.dart';
import 'screens/auth/login_screen.dart';
import 'screens/pengelola/pengelola_home_screen.dart';
import 'screens/anggota/anggota_home_screen.dart';
import 'services/auth_service.dart';
import 'models/user_model.dart';
import 'core/theme/app_theme.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Lugasku',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: const InitialScreen(),
    );
  }
}

class InitialScreen extends StatefulWidget {
  const InitialScreen({super.key});

  @override
  State<InitialScreen> createState() => _InitialScreenState();
}

class _InitialScreenState extends State<InitialScreen> {
  @override
  void initState() {
    super.initState();
    _checkSession();
  }

  void _checkSession() async {
    final result = await AuthService.getMe();
    
    if (!mounted) return;

    if (result['success']) {
      final UserModel user = result['user'];
      if (user.statusAktif != 1) {
        // User not active, force logout locally and goto login
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Akun Anda tidak aktif')),
        );
        return;
      }
      
      if (user.roleLevel == 'pengelola') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PengelolaHomeScreen()),
        );
      } else {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const AnggotaHomeScreen()),
        );
      }
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: CircularProgressIndicator(),
      ),
    );
  }
}
