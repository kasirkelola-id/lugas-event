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
    final tenant = await AuthStorage.getTenant();
    if (!mounted) return;

    if (tenant == null) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const PinScreen()),
      );
      return;
    }

    final result = await AuthService.getMe();
    if (result['success']) {
      // Initialize Push Notification
      await NotificationService.initialize();

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
        
        // Retry getMe without the invalid tenant header
        final globalResult = await AuthService.getMe();
        if (!mounted) return;
        
        if (globalResult['success']) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => TenantSelectorScreen(user: globalResult['user'])),
          );
        } else {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const LoginScreen()),
          );
        }
      } else {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
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
