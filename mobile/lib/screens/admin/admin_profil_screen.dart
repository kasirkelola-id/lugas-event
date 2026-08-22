import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/profile_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';

class AdminProfilScreen extends StatefulWidget {
  const AdminProfilScreen({super.key});

  @override
  State<AdminProfilScreen> createState() => _AdminProfilScreenState();
}

class _AdminProfilScreenState extends State<AdminProfilScreen> {
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
        backgroundColor: isError ? Colors.red.shade700 : Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
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
    bool isLoadingSubmit = false;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: const Text('Edit Profil'),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: namaLengkapController,
                        decoration: const InputDecoration(labelText: 'Nama Lengkap', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: namaPanggilanController,
                        decoration: const InputDecoration(labelText: 'Nama Panggilan', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: usernameController,
                        decoration: const InputDecoration(labelText: 'Username', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: whatsappController,
                        decoration: const InputDecoration(labelText: 'No. WhatsApp', border: OutlineInputBorder()),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoadingSubmit ? null : () => Navigator.pop(context),
                  child: const Text('Batal'),
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
                  child: isLoadingSubmit ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Simpan'),
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

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: const Text('Ubah Password'),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: oldPasswordController,
                        decoration: const InputDecoration(labelText: 'Password Lama', border: OutlineInputBorder()),
                        obscureText: true,
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: newPasswordController,
                        decoration: const InputDecoration(labelText: 'Password Baru', border: OutlineInputBorder()),
                        obscureText: true,
                        validator: (value) {
                          if (value == null || value.length < 6) return 'Minimal 6 karakter';
                          if (value == 'lugasjosjis') return 'Tidak boleh gunakan password bawaan';
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: confirmPasswordController,
                        decoration: const InputDecoration(labelText: 'Konfirmasi Password', border: OutlineInputBorder()),
                        obscureText: true,
                        validator: (value) => value != newPasswordController.text ? 'Password tidak sama' : null,
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isLoadingSubmit ? null : () => Navigator.pop(context),
                  child: const Text('Batal'),
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
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.orange.shade700),
                  child: isLoadingSubmit 
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) 
                      : const Text('Ganti Password', style: TextStyle(color: Colors.white)),
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
      appBar: AppBar(title: const Text('Profil Saya')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      body: RefreshIndicator(
        onRefresh: _loadUser,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _user == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _user == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
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
            padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [Colors.indigo.shade800, Colors.blue.shade600],
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
              ),
            ),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 50,
                  backgroundColor: Colors.white,
                  child: Text(
                    _user!.namaPanggilan.isNotEmpty ? _user!.namaPanggilan.substring(0, 1).toUpperCase() : '?',
                    style: TextStyle(fontSize: 40, fontWeight: FontWeight.bold, color: Colors.indigo.shade800),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _user!.namaLengkap,
                  style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    _user!.roleLevel.toUpperCase(),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
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
                const Text('Informasi Akun', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                const SizedBox(height: 16),
                _buildInfoTile(Icons.person, 'Username', _user!.username),
                const Divider(),
                _buildInfoTile(Icons.phone, 'WhatsApp', _user!.noWhatsapp.isEmpty ? '-' : _user!.noWhatsapp),
                const Divider(),
                _buildInfoTile(
                  Icons.verified_user, 
                  'Status Akun', 
                  _user!.statusAktif == 1 ? 'Aktif' : 'Nonaktif',
                  valueColor: _user!.statusAktif == 1 ? Colors.green.shade700 : Colors.red.shade700
                ),
                const SizedBox(height: 32),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _showEditProfileDialog,
                    icon: const Icon(Icons.edit),
                    label: const Text('Edit Profil'),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _showUpdatePasswordDialog,
                    icon: const Icon(Icons.lock_reset),
                    label: const Text('Ubah Password'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      foregroundColor: Colors.orange.shade700,
                      side: BorderSide(color: Colors.orange.shade700),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoTile(IconData icon, String title, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Row(
        children: [
          Icon(icon, color: Colors.grey.shade600, size: 24),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                const SizedBox(height: 4),
                Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w500, color: valueColor ?? Colors.black87)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
