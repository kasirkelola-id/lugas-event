import 'package:flutter/material.dart';
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
      appBar: AppBar(title: const Text('Pengumuman')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: Colors.grey.shade50,
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_announcements.isEmpty) {
      return ListView(
        children: const [
          SizedBox(height: 100),
          Icon(Icons.campaign_outlined, size: 64, color: Colors.grey),
          SizedBox(height: 16),
          Text('Belum ada pengumuman untuk Anda.', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey, fontSize: 16)),
        ],
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _announcements.length,
      itemBuilder: (context, index) {
        final a = _announcements[index];
        return Card(
          elevation: 2,
          margin: const EdgeInsets.only(bottom: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(a.judul, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.indigo)),
                    const Icon(Icons.campaign, color: Colors.indigo, size: 20),
                  ],
                ),
                const SizedBox(height: 12),
                Text(a.isi, style: const TextStyle(fontSize: 14, color: Colors.black87)),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Oleh: ${a.pembuat}', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                    Text(
                      a.targetRole == 'semua' ? 'Untuk Semua' : (a.targetRole == 'pengelola' ? 'Untuk Pengelola' : 'Untuk Anggota'),
                      style: TextStyle(
                        fontSize: 11,
                        color: a.targetRole == 'semua' ? Colors.blue.shade700 : Colors.orange.shade700,
                        fontWeight: FontWeight.w600
                      )
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
