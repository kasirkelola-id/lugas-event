import 'package:flutter/material.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/pin_screen.dart';
import 'screens/auth/force_change_password_screen.dart';
import 'screens/pengelola/pengelola_home_screen.dart';
import 'screens/admin/admin_home_screen.dart';
import 'screens/anggota/anggota_home_screen.dart';
import 'services/auth_service.dart';
import 'services/notification_service.dart';
import 'storage/auth_storage.dart';
import 'models/user_model.dart';
import 'core/theme/app_theme.dart';
import 'screens/auth/tenant_selector_screen.dart';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Karang Taruna App',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      navigatorKey: navigatorKey,
      home: const InitialScreen(),
    );
  }
}

class InitialScreen extends StatefulWidget {
  final Map<String, dynamic>? pendingNavigation;
  const InitialScreen({super.key, this.pendingNavigation});

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
    final hasToken = await AuthStorage.hasToken();
    if (!mounted) return;

    if (!hasToken) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const PinScreen()),
      );
      return;
    }

    final result = await AuthService.getMe();
    
    // Network Error (No status code, or null statusCode, meaning it failed to connect)
    if (!result['success'] && result['statusCode'] == null) {
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => Scaffold(
            backgroundColor: AppTheme.background,
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.wifi_off_outlined, size: 64, color: AppTheme.textSecondary),
                    const SizedBox(height: 16),
                    const Text('Koneksi Gagal', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                    const SizedBox(height: 8),
                    const Text('Gagal menyambung ke server. Periksa koneksi internet Anda.', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.textSecondary)),
                    const SizedBox(height: 24),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                      ),
                      onPressed: () {
                        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const InitialScreen()));
                      },
                      child: const Text('Coba Lagi'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
      return;
    }

    if (result['success']) {
      // Initialize Push Notification
      await NotificationService.initialize();

      final UserModel user = result['user'];
      if (user.statusAktif != 1) {
        // User not active, force logout locally and goto login
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const PinScreen()));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Akun Anda tidak aktif')),
        );
        return;
      }

      if (user.passwordMustChange) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const ForceChangePasswordScreen()),
        );
        return;
      }
      
      Widget targetScreen = const AnggotaHomeScreen();
      if (user.roleLevel == 'pengelola') {
        targetScreen = const PengelolaHomeScreen();
      } else if (user.roleLevel == 'admin') {
        targetScreen = const AdminHomeScreen();
      }
      
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => targetScreen),
      );
      
      if (widget.pendingNavigation != null) {
        NotificationService.navigateBasedOnPayload(widget.pendingNavigation!);
      }
    } else {
      if (result['statusCode'] == 403) {
        // Membership revoked or inactive for the current tenant.
        await AuthStorage.clearTenant();
        
        // Return to PinScreen so user has to choose tenant safely
        if (!mounted) return;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PinScreen()),
        );
      } else if (result['statusCode'] == 401) {
        await AuthStorage.removeToken();
        await AuthStorage.clearTenant();
        if (!mounted) return;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PinScreen()),
        );
      } else {
        if (!mounted) return;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PinScreen()),
        );
      }
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
