import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/profile_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class AnggotaProfilScreen extends StatefulWidget {
  const AnggotaProfilScreen({super.key});

  @override
  State<AnggotaProfilScreen> createState() => _AnggotaProfilScreenState();
}

class _AnggotaProfilScreenState extends State<AnggotaProfilScreen> {
  UserModel? _user;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    
    final userResult = await AuthService.getMe();
    if (mounted) {
      if (userResult['success']) {
        setState(() {
          _user = userResult['user'];
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = userResult['message'] ?? 'Gagal memuat profil';
          _isLoading = false;
        });
      }
    }
  }
  
  void _showSnackbar(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppTheme.error : AppTheme.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
      ),
    );
  }

  Future<void> _showEditProfileDialog() async {
    if (_user == null) return;
    
    final formKey = GlobalKey<FormState>();
    final namaLengkapController = TextEditingController(text: _user!.namaLengkap);
    final namaPanggilanController = TextEditingController(text: _user!.namaPanggilan);
    final usernameController = TextEditingController(text: _user!.username);
    final whatsappController = TextEditingController(text: _user!.noWhatsapp);
    int selectedRt = _user!.rt;
    bool isLoadingSubmit = false;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: const Text('Edit Profil', style: TextStyle(fontWeight: FontWeight.bold)),
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: namaLengkapController,
                        decoration: InputDecoration(
                          labelText: 'Nama Lengkap', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: namaPanggilanController,
                        decoration: InputDecoration(
                          labelText: 'Nama Panggilan', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: usernameController,
                        decoration: InputDecoration(
                          labelText: 'Username', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: whatsappController,
                        decoration: InputDecoration(
                          labelText: 'No. WhatsApp', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<int>(
                        value: selectedRt,
                        decoration: InputDecoration(
                          labelText: 'RT (Rukun Tetangga)', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        items: const [
                          DropdownMenuItem(value: 1, child: Text('RT 01')),
                          DropdownMenuItem(value: 2, child: Text('RT 02')),
                          DropdownMenuItem(value: 3, child: Text('RT 03')),
                          DropdownMenuItem(value: 4, child: Text('RT 04')),
                        ],
                        onChanged: (val) {
                          if (val != null) setStateDialog(() => selectedRt = val);
                        },
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoadingSubmit ? null : () => Navigator.pop(context),
                  child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary)),
                ),
                ElevatedButton(
                  onPressed: isLoadingSubmit
                      ? null
                      : () async {
                          if (formKey.currentState!.validate()) {
                            setStateDialog(() => isLoadingSubmit = true);
                            
                            final data = {
                              'nama_lengkap': namaLengkapController.text,
                              'nama_panggilan': namaPanggilanController.text,
                              'username': usernameController.text,
                              'no_whatsapp': whatsappController.text,
                              'rt': selectedRt,
                            };

                            final result = await ProfileService.updateProfile(data);

                            if (result['success']) {
                              if (context.mounted) {
                                Navigator.pop(context);
                                _showSnackbar('Profil berhasil diperbarui');
                                _loadUser();
                              }
                            } else {
                              setStateDialog(() => isLoadingSubmit = false);
                              _showSnackbar(result['message'], isError: true);
                            }
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                  ),
                  child: isLoadingSubmit 
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) 
                      : const Text('Simpan'),
                ),
              ],
            );
          }
        );
      },
    );
  }

  Future<void> _showUpdatePasswordDialog() async {
    final formKey = GlobalKey<FormState>();
    final oldPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();
    bool isLoadingSubmit = false;
    bool obscureOld = true;
    bool obscureNew = true;
    bool obscureConfirm = true;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: const Text('Ubah Password', style: TextStyle(fontWeight: FontWeight.bold)),
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: oldPasswordController,
                        decoration: InputDecoration(
                          labelText: 'Password Lama', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                          suffixIcon: IconButton(
                            icon: Icon(obscureOld ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                            onPressed: () => setStateDialog(() => obscureOld = !obscureOld),
                          ),
                        ),
                        obscureText: obscureOld,
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: newPasswordController,
                        decoration: InputDecoration(
                          labelText: 'Password Baru', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                          suffixIcon: IconButton(
                            icon: Icon(obscureNew ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                            onPressed: () => setStateDialog(() => obscureNew = !obscureNew),
                          ),
                        ),
                        obscureText: obscureNew,
                        validator: (value) {
                          if (value == null || value.length < 6) return 'Minimal 6 karakter';
                          if (value == 'lugasjosjis') return 'Tidak boleh gunakan password bawaan';
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: confirmPasswordController,
                        decoration: InputDecoration(
                          labelText: 'Konfirmasi Password', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                          suffixIcon: IconButton(
                            icon: Icon(obscureConfirm ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                            onPressed: () => setStateDialog(() => obscureConfirm = !obscureConfirm),
                          ),
                        ),
                        obscureText: obscureConfirm,
                        validator: (value) => value != newPasswordController.text ? 'Password tidak sama' : null,
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoadingSubmit ? null : () => Navigator.pop(context),
                  child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary)),
                ),
                ElevatedButton(
                  onPressed: isLoadingSubmit
                      ? null
                      : () async {
                          if (formKey.currentState!.validate()) {
                            setStateDialog(() => isLoadingSubmit = true);
                            
                            final data = {
                              'old_password': oldPasswordController.text,
                              'new_password': newPasswordController.text,
                              'confirm_password': confirmPasswordController.text,
                            };

                            final result = await ProfileService.updatePassword(data);

                            if (result['success']) {
                              if (context.mounted) {
                                Navigator.pop(context);
                                _showSnackbar('Password berhasil diubah');
                              }
                            } else {
                              setStateDialog(() => isLoadingSubmit = false);
                              _showSnackbar(result['message'], isError: true);
                            }
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.warning,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                  ),
                  child: isLoadingSubmit 
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) 
                      : const Text('Ganti Password', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          }
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil Saya'),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadUser,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _user == null) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _user == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadUser, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_user == null) return const SizedBox();

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.only(top: 32, bottom: 48, left: 24, right: 24),
            decoration: BoxDecoration(
              gradient: AppTheme.primaryGradient,
              borderRadius: const BorderRadius.only(
                bottomLeft: Radius.circular(32),
                bottomRight: Radius.circular(32),
              ),
              boxShadow: AppTheme.shadowMedium,
            ),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: CircleAvatar(
                    radius: 50,
                    backgroundColor: Colors.white,
                    child: Text(
                      _user!.namaPanggilan.isNotEmpty ? _user!.namaPanggilan.substring(0, 1).toUpperCase() : '?',
                      style: const TextStyle(fontSize: 40, fontWeight: FontWeight.bold, color: AppTheme.primary),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _user!.namaLengkap,
                  style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
                  ),
                  child: Text(
                    _user!.roleLevel.toUpperCase(),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12, letterSpacing: 1),
                  ),
                ),
              ],
            ),
          ),
          
          Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Informasi Akun', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppTheme.surface,
                    borderRadius: AppTheme.radiusLarge,
                    boxShadow: AppTheme.shadowSoft,
                    border: Border.all(color: Colors.grey.shade200),
                  ),
                  child: Column(
                    children: [
                      _buildInfoTile(Icons.person_outline, 'Username', _user!.username),
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 12),
                        child: Divider(height: 1, color: Colors.black12),
                      ),
                      _buildInfoTile(Icons.phone_outlined, 'WhatsApp', _user!.noWhatsapp.isEmpty ? '-' : _user!.noWhatsapp),
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 12),
                        child: Divider(height: 1, color: Colors.black12),
                      ),
                      _buildInfoTile(Icons.home_outlined, 'RT', 'RT 0${_user!.rt}'),
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 12),
                        child: Divider(height: 1, color: Colors.black12),
                      ),
                      _buildInfoTile(
                        Icons.verified_user_outlined, 
                        'Status Akun', 
                        _user!.statusAktif == 1 ? 'Aktif' : 'Nonaktif',
                        valueColor: _user!.statusAktif == 1 ? AppTheme.success : AppTheme.error
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),
                
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _showEditProfileDialog,
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Edit Profil', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                      elevation: 0,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _showUpdatePasswordDialog,
                    icon: const Icon(Icons.lock_reset),
                    label: const Text('Ubah Password', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      foregroundColor: AppTheme.warning,
                      side: BorderSide(color: AppTheme.warning.withValues(alpha: 0.5)),
                      shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
                    ),
                  ),
                ),
                const SizedBox(height: 40),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoTile(IconData icon, String title, String value, {Color? valueColor}) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: AppTheme.primary.withValues(alpha: 0.1),
            borderRadius: AppTheme.radiusSmall,
          ),
          child: Icon(icon, color: AppTheme.primary, size: 20),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13, fontWeight: FontWeight.w500)),
              const SizedBox(height: 2),
              Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: valueColor ?? AppTheme.textPrimary)),
            ],
          ),
        ),
      ],
    );
  }
}

