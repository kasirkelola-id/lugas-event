import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/event_model.dart';
import '../../models/participant_model.dart';
import '../../services/participant_service.dart';
import '../../services/auth_service.dart';
import 'package:mobile/screens/auth/login_screen.dart';
import 'attendance_list_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/common/feedback_dialogs.dart';
import '../widgets/common/app_snackbar.dart';

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
  String _searchQuery = '';
  String? _rtFilter;
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<int> get _availableRts {
    final rts = _participants.map((p) => p.userRt).toSet().toList();
    rts.sort();
    return rts;
  }

  List<ParticipantModel> get _filteredParticipants {
    return _participants.where((p) {
      bool matchesSearch =
          _searchQuery.isEmpty ||
          p.namaLengkap.toLowerCase().contains(_searchQuery.toLowerCase());
      bool matchesRt = _rtFilter == null || p.userRt.toString() == _rtFilter;
      return matchesSearch && matchesRt;
    }).toList();
  }

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
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
      }
    }
  }

  Future<void> _addParticipant() async {
    // This is a placeholder for adding participant
    // For real app, we need a bottom sheet to select users
    AppSnackBar.showInfo(context, 'Pilih pengguna untuk ditambahkan');
  }

  Future<void> _removeParticipant(ParticipantModel participant) async {
    final confirm = await FeedbackDialogs.showConfirmation(
      context: context,
      title: 'Hapus Peserta',
      content:
          'Apakah Anda yakin ingin menghapus ${participant.namaLengkap} dari daftar peserta?',
      isDestructive: true,
    );

    if (confirm != true) return;

    setState(() {
      _isLoading = true;
    });

    final result = await ParticipantService.removeParticipant(
      widget.event.id,
      participant.userId,
    );

    if (!mounted) return;

    if (result['success']) {
      AppSnackBar.showSuccess(context, 'Peserta berhasil dihapus.');
      _loadParticipants();
    } else {
      setState(() {
        _isLoading = false;
      });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
        );
      } else {
        AppSnackBar.showError(context, result['message']);
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
        label: const Text(
          'Tambah Peserta',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
        ),
        backgroundColor: AppTheme.primary,
        elevation: 4,
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _participants.isEmpty) {
      return const Center(
        child: CustomLoadingIndicator(color: AppTheme.primary),
      );
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
            ElevatedButton(
              onPressed: _loadParticipants,
              child: const Text('Coba Lagi'),
            ),
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
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).brightness == Brightness.dark
                ? AppTheme.surface
                : Colors.white,
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
            border: Border.all(
              color: Colors.grey.withValues(alpha: 0.2),
              width: 1,
            ),
          ),
          child: TextField(
            controller: _searchController,
            style: const TextStyle(fontSize: 14, color: AppTheme.textPrimary),
            decoration: InputDecoration(
              hintText: 'Cari nama atau email...',
              hintStyle: TextStyle(
                color: AppTheme.textSecondary.withValues(alpha: 0.7),
                fontSize: 14,
              ),
              prefixIcon: const Padding(
                padding: EdgeInsets.only(left: 12.0, right: 8.0),
                child: Icon(Icons.search, color: AppTheme.textSecondary, size: 22),
              ),
              prefixIconConstraints: const BoxConstraints(minWidth: 40, minHeight: 40),
              suffixIcon: _searchQuery.isNotEmpty
                  ? IconButton(
                      icon: const Icon(
                        Icons.clear,
                        color: AppTheme.textSecondary,
                        size: 20,
                      ),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _searchQuery = '');
                      },
                    )
                  : null,
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 20,
                vertical: 14,
              ),
            ),
            onChanged: (val) => setState(() => _searchQuery = val),
          ),
        ),
        const SizedBox(height: 12),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          clipBehavior: Clip.none,
          child: Row(
            children: [null, ..._availableRts].map((rt) {
              final isSelected = _rtFilter == rt?.toString();
              final label = rt == null
                  ? 'Semua RT'
                  : 'RT ${rt.toString().padLeft(2, '0')}';
              
              return Padding(
                padding: const EdgeInsets.only(right: 10.0),
                child: FilterChip(
                  label: Text(label),
                  selected: isSelected,
                  showCheckmark: false,
                  selectedColor: AppTheme.primary,
                  backgroundColor: Theme.of(context).brightness == Brightness.dark
                      ? AppTheme.surface
                      : Colors.white,
                  labelStyle: TextStyle(
                    color: isSelected
                        ? Colors.white
                        : AppTheme.textPrimary,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                    side: BorderSide(
                      color: isSelected
                          ? AppTheme.primary
                          : Colors.grey.withValues(alpha: 0.3),
                      width: 1,
                    ),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  onSelected: (selected) {
                    setState(() {
                      _rtFilter = rt?.toString();
                    });
                  },
                ),
              );
            }).toList(),
          ),
        ),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Daftar Peserta (${_filteredParticipants.length})',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
            TextButton.icon(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => AttendanceListScreen(event: widget.event),
                  ),
                );
              },
              icon: const Icon(
                Icons.checklist,
                color: AppTheme.primary,
                size: 20,
              ),
              label: const Text(
                'Lihat Absensi',
                style: TextStyle(color: AppTheme.primary),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (_filteredParticipants.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(32.0),
              child: Column(
                children: [
                  Icon(
                    Icons.people_outline,
                    size: 80,
                    color: Colors.grey.shade300,
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Tidak ada peserta',
                    style: TextStyle(
                      color: AppTheme.textPrimary,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          )
        else
          ..._filteredParticipants.map((p) => _buildParticipantCard(p)),

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
          Text(
            widget.event.namaAcara,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(
                Icons.calendar_month,
                size: 16,
                color: AppTheme.textSecondary,
              ),
              const SizedBox(width: 8),
              Text(
                widget.event.tanggalAcara,
                style: const TextStyle(color: AppTheme.textSecondary),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const Divider(),
          const SizedBox(height: 16),
          const Text(
            'Gunakan halaman ini untuk mendaftarkan pengguna sebagai peserta acara.',
            style: TextStyle(color: AppTheme.textSecondary),
          ),
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
            participant.namaPanggilan.isNotEmpty
                ? participant.namaPanggilan.substring(0, 1).toUpperCase()
                : 'U',
            style: const TextStyle(
              color: AppTheme.primary,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        title: Text(
          participant.namaLengkap,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            color: AppTheme.textPrimary,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              participant.whatsapp,
              style: const TextStyle(
                color: AppTheme.textSecondary,
                fontSize: 13,
              ),
            ),
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
