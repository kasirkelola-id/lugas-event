import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../widgets/common/custom_text_field.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/feedback_dialogs.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _namaLengkapController = TextEditingController();
  final _namaPanggilanController = TextEditingController();
  final _usernameController = TextEditingController();
  final _whatsappController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  int _selectedRt = 1;
  bool _isLoading = false;
  String? _errorMessage;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;

  Future<void> _register() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final namaLengkap = _namaLengkapController.text.trim();
    final namaPanggilan = _namaPanggilanController.text.trim();
    final username = _usernameController.text.trim();
    final whatsapp = _whatsappController.text.trim();
    final password = _passwordController.text;
    final confirmPassword = _confirmPasswordController.text;

    if (namaLengkap.isEmpty || namaPanggilan.isEmpty || username.isEmpty || whatsapp.isEmpty || password.isEmpty || confirmPassword.isEmpty) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Semua field wajib diisi';
      });
      return;
    }

    if (password != confirmPassword) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Konfirmasi password tidak sama';
      });
      return;
    }

    try {
      final result = await AuthService.register({
        'nama_lengkap': namaLengkap,
        'nama_panggilan': namaPanggilan,
        'username': username,
        'no_whatsapp': whatsapp,
        'rt': _selectedRt,
        'password': password,
        'confirm_password': confirmPassword,
      });

      if (!mounted) return;

      if (result['success']) {
        await FeedbackDialogs.showConfirmation(
          context: context,
          title: 'Pendaftaran Berhasil',
          content: 'Akun Anda berhasil dibuat. Silakan masuk untuk melanjutkan.',
          confirmText: 'Selesai',
        );
        if (context.mounted) {
          Navigator.pop(context); // back to login
        }
      } else {
        setState(() {
          _errorMessage = result['message'];
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Terjadi kesalahan sistem: $e';
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Daftar Akun Anggota', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        backgroundColor: AppTheme.primary,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
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
                    const Icon(Icons.person_add_alt_1, size: 64, color: AppTheme.primary),
                    const SizedBox(height: 16),
                    const Text(
                      'Pendaftaran Anggota',
                      style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
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
                      controller: _namaLengkapController,
                      label: 'Nama Lengkap',
                      prefixIcon: Icons.badge_outlined,
                      readOnly: _isLoading,
                    ),
                    CustomTextField(
                      controller: _namaPanggilanController,
                      label: 'Nama Panggilan',
                      prefixIcon: Icons.face,
                      readOnly: _isLoading,
                    ),
                    CustomTextField(
                      controller: _usernameController,
                      label: 'Username',
                      prefixIcon: Icons.person_outline,
                      readOnly: _isLoading,
                    ),
                    CustomTextField(
                      controller: _whatsappController,
                      label: 'No. WhatsApp',
                      prefixIcon: Icons.phone_android,
                      keyboardType: TextInputType.phone,
                      readOnly: _isLoading,
                    ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16.0),
                      child: DropdownButtonFormField<int>(
                        value: _selectedRt,
                        decoration: const InputDecoration(labelText: 'Pilih RT', prefixIcon: Icon(Icons.home_outlined)),
                        items: const [
                          DropdownMenuItem(value: 1, child: Text('RT 01')),
                          DropdownMenuItem(value: 2, child: Text('RT 02')),
                          DropdownMenuItem(value: 3, child: Text('RT 03')),
                          DropdownMenuItem(value: 4, child: Text('RT 04')),
                        ],
                        onChanged: _isLoading ? null : (val) {
                          if (val != null) setState(() => _selectedRt = val);
                        },
                      ),
                    ),
                    CustomTextField(
                      controller: _passwordController,
                      label: 'Password',
                      prefixIcon: Icons.lock_outline,
                      obscureText: _obscurePassword,
                      readOnly: _isLoading,
                      suffixIcon: IconButton(
                        icon: Icon(_obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                        onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                      ),
                    ),
                    CustomTextField(
                      controller: _confirmPasswordController,
                      label: 'Konfirmasi Password',
                      prefixIcon: Icons.lock_reset,
                      obscureText: _obscureConfirm,
                      readOnly: _isLoading,
                      suffixIcon: IconButton(
                        icon: Icon(_obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                        onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
                      ),
                    ),
                    const SizedBox(height: 16),
                    CustomButton(
                      text: 'Daftar',
                      onPressed: _register,
                      isLoading: _isLoading,
                    ),
                    const SizedBox(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('Sudah punya akun? ', style: TextStyle(color: AppTheme.textSecondary)),
                        GestureDetector(
                          onTap: () => Navigator.pop(context),
                          child: const Text('Masuk', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
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
