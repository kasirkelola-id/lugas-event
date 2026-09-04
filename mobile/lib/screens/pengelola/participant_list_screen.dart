import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/event_model.dart';
import '../../models/participant_model.dart';
import '../../services/participant_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import 'attendance_list_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class ParticipantListScreen extends StatefulWidget {
  final EventModel event;
  const ParticipantListScreen({super.key, required this.event});

  @override
  State<ParticipantListScreen> createState() => _ParticipantListScreenState();
}

class _ParticipantListScreenState extends State<ParticipantListScreen> {
  List<ParticipantModel> _participants = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadParticipants();
  }

  Future<void> _loadParticipants() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await ParticipantService.getParticipants(widget.event.id);
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _participants = result['participants'] as List<ParticipantModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = result['message'];
      });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
    }
  }

  Future<void> _addParticipant() async {
    // This is a placeholder for adding participant
    // For real app, we need a bottom sheet to select users
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pilih pengguna untuk ditambahkan')));
  }

  Future<void> _removeParticipant(ParticipantModel participant) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Peserta', style: TextStyle(fontWeight: FontWeight.bold)),
        content: Text('Apakah Anda yakin ingin menghapus ${participant.namaLengkap} dari daftar peserta?'),
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false), 
            child: const Text('Batalkan', style: TextStyle(color: AppTheme.textSecondary))
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: AppTheme.error),
            child: const Text('Konfirmasi', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() {
      _isLoading = true;
    });

    final result = await ParticipantService.removeParticipant(widget.event.id, participant.userId);
    
    if (!mounted) return;

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Peserta berhasil dihapus.'), backgroundColor: AppTheme.success));
      _loadParticipants();
    } else {
      setState(() { _isLoading = false; });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message']), backgroundColor: AppTheme.error));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Kelola Peserta'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadParticipants,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addParticipant,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Tambah Peserta', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        backgroundColor: AppTheme.primary,
        elevation: 4,
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _participants.isEmpty) {
      return const Center(child: CustomLoadingIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _participants.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadParticipants, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _buildHeader(),
        const SizedBox(height: 24),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Daftar Peserta (${_participants.length})', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
            TextButton.icon(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => AttendanceListScreen(event: widget.event)),
                );
              },
              icon: const Icon(Icons.checklist, color: AppTheme.primary, size: 20),
              label: const Text('Lihat Absensi', style: TextStyle(color: AppTheme.primary)),
            )
          ],
        ),
        const SizedBox(height: 16),
        if (_participants.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(32.0),
              child: Column(
                children: [
                  Icon(Icons.people_outline, size: 80, color: Colors.grey.shade300),
                  const SizedBox(height: 16),
                  const Text('Belum Ada Peserta', style: TextStyle(color: AppTheme.textPrimary, fontSize: 20, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Belum ada peserta yang didaftarkan.', style: TextStyle(color: AppTheme.textSecondary, fontSize: 14)),
                ],
              ),
            ),
          )
        else
          ..._participants.map((p) => _buildParticipantCard(p)),
          
        const SizedBox(height: 80),
      ],
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: AppTheme.shadowSoft,
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.event.namaAcara, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.calendar_month, size: 16, color: AppTheme.textSecondary),
              const SizedBox(width: 8),
              Text(widget.event.tanggalAcara, style: const TextStyle(color: AppTheme.textSecondary)),
            ],
          ),
          const SizedBox(height: 16),
          const Divider(),
          const SizedBox(height: 16),
          const Text('Gunakan halaman ini untuk mendaftarkan pengguna sebagai peserta acara.', style: TextStyle(color: AppTheme.textSecondary)),
        ],
      ),
    );
  }

  Widget _buildParticipantCard(ParticipantModel participant) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: AppTheme.radiusMedium,
        boxShadow: AppTheme.shadowSoft,
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
          child: Text(
            participant.namaPanggilan.isNotEmpty ? participant.namaPanggilan.substring(0, 1).toUpperCase() : 'U',
            style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
          ),
        ),
        title: Text(participant.namaLengkap, style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(participant.whatsapp, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
          ],
        ),
        trailing: IconButton(
          icon: const Icon(Icons.delete_outline, color: AppTheme.error),
          onPressed: () => _removeParticipant(participant),
        ),
      ),
    );
  }
}
