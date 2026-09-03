import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../storage/auth_storage.dart';
import '../pengelola/pengelola_home_screen.dart';
import '../anggota/anggota_home_screen.dart';
import '../admin/admin_home_screen.dart';
import '../../models/user_model.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_text_field.dart';
import '../widgets/common/custom_button.dart';
import 'force_change_password_screen.dart';
import 'register_screen.dart';
import 'pin_screen.dart';
import 'tenant_selector_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;
  String _ktName = 'Memuat...';
  String? _ktLogoUrl;

  bool _isPasswordVisible = false;

  @override
  void initState() {
    super.initState();
    _loadTenant();
  }

  void _loadTenant() async {
    final tenant = await AuthStorage.getTenant();
    if (tenant != null && mounted) {
      setState(() {
        _ktName = tenant['name'];
        _ktLogoUrl = tenant['logo_url'];
      });
    }
  }

  void _changePin() async {
    await AuthStorage.clearTenant();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const PinScreen()),
    );
  }

  void _login() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final username = _usernameController.text.trim();
    final password = _passwordController.text;

    if (username.isEmpty || password.isEmpty) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Username dan password tidak boleh kosong';
      });
      return;
    }

    final result = await AuthService.login(username, password);

    setState(() {
      _isLoading = false;
    });

    if (result['success']) {
      final UserModel user = result['user'];
      if (user.statusAktif != 1) {
        setState(() {
          _errorMessage = 'Akun Anda tidak aktif';
        });
        return;
      }
      
      if (!mounted) return;
      
      if (user.passwordMustChange) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const ForceChangePasswordScreen()),
        );
        return;
      }

      if (result['requires_tenant_selection'] == true) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => TenantSelectorScreen(
              user: user,
              initialMemberships: result['memberships'],
            ),
          ),
        );
        return;
      }

      // If single membership, just use the tenant we logged into, it's already saved by PinScreen/login
      if (user.roleLevel == 'pengelola') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PengelolaHomeScreen()),
        );
      } else if (user.roleLevel == 'admin') {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const AdminHomeScreen()),
        );
      } else {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const AnggotaHomeScreen()),
        );
      }
    } else {
      setState(() {
        _errorMessage = result['message'];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: AppTheme.radiusLarge,
                side: BorderSide(color: Colors.grey.shade200),
              ),
              child: Padding(
                padding: const EdgeInsets.all(32.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _ktLogoUrl != null 
                      ? Container(
                          width: 100,
                          height: 100,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            image: DecorationImage(
                              image: NetworkImage(_ktLogoUrl!),
                              fit: BoxFit.cover,
                            ),
                          ),
                        )
                      : Container(
                          width: 100,
                          height: 100,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            image: DecorationImage(
                              image: AssetImage('assets/logo/default_kartar.png'),
                              fit: BoxFit.cover,
                            ),
                          ),
                        ),
                    const SizedBox(height: 24),
                    const Text(
                      'Selamat Datang',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _ktName,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.primary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    TextButton.icon(
                      onPressed: _changePin,
                      icon: const Icon(Icons.swap_horiz, size: 16),
                      label: const Text('Ganti Karang Taruna / PIN', style: TextStyle(fontSize: 12)),
                      style: TextButton.styleFrom(
                        foregroundColor: AppTheme.textSecondary,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                    ),
                    const SizedBox(height: 24),
                    if (_errorMessage != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 24),
                        decoration: BoxDecoration(
                          color: AppTheme.error.withValues(alpha: 0.1),
                          borderRadius: AppTheme.radiusSmall,
                          border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline, color: AppTheme.error, size: 20),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: const TextStyle(color: AppTheme.error, fontSize: 14),
                              ),
                            ),
                          ],
                        ),
                      ),
                    CustomTextField(
                      controller: _usernameController,
                      label: 'Username',
                      prefixIcon: Icons.person_outline,
                      readOnly: _isLoading,
                    ),
                    CustomTextField(
                      controller: _passwordController,
                      label: 'Password',
                      prefixIcon: Icons.lock_outline,
                      obscureText: !_isPasswordVisible,
                      readOnly: _isLoading,
                      suffixIcon: IconButton(
                        icon: Icon(_isPasswordVisible ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                        onPressed: () {
                          setState(() {
                            _isPasswordVisible = !_isPasswordVisible;
                          });
                        },
                      ),
                    ),
                    const SizedBox(height: 16),
                    CustomButton(
                      text: 'MASUK',
                      onPressed: _login,
                      isLoading: _isLoading,
                    ),
                    const SizedBox(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('Belum punya akun? ', style: TextStyle(color: AppTheme.textSecondary)),
                        GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const RegisterScreen()),
                            );
                          },
                          child: const Text(
                            'Daftar',
                            style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    )
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
