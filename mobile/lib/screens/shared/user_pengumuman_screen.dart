import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/announcement_service.dart';
import '../../models/user_model.dart';
import '../../models/announcement_model.dart';
import '../widgets/app_drawer.dart';

class UserPengumumanScreen extends StatefulWidget {
  const UserPengumumanScreen({super.key});

  @override
  State<UserPengumumanScreen> createState() => _UserPengumumanScreenState();
}

class _UserPengumumanScreenState extends State<UserPengumumanScreen> {
  UserModel? _user;
  List<AnnouncementModel> _announcements = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    if (mounted && userResult['success']) {
      _user = userResult['user'];
    }

    final announcementResult = await AnnouncementService.getAnnouncements();
    if (mounted) {
      if (announcementResult['success']) {
        setState(() {
          _announcements = announcementResult['data'];
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = announcementResult['message'] ?? 'Gagal memuat pengumuman';
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengumuman'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_announcements.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.campaign_outlined, size: 80, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            const Text('Belum Ada Pengumuman', style: TextStyle(color: AppTheme.textPrimary, fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text('Belum ada pengumuman untuk Anda.', style: TextStyle(color: AppTheme.textSecondary, fontSize: 14)),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: _announcements.length,
      itemBuilder: (context, index) {
        final a = _announcements[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: Padding(
            padding: const EdgeInsets.all(20.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        a.judul, 
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.primary)
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.campaign_outlined, color: AppTheme.primary, size: 24),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Text(a.isi, style: const TextStyle(fontSize: 14, color: AppTheme.textSecondary, height: 1.5)),
                const SizedBox(height: 20),
                const Divider(height: 1, color: Colors.black12),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.person_outline, size: 14, color: Colors.grey.shade500),
                        const SizedBox(width: 4),
                        Text('Oleh: ${a.pembuat}', style: TextStyle(fontSize: 12, color: Colors.grey.shade600, fontWeight: FontWeight.w500)),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: a.targetRole == 'semua' ? Colors.blue.shade50 : Colors.orange.shade50,
                        borderRadius: AppTheme.radiusSmall,
                        border: Border.all(
                          color: a.targetRole == 'semua' ? Colors.blue.shade200 : Colors.orange.shade200,
                        ),
                      ),
                      child: Text(
                        a.targetRole == 'semua' ? 'UNTUK SEMUA' : (a.targetRole == 'pengelola' ? 'UNTUK PENGELOLA' : 'UNTUK ANGGOTA'),
                        style: TextStyle(
                          fontSize: 10,
                          color: a.targetRole == 'semua' ? Colors.blue.shade700 : Colors.orange.shade700,
                          fontWeight: FontWeight.bold
                        )
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

