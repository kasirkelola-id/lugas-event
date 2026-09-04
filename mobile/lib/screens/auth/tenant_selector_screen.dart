import 'dart:convert';
import 'package:flutter/material.dart';
import '../../models/user_model.dart';
import '../../core/theme/app_theme.dart';
import '../../storage/auth_storage.dart';
import '../../core/network/api_client.dart';
import '../pengelola/pengelola_home_screen.dart';
import '../anggota/anggota_home_screen.dart';
import '../admin/admin_home_screen.dart';
import 'login_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class TenantSelectorScreen extends StatefulWidget {
  final UserModel user;
  final List<dynamic>? initialMemberships;

  const TenantSelectorScreen({
    super.key,
    required this.user,
    this.initialMemberships,
  });

  @override
  State<TenantSelectorScreen> createState() => _TenantSelectorScreenState();
}

class _TenantSelectorScreenState extends State<TenantSelectorScreen> {
  bool _isLoading = false;
  List<dynamic> _memberships = [];
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    if (widget.initialMemberships != null) {
      _memberships = widget.initialMemberships!;
    } else {
      _fetchMemberships();
    }
  }

  void _fetchMemberships() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final response = await ApiClient.get('/memberships');
      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['status'] == true) {
        setState(() {
          _memberships = data['data'];
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = data['message'] ?? 'Gagal memuat memberships';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Terjadi kesalahan jaringan';
        _isLoading = false;
      });
    }
  }

  void _selectTenant(Map<String, dynamic> membership) async {
    // Save to storage
    await AuthStorage.saveTenant(membership['karang_taruna_id'], membership['nama']);
    
    // Update local user model temporarily for routing
    final updatedUser = UserModel(
      id: widget.user.id,
      namaLengkap: widget.user.namaLengkap,
      namaPanggilan: widget.user.namaPanggilan,
      username: widget.user.username,
      noWhatsapp: widget.user.noWhatsapp,
      rt: widget.user.rt,
      roleLevel: membership['role'],
      statusAktif: widget.user.statusAktif,
      passwordMustChange: widget.user.passwordMustChange,
    );

    if (!mounted) return;

    if (updatedUser.roleLevel == 'pengelola') {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const PengelolaHomeScreen()),
        (route) => false,
      );
    } else if (updatedUser.roleLevel == 'admin') {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const AdminHomeScreen()),
        (route) => false,
      );
    } else {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const AnggotaHomeScreen()),
        (route) => false,
      );
    }
  }

  void _logout() async {
    await AuthStorage.removeToken();
    await AuthStorage.clearTenant();
    if (!mounted) return;
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Pilih Karang Taruna', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.primary,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.white),
            onPressed: _logout,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator(color: AppTheme.primary))
          : _errorMessage != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _fetchMemberships,
                        child: const Text('Coba Lagi'),
                      ),
                    ],
                  ),
                )
              : _memberships.isEmpty
                  ? const Center(child: Text('Anda tidak memiliki keanggotaan aktif.'))
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _memberships.length,
                      itemBuilder: (context, index) {
                        final membership = _memberships[index];
                        final roleName = membership['role'].toString().toUpperCase();
                        
                        return Card(
                          margin: const EdgeInsets.only(bottom: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          child: ListTile(
                            contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                            leading: CircleAvatar(
                              backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                              child: const Icon(Icons.groups, color: AppTheme.primary),
                            ),
                            title: Text(
                              membership['nama'],
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            subtitle: Text('Role: $roleName'),
                            trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                            onTap: () => _selectTenant(membership),
                          ),
                        );
                      },
                    ),
    );
  }
}
