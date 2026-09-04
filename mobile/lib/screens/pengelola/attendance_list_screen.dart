import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/attendance_model.dart';
import '../../models/event_model.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class AttendanceListScreen extends StatefulWidget {
  final EventModel event;
  const AttendanceListScreen({super.key, required this.event});

  @override
  State<AttendanceListScreen> createState() => _AttendanceListScreenState();
}

class _AttendanceListScreenState extends State<AttendanceListScreen> {
  List<AttendanceModel> _attendees = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadAttendees();
  }

  Future<void> _loadAttendees() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await AttendanceService.getEventAttendance(widget.event.id);
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _attendees = result['attendees'] as List<AttendanceModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = (result['message'].toString().toLowerCase().contains('sesi') || 
                         result['message'].toString().toLowerCase().contains('berakhir'))
            ? result['message']
            : 'Daftar hadir gagal dimuat.';
      });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Daftar Hadir'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadAttendees,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _attendees.isEmpty) {
      return const Center(child: CustomLoadingIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _attendees.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadAttendees, child: const Text('Coba Lagi')),
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
        Text('Riwayat Kehadiran (${_attendees.length})', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        const SizedBox(height: 16),
        if (_attendees.isEmpty)
          Center(
            child: Padding(
              padding: const EdgeInsets.all(32.0),
              child: Column(
                children: [
                  Icon(Icons.history_toggle_off, size: 80, color: Colors.grey.shade300),
                  const SizedBox(height: 16),
                  const Text('Belum Ada Kehadiran', style: TextStyle(color: AppTheme.textPrimary, fontSize: 20, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Belum ada peserta yang hadir.', style: TextStyle(color: AppTheme.textSecondary, fontSize: 14)),
                ],
              ),
            ),
          )
        else
          ..._attendees.asMap().entries.map((entry) {
            final index = entry.key;
            final item = entry.value;
            return _buildAttendeeCard(index, item);
          }),
          
        const SizedBox(height: 40),
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
          const Text('Halaman ini menampilkan log waktu saat peserta berhasil melakukan scan QR Code.', style: TextStyle(color: AppTheme.textSecondary)),
        ],
      ),
    );
  }

  Widget _buildAttendeeCard(int index, AttendanceModel item) {
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
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: AppTheme.success.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              '${index + 1}',
              style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold),
            ),
          ),
        ),
        title: Text(item.namaPanggilan.isNotEmpty ? item.namaPanggilan : 'Peserta', style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(item.namaLengkap, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.access_time, size: 12, color: AppTheme.success),
                const SizedBox(width: 4),
                Text(item.waktuAbsen, style: const TextStyle(color: AppTheme.success, fontSize: 12, fontWeight: FontWeight.w600)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

