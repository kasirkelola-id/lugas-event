import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_text_field.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/feedback_dialogs.dart';
import 'login_screen.dart';
import '../pengelola/pengelola_home_screen.dart';
import '../anggota/anggota_home_screen.dart';
import '../admin/admin_home_screen.dart';

class ForceChangePasswordScreen extends StatefulWidget {
  const ForceChangePasswordScreen({super.key});

  @override
  State<ForceChangePasswordScreen> createState() => _ForceChangePasswordScreenState();
}

class _ForceChangePasswordScreenState extends State<ForceChangePasswordScreen> {
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;
  bool _obscureOld = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  void _submit() async {
    setState(() {
      _errorMessage = null;
    });

    final oldPass = _oldPasswordController.text;
    final newPass = _newPasswordController.text;
    final confirmPass = _confirmPasswordController.text;

    if (oldPass.isEmpty || newPass.isEmpty || confirmPass.isEmpty) {
      setState(() => _errorMessage = 'Semua kolom wajib diisi');
      return;
    }

    if (newPass != confirmPass) {
      setState(() => _errorMessage = 'Konfirmasi password tidak cocok');
      return;
    }

    if (newPass == 'lugasjosjis') {
      setState(() => _errorMessage = 'Tidak boleh menggunakan password default sementara');
      return;
    }

    setState(() => _isLoading = true);

    final result = await AuthService.updatePassword(oldPass, newPass, confirmPass);

    if (!mounted) return;

    if (result['success']) {
      // Refresh user session data to ensure password_must_change == false
      final userResult = await AuthService.getMe();
      if (!mounted) return;

      if (userResult['success']) {
        final UserModel user = userResult['user'];
        if (user.passwordMustChange) {
          // Fallback if somehow still true
          setState(() {
            _isLoading = false;
            _errorMessage = 'Terjadi kesalahan saat memverifikasi password baru. Silakan coba lagi.';
          });
          return;
        }

        await FeedbackDialogs.showConfirmation(
          context: context,
          title: 'Pembaruan Berhasil',
          content: 'Password berhasil diperbarui. Selamat datang di LUGAS.',
          confirmText: 'Lanjutkan',
        );

        if (!mounted) return;

        // Navigate to the dashboard according to the role
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
          _isLoading = false;
          _errorMessage = userResult['message'] ?? 'Gagal memverifikasi sesi.';
        });
      }
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = result['message'];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          automaticallyImplyLeading: false,
          title: const Text('Perbarui Password', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          backgroundColor: AppTheme.primary,
          elevation: 0,
        ),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24.0),
              child: Card(
                elevation: 4,
                shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
                child: Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.security, size: 64, color: AppTheme.warning),
                      const SizedBox(height: 16),
                      const Text(
                        'Keamanan Akun',
                        style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Untuk keamanan akun, silakan buat password baru sebelum melanjutkan.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 14, color: AppTheme.textSecondary),
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
                                child: Text(_errorMessage!, style: const TextStyle(color: AppTheme.error, fontSize: 14)),
                              ),
                            ],
                          ),
                        ),
                      CustomTextField(
                        controller: _oldPasswordController,
                        label: 'Password Saat Ini',
                        prefixIcon: Icons.lock_outline,
                        obscureText: _obscureOld,
                        readOnly: _isLoading,
                        suffixIcon: IconButton(
                          icon: Icon(_obscureOld ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                          onPressed: () => setState(() => _obscureOld = !_obscureOld),
                        ),
                      ),
                      CustomTextField(
                        controller: _newPasswordController,
                        label: 'Password Baru',
                        prefixIcon: Icons.lock,
                        obscureText: _obscureNew,
                        readOnly: _isLoading,
                        suffixIcon: IconButton(
                          icon: Icon(_obscureNew ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                          onPressed: () => setState(() => _obscureNew = !_obscureNew),
                        ),
                      ),
                      CustomTextField(
                        controller: _confirmPasswordController,
                        label: 'Konfirmasi Password Baru',
                        prefixIcon: Icons.lock,
                        obscureText: _obscureConfirm,
                        readOnly: _isLoading,
                        suffixIcon: IconButton(
                          icon: Icon(_obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                          onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
                        ),
                      ),
                      const SizedBox(height: 16),
                      CustomButton(
                        text: 'Simpan & Lanjutkan',
                        onPressed: _submit,
                        isLoading: _isLoading,
                      ),
                      const SizedBox(height: 16),
                      TextButton(
                        onPressed: _isLoading ? null : () async {
                          await AuthService.logout();
                          if (context.mounted) {
                            Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
                          }
                        },
                        child: const Text('Batal & Keluar', style: TextStyle(color: AppTheme.error)),
                      )
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
