import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/announcement_service.dart';
import '../../models/user_model.dart';
import '../../models/announcement_model.dart';
import '../widgets/app_drawer.dart';

class AdminPengumumanScreen extends StatefulWidget {
  const AdminPengumumanScreen({super.key});

  @override
  State<AdminPengumumanScreen> createState() => _AdminPengumumanScreenState();
}

class _AdminPengumumanScreenState extends State<AdminPengumumanScreen> {
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

  Future<void> _showFormDialog({AnnouncementModel? announcement}) async {
    final formKey = GlobalKey<FormState>();
    final judulController = TextEditingController(text: announcement?.judul);
    final isiController = TextEditingController(text: announcement?.isi);
    String targetRole = announcement?.targetRole ?? 'semua';
    bool statusAktif = announcement?.statusAktif == 1;
    if (announcement == null) statusAktif = true;
    bool isLoadingSubmit = false;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              title: Text(announcement == null ? 'Buat Pengumuman' : 'Edit Pengumuman', style: const TextStyle(fontWeight: FontWeight.bold)),
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: judulController,
                        decoration: InputDecoration(
                          labelText: 'Judul', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: isiController,
                        decoration: InputDecoration(
                          labelText: 'Isi Pengumuman', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        maxLines: 4,
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue: targetRole,
                        decoration: InputDecoration(
                          labelText: 'Target Pengguna', 
                          border: OutlineInputBorder(borderRadius: AppTheme.radiusMedium),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'semua', child: Text('Semua Pengguna')),
                          DropdownMenuItem(value: 'pengelola', child: Text('Hanya Pengelola')),
                          DropdownMenuItem(value: 'anggota', child: Text('Hanya Anggota')),
                        ],
                        onChanged: (val) {
                          if (val != null) setStateDialog(() => targetRole = val);
                        },
                      ),
                      const SizedBox(height: 16),
                      SwitchListTile(
                        title: const Text('Status Aktif', style: TextStyle(fontWeight: FontWeight.w500)),
                        value: statusAktif,
                        onChanged: (val) => setStateDialog(() => statusAktif = val),
                        contentPadding: EdgeInsets.zero,
                        activeTrackColor: AppTheme.primary.withValues(alpha: 0.5),
                        activeThumbColor: AppTheme.primary,
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
                              'judul': judulController.text,
                              'isi': isiController.text,
                              'target_role': targetRole,
                              'status_aktif': statusAktif ? 1 : 0,
                            };

                            Map<String, dynamic> result;
                            if (announcement == null) {
                              result = await AnnouncementService.createAnnouncement(data);
                            } else {
                              result = await AnnouncementService.updateAnnouncement(announcement.id, data);
                            }

                            if (result['success']) {
                              if (context.mounted) {
                                Navigator.pop(context);
                                _showSnackbar(announcement == null ? 'Pengumuman dibuat' : 'Pengumuman diperbarui');
                                _loadData();
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

  Future<void> _deleteAnnouncement(AnnouncementModel announcement) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hapus Pengumuman?', style: TextStyle(fontWeight: FontWeight.bold)),
        content: Text('Anda yakin ingin menghapus "${announcement.judul}"?'),
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false), 
            child: const Text('Batal', style: TextStyle(color: AppTheme.textSecondary))
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true), 
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.error,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
            ),
            child: const Text('Hapus', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      final result = await AnnouncementService.deleteAnnouncement(announcement.id);
      if (result['success']) {
        _showSnackbar('Pengumuman dihapus');
        _loadData();
      } else {
        _showSnackbar(result['message'], isError: true);
      }
    }
  }

  Future<void> _toggleStatus(AnnouncementModel announcement) async {
    final result = await AnnouncementService.toggleStatus(announcement.id);
    if (result['success']) {
      _showSnackbar('Status diubah');
      _loadData();
    } else {
      _showSnackbar(result['message'], isError: true);
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showFormDialog(),
        backgroundColor: AppTheme.primary,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Buat', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
      ),
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
      return ListView(
        children: const [
          SizedBox(height: 100),
          Icon(Icons.campaign_outlined, size: 80, color: Colors.black12),
          SizedBox(height: 16),
          Text('Belum ada pengumuman', textAlign: TextAlign.center, style: TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
        ],
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: _announcements.length,
      itemBuilder: (context, index) {
        final a = _announcements[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 20),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                decoration: BoxDecoration(
                  color: a.statusAktif == 1 ? AppTheme.primary.withValues(alpha: 0.05) : Colors.grey.shade100,
                  borderRadius: const BorderRadius.only(topLeft: Radius.circular(20), topRight: Radius.circular(20)),
                  border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: a.targetRole == 'semua' 
                            ? Colors.blue.shade50 
                            : (a.targetRole == 'pengelola' ? Colors.orange.shade50 : Colors.green.shade50),
                        borderRadius: AppTheme.radiusSmall,
                        border: Border.all(
                          color: a.targetRole == 'semua' 
                              ? Colors.blue.shade200 
                              : (a.targetRole == 'pengelola' ? Colors.orange.shade200 : Colors.green.shade200),
                        ),
                      ),
                      child: Text(
                        'Target: ${a.targetRole.toUpperCase()}',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: a.targetRole == 'semua' 
                              ? Colors.blue.shade700 
                              : (a.targetRole == 'pengelola' ? Colors.orange.shade700 : Colors.green.shade700),
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: a.statusAktif == 1 ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.error.withValues(alpha: 0.1),
                        borderRadius: AppTheme.radiusSmall,
                      ),
                      child: Text(
                        a.statusAktif == 1 ? 'AKTIF' : 'NONAKTIF',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: a.statusAktif == 1 ? AppTheme.success : AppTheme.error,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.judul, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                    const SizedBox(height: 12),
                    Text(
                      a.isi, 
                      style: const TextStyle(fontSize: 14, color: AppTheme.textSecondary, height: 1.5),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Icon(Icons.person_outline, size: 14, color: Colors.grey.shade500),
                        const SizedBox(width: 4),
                        Text('Dibuat oleh: ${a.pembuat}', style: TextStyle(fontSize: 12, color: Colors.grey.shade600, fontWeight: FontWeight.w500)),
                      ],
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: Colors.black12),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8.0, horizontal: 8.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    Expanded(
                      child: TextButton.icon(
                        onPressed: () => _toggleStatus(a),
                        icon: Icon(a.statusAktif == 1 ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 18),
                        label: Text(a.statusAktif == 1 ? 'Nonaktifkan' : 'Aktifkan'),
                        style: TextButton.styleFrom(foregroundColor: AppTheme.textSecondary),
                      ),
                    ),
                    Container(width: 1, height: 24, color: Colors.grey.shade300),
                    Expanded(
                      child: TextButton.icon(
                        onPressed: () => _showFormDialog(announcement: a),
                        icon: const Icon(Icons.edit_outlined, size: 18),
                        label: const Text('Edit'),
                        style: TextButton.styleFrom(foregroundColor: AppTheme.info),
                      ),
                    ),
                    Container(width: 1, height: 24, color: Colors.grey.shade300),
                    Expanded(
                      child: TextButton.icon(
                        onPressed: () => _deleteAnnouncement(a),
                        icon: const Icon(Icons.delete_outline, size: 18),
                        label: const Text('Hapus'),
                        style: TextButton.styleFrom(foregroundColor: AppTheme.error),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

