import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../services/announcement_service.dart';
import '../../services/attendance_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../../models/announcement_model.dart';
import '../../models/attendance_model.dart';
import '../auth/login_screen.dart';
import 'scan_qr_screen.dart';
import 'attendance_history_screen.dart';
import '../shared/user_pengumuman_screen.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/empty_state.dart';

class AnggotaHomeScreen extends StatefulWidget {
  const AnggotaHomeScreen({super.key});

  @override
  State<AnggotaHomeScreen> createState() => _AnggotaHomeScreenState();
}

class _AnggotaHomeScreenState extends State<AnggotaHomeScreen> {
  UserModel? _user;
  List<EventModel> _activeEvents = [];
  List<AnnouncementModel> _announcements = [];
  List<AttendanceModel> _recentAttendances = [];
  
  bool _isLoading = true;
  bool _isError = false;
  String _errorMessage = 'Koneksi bermasalah. Periksa koneksi internet Anda lalu coba lagi.';
  bool _isParsingError = false;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _isError = false;
    });

    try {
      final userResult = await AuthService.getMe();
      if (!mounted) return;

      if (!userResult['success']) {
        if (userResult['message'].toString().toLowerCase().contains('sesi') || 
            userResult['message'].toString().toLowerCase().contains('berakhir')) {
          _logout();
          return;
        }
        setState(() => _isError = true);
        return;
      }

      final user = userResult['user'] as UserModel;

      // Load other data in parallel
      final results = await Future.wait([
        EventService.getEvents(), // will return active events for anggota
        AnnouncementService.getAnnouncements(),
        AttendanceService.getMyHistory(),
      ]);

      if (!mounted) return;

      final eventsResult = results[0];
      final annResult = results[1];
      final attResult = results[2];

      List<EventModel> events = [];
      if (eventsResult['success']) {
        final List<EventModel>? eventsList = eventsResult['events'] as List<EventModel>?;
        events = (eventsList ?? []).where((e) => e.isActive).toList();
      }

      List<AnnouncementModel> announcements = [];
      if (annResult['success']) {
        final List<AnnouncementModel>? annList = annResult['data'] as List<AnnouncementModel>?;
        announcements = (annList ?? []).take(2).toList();
      }

      List<AttendanceModel> attendances = [];
      if (attResult['success']) {
        final List<AttendanceModel>? attList = attResult['history'] as List<AttendanceModel>?;
        attendances = (attList ?? []).take(3).toList();
      }

      setState(() {
        _user = user;
        _activeEvents = events;
        _announcements = announcements;
        _recentAttendances = attendances;
        _isLoading = false;
        
        // Cek jika ada yg parsing error
        if ((eventsResult['isParsingError'] == true) || 
            (annResult['isParsingError'] == true) || 
            (attResult['isParsingError'] == true)) {
          _isError = true;
          _isParsingError = true;
          _errorMessage = 'Data aplikasi tidak dapat dimuat karena format tidak sesuai. Pastikan versi aplikasi Anda terbaru.';
        } else if (!eventsResult['success'] || !annResult['success'] || !attResult['success']) {
          _isError = true;
          _isParsingError = false;
          // Ambil salah satu pesan error API
          _errorMessage = (!eventsResult['success'] ? eventsResult['message'] : null) ?? 
                          (!annResult['success'] ? annResult['message'] : null) ?? 
                          (!attResult['success'] ? attResult['message'] : null) ?? 
                          'Koneksi bermasalah';
        }
      });
    } catch (e, stackTrace) {
      print('[DEBUG] AnggotaHomeScreen _loadData catch Exception: $e');
      print('[DEBUG] StackTrace: $stackTrace');
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isError = true;
          _isParsingError = false;
          _errorMessage = 'Koneksi bermasalah. Periksa koneksi internet Anda lalu coba lagi.';
        });
      }
    }
  }

  void _logout() async {
    await AuthService.logout();
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
        title: const Text('Beranda Anggota'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(color: AppTheme.primary),
            SizedBox(height: 16),
            Text('Memuat informasi...', style: TextStyle(color: AppTheme.textSecondary)),
          ],
        ),
      );
    }

    if (_isError || _user == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(_isParsingError ? Icons.warning_amber_rounded : Icons.wifi_off_outlined, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_isParsingError ? 'Data Aplikasi Tidak Dapat Dimuat' : 'Koneksi Bermasalah', style: const TextStyle(color: AppTheme.error, fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32.0),
              child: Text(_errorMessage, textAlign: TextAlign.center, style: const TextStyle(color: AppTheme.textSecondary)),
            ),
            const SizedBox(height: 16),
            CustomButton(
              text: 'Coba Lagi',
              onPressed: _loadData,
              isFullWidth: false,
              icon: Icons.refresh,
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _buildHeaderSection(),
          _buildPrimaryCTA(),
          
          if (_activeEvents.isNotEmpty)
            _buildActiveEventsSection(),

          _buildAnnouncementsSection(),
          _buildRecentAttendanceSection(),
          
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildHeaderSection() {
    return Container(
      padding: const EdgeInsets.only(left: 24, right: 24, top: 16, bottom: 24),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(24),
          bottomRight: Radius.circular(24),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black12,
            blurRadius: 10,
            offset: Offset(0, 4),
          )
        ]
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
            child: Text(
              _user!.namaPanggilan.isNotEmpty ? _user!.namaPanggilan.substring(0, 1).toUpperCase() : 'U',
              style: const TextStyle(fontSize: 28, color: AppTheme.primary, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Halo, ${_user!.namaPanggilan} 👋',
                  style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Siap mengikuti kegiatan hari ini?',
                  style: TextStyle(fontSize: 14, color: AppTheme.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPrimaryCTA() {
    return Padding(
      padding: const EdgeInsets.all(24.0),
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const ScanQrScreen()),
          );
        },
        borderRadius: AppTheme.radiusLarge,
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: AppTheme.primaryGradient,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowMedium,
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: AppTheme.radiusMedium,
                ),
                child: const Icon(Icons.qr_code_scanner, size: 40, color: Colors.white),
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    Text(
                      'Scan QR Absensi',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Scan QR Code untuk mencatat kehadiran Anda.',
                      style: TextStyle(fontSize: 13, color: Colors.white70),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildActiveEventsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 24.0, vertical: 8.0),
          child: Text('Acara Sedang Berlangsung', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        ),
        ListView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 8.0),
          itemCount: _activeEvents.length,
          itemBuilder: (context, index) {
            final event = _activeEvents[index];
            return Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppTheme.surface,
                borderRadius: AppTheme.radiusMedium,
                border: Border.all(color: AppTheme.success.withValues(alpha: 0.3)),
                boxShadow: AppTheme.shadowSoft,
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.success.withValues(alpha: 0.1),
                      borderRadius: AppTheme.radiusSmall,
                    ),
                    child: const Icon(Icons.event_available, color: AppTheme.success),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(event.namaAcara, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary)),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.calendar_today, size: 12, color: AppTheme.textSecondary),
                            const SizedBox(width: 4),
                            Text(event.tanggalAcara, style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildAnnouncementsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Pengumuman Terbaru', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
              if (_announcements.isNotEmpty)
                TextButton(
                  onPressed: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const UserPengumumanScreen()));
                  },
                  child: const Text('Lihat Semua', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
                ),
            ],
          ),
        ),
        if (_announcements.isEmpty)
          const EmptyStateWidget(
            icon: Icons.campaign_outlined,
            title: 'Belum Ada Pengumuman',
            subtitle: 'Informasi terbaru akan muncul di sini.',
          )
        else
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: const EdgeInsets.symmetric(horizontal: 24.0),
            itemCount: _announcements.length,
            itemBuilder: (context, index) {
              final ann = _announcements[index];
              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: AppTheme.radiusMedium,
                  border: Border.all(color: Colors.grey.shade200),
                  boxShadow: AppTheme.shadowSoft,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.campaign, color: AppTheme.primary, size: 16),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(ann.judul, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.textPrimary), maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      ann.isi,
                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              );
            },
          ),
      ],
    );
  }

  Widget _buildRecentAttendanceSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Absensi Terakhir', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
              if (_recentAttendances.isNotEmpty)
                TextButton(
                  onPressed: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceHistoryScreen()));
                  },
                  child: const Text('Lihat Riwayat', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
                ),
            ],
          ),
        ),
        if (_recentAttendances.isEmpty)
          const EmptyStateWidget(
            icon: Icons.history_outlined,
            title: 'Belum Ada Riwayat',
            subtitle: 'Absensi yang Anda lakukan akan muncul di sini.',
          )
        else
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: const EdgeInsets.symmetric(horizontal: 24.0),
            itemCount: _recentAttendances.length,
            itemBuilder: (context, index) {
              final att = _recentAttendances[index];
              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: AppTheme.radiusMedium,
                  border: Border.all(color: Colors.grey.shade200),
                  boxShadow: AppTheme.shadowSoft,
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppTheme.success.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.check, color: AppTheme.success, size: 16),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(att.namaAcara, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.textPrimary)),
                          const SizedBox(height: 4),
                          Text('${att.tanggalAcara} · ${att.waktuAbsen}', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
      ],
    );
  }
}
