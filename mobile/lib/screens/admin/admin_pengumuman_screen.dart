import 'package:flutter/material.dart';
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
        backgroundColor: isError ? Colors.red.shade700 : Colors.green.shade700,
        behavior: SnackBarBehavior.floating,
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
              title: Text(announcement == null ? 'Buat Pengumuman' : 'Edit Pengumuman'),
              content: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: judulController,
                        decoration: const InputDecoration(labelText: 'Judul', border: OutlineInputBorder()),
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: isiController,
                        decoration: const InputDecoration(labelText: 'Isi Pengumuman', border: OutlineInputBorder()),
                        maxLines: 4,
                        validator: (value) => value == null || value.isEmpty ? 'Wajib diisi' : null,
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: targetRole,
                        decoration: const InputDecoration(labelText: 'Target Pengguna', border: OutlineInputBorder()),
                        items: const [
                          DropdownMenuItem(value: 'semua', child: Text('Semua Pengguna')),
                          DropdownMenuItem(value: 'pengelola', child: Text('Hanya Pengelola')),
                          DropdownMenuItem(value: 'anggota', child: Text('Hanya Anggota')),
                        ],
                        onChanged: (val) {
                          if (val != null) setStateDialog(() => targetRole = val);
                        },
                      ),
                      const SizedBox(height: 12),
                      SwitchListTile(
                        title: const Text('Status Aktif'),
                        value: statusAktif,
                        onChanged: (val) => setStateDialog(() => statusAktif = val),
                        contentPadding: EdgeInsets.zero,
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
                  child: isLoadingSubmit ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Simpan'),
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
        title: const Text('Hapus Pengumuman?'),
        content: Text('Anda yakin ingin menghapus "${announcement.judul}"?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true), 
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Hapus', style: TextStyle(color: Colors.white)),
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
      appBar: AppBar(title: const Text('Pengumuman')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showFormDialog(),
        backgroundColor: Colors.indigo,
        child: const Icon(Icons.add, color: Colors.white),
      ),
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
          Text('Belum ada pengumuman', textAlign: TextAlign.center, style: TextStyle(color: Colors.grey, fontSize: 16)),
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
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: a.statusAktif == 1 ? Colors.indigo.shade50 : Colors.grey.shade200,
                  borderRadius: const BorderRadius.only(topLeft: Radius.circular(12), topRight: Radius.circular(12)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: a.targetRole == 'semua' ? Colors.blue.shade100 : (a.targetRole == 'pengelola' ? Colors.orange.shade100 : Colors.green.shade100),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Target: ${a.targetRole.toUpperCase()}',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: a.targetRole == 'semua' ? Colors.blue.shade800 : (a.targetRole == 'pengelola' ? Colors.orange.shade800 : Colors.green.shade800),
                        ),
                      ),
                    ),
                    Text(
                      a.statusAktif == 1 ? 'AKTIF' : 'NONAKTIF',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: a.statusAktif == 1 ? Colors.green.shade700 : Colors.red.shade700,
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(a.judul, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Text(a.isi, style: const TextStyle(fontSize: 14, color: Colors.black87)),
                    const SizedBox(height: 12),
                    Text('Dibuat oleh: ${a.pembuat}', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                  ],
                ),
              ),
              const Divider(height: 1),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  TextButton.icon(
                    onPressed: () => _toggleStatus(a),
                    icon: Icon(a.statusAktif == 1 ? Icons.visibility_off : Icons.visibility, size: 18),
                    label: Text(a.statusAktif == 1 ? 'Nonaktifkan' : 'Aktifkan'),
                    style: TextButton.styleFrom(foregroundColor: Colors.grey.shade700),
                  ),
                  TextButton.icon(
                    onPressed: () => _showFormDialog(announcement: a),
                    icon: const Icon(Icons.edit, size: 18),
                    label: const Text('Edit'),
                    style: TextButton.styleFrom(foregroundColor: Colors.blue.shade700),
                  ),
                  TextButton.icon(
                    onPressed: () => _deleteAnnouncement(a),
                    icon: const Icon(Icons.delete, size: 18),
                    label: const Text('Hapus'),
                    style: TextButton.styleFrom(foregroundColor: Colors.red.shade700),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
