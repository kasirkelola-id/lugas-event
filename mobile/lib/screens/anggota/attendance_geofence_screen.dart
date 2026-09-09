import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../models/event_model.dart';
import '../../services/event_service.dart';
import '../../services/attendance_service.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_dialog.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/animations/fade_in_slide.dart';

class AttendanceGeofenceScreen extends StatefulWidget {
  const AttendanceGeofenceScreen({Key? key}) : super(key: key);

  @override
  State<AttendanceGeofenceScreen> createState() =>
      _AttendanceGeofenceScreenState();
}

class _AttendanceGeofenceScreenState extends State<AttendanceGeofenceScreen> {
  bool _isLoading = true;
  String _errorMessage = '';
  Position? _currentPosition;
  List<EventModel> _nearbyEvents = [];
  List<int> _activeCheckinEventIds = [];
  UserModel? _currentUser;

  @override
  void initState() {
    super.initState();
    _initLocationAndData();
  }

  Future<void> _initLocationAndData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final userResult = await AuthService.getMe();
      if (userResult['success']) {
        _currentUser = userResult['user'];
      }

      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        setState(() {
          _errorMessage =
              'Layanan Lokasi (GPS) tidak aktif.\nMohon aktifkan GPS Anda.';
          _isLoading = false;
        });
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          if (mounted) {
            final confirm = await AppDialog.showConfirmation(
              context: context,
              title: 'Lokasi Diperlukan',
              content:
                  'KARTAR membutuhkan akses lokasi untuk memastikan absensi dilakukan di area kegiatan.',
              confirmText: 'Buka Pengaturan',
              cancelText: 'Nanti',
              type: DialogType.warning,
            );
            if (confirm == true) {
              await Geolocator.openAppSettings();
            }
          }
          setState(() {
            _errorMessage = 'Izin lokasi ditolak.';
            _isLoading = false;
          });
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        setState(() {
          _errorMessage =
              'Izin lokasi ditolak secara permanen. Mohon ubah di pengaturan aplikasi.';
          _isLoading = false;
        });
        return;
      }

      _currentPosition = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      if (_currentPosition!.isMocked) {
        setState(() {
          _errorMessage = 'Terdeteksi penggunaan Fake GPS. Akses ditolak.';
          _isLoading = false;
        });
        return;
      }

      await _fetchData();
    } catch (e) {
      setState(() {
        _errorMessage =
            'Gagal mendapatkan lokasi Anda. Pastikan sinyal GPS baik.';
        _isLoading = false;
      });
    }
  }

  Future<void> _fetchData() async {
    final eventResult = await EventService.getEvents();
    final statusResult = await AttendanceService.getStatus();

    if (eventResult['success'] && statusResult['success']) {
      final List<EventModel> allEvents = eventResult['events'];
      _activeCheckinEventIds = (statusResult['active_event_ids'] as List)
          .map((e) => e is int ? e : int.parse(e.toString()))
          .toList();

      _nearbyEvents = [];
      for (var event in allEvents) {
        if (!event.isActive) continue;

        if (event.requireGps &&
            event.latitude != null &&
            event.longitude != null &&
            event.radius != null) {
          double distance = Geolocator.distanceBetween(
            _currentPosition!.latitude,
            _currentPosition!.longitude,
            event.latitude!,
            event.longitude!,
          );

          if (distance <= event.radius!) {
            _nearbyEvents.add(event);
          }
        } else {
          _nearbyEvents.add(event);
        }
      }

      setState(() {
        _isLoading = false;
      });
    } else {
      setState(() {
        _errorMessage = eventResult['message'] ?? 'Gagal memuat data.';
        _isLoading = false;
      });
    }
  }

  Future<void> _handleCheckIn(EventModel event) async {
    AppDialog.showLoading(context, message: 'Mencatat kehadiran...');
    final result = await AttendanceService.checkIn(
      event.id,
      userLat: _currentPosition!.latitude,
      userLng: _currentPosition!.longitude,
      accuracy: _currentPosition!.accuracy,
    );
    if (!mounted) return;
    Navigator.pop(context); // close loading

    if (result['success']) {
      await AppDialog.showResult(
        context: context,
        title: 'Kehadiran Tercatat',
        content: 'Absensi Anda untuk acara ini berhasil tersimpan.',
        type: DialogType.success,
      );
      await _initLocationAndData(); // Refresh everything
    } else {
      await AppDialog.showResult(
        context: context,
        title: 'Gagal Check-in',
        content: result['message'] ?? 'Terjadi kesalahan sistem.',
        type: DialogType.error,
      );
    }
  }

  Future<void> _handleCheckOut(EventModel event) async {
    final confirm = await AppDialog.showConfirmation(
      context: context,
      title: 'Check-Out?',
      content: 'Apakah Anda yakin ingin check-out dari acara ini sekarang?',
      type: DialogType.info,
    );

    if (confirm != true) return;

    if (!mounted) return;
    AppDialog.showLoading(context, message: 'Proses Check-out...');
    final result = await AttendanceService.checkOut(
      event.id,
      userLat: _currentPosition!.latitude,
      userLng: _currentPosition!.longitude,
      accuracy: _currentPosition!.accuracy,
    );
    if (!mounted) return;
    Navigator.pop(context); // close loading

    if (result['success']) {
      await AppDialog.showResult(
        context: context,
        title: 'Check-out Berhasil',
        content: 'Anda telah berhasil check-out dari acara.',
        type: DialogType.success,
      );
      await _initLocationAndData(); // Refresh
    } else {
      await AppDialog.showResult(
        context: context,
        title: 'Gagal Check-out',
        content: result['message'] ?? 'Terjadi kesalahan sistem.',
        type: DialogType.error,
      );
    }
  }

  Widget _buildLocationStatusCard() {
    bool isError = _errorMessage.isNotEmpty;
    bool isSuccess = _currentPosition != null;
    if (!isError && !isSuccess) return const SizedBox();

    Color accentColor = isError ? AppTheme.error : AppTheme.success;
    IconData iconData = isError ? Icons.location_off : Icons.my_location;
    String title = isError ? 'Lokasi Tidak Tersedia' : 'Lokasi Ditemukan';
    String desc = isError ? _errorMessage : 'Akurasi GPS baik. Siap untuk absensi.';

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 20,
            offset: const Offset(0, 10),
          )
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: accentColor.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(iconData, color: accentColor, size: 28),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                    color: accentColor,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  desc,
                  style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _currentUser != null ? AppDrawer(user: _currentUser!) : null,
      backgroundColor: AppTheme.background,
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _initLocationAndData,
              color: AppTheme.primary,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverAppBar(
                    title: const Text(
                      'Absensi Lokasi',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    backgroundColor: AppTheme.primary,
                    iconTheme: const IconThemeData(color: Colors.white),
                    pinned: true,
                    floating: true,
                    elevation: 0,
                    expandedHeight: 180,
                    flexibleSpace: FlexibleSpaceBar(
                      background: Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              AppTheme.primary,
                              AppTheme.primary.withOpacity(0.8),
                            ],
                          ),
                        ),
                        child: SafeArea(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.end,
                            children: [
                              const Icon(Icons.location_on, color: Colors.white, size: 48),
                              const SizedBox(height: 8),
                              const Text(
                                'Catat Kehadiran Anda',
                                style: TextStyle(color: Colors.white, fontSize: 16),
                              ),
                              const SizedBox(height: 24),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.all(24.0),
                      child: Column(
                        children: [
                          FadeInSlide(delay: 0.1, child: _buildLocationStatusCard()),
                          const SizedBox(height: 32),
                          if (_nearbyEvents.isEmpty && _errorMessage.isEmpty)
                            FadeInSlide(delay: 0.2, child: _buildEmptyState())
                          else
                            ..._nearbyEvents.asMap().entries.map((entry) => FadeInSlide(
                              delay: 0.2 + (0.1 * entry.key), 
                              child: _buildEventCard(entry.value),
                            )),
                          if (_errorMessage.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 24),
                              child: ElevatedButton.icon(
                                onPressed: _initLocationAndData,
                                icon: const Icon(Icons.refresh),
                                label: const Text('Coba Lagi'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.white,
                                  foregroundColor: AppTheme.primary,
                                  elevation: 2,
                                  padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
                                ),
                              ),
                            ),
                          const SizedBox(height: 40),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildEmptyState() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 60, horizontal: 20),
      child: Column(
        children: [
          Icon(Icons.event_busy, size: 80, color: Colors.grey.shade300),
          const SizedBox(height: 24),
          const Text(
            'Tidak Ada Acara Terdekat',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: AppTheme.textPrimary,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          const Text(
            'Anda berada di luar area absensi kegiatan atau belum ada acara yang sedang aktif.',
            style: TextStyle(
              fontSize: 14,
              color: AppTheme.textSecondary,
              height: 1.5,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildEventCard(EventModel event) {
    final isCheckedIn = _activeCheckinEventIds.contains(event.id);

    return Container(
      margin: const EdgeInsets.only(bottom: 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 15,
            offset: const Offset(0, 5),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withOpacity(0.1),
                        borderRadius: AppTheme.radiusMedium,
                      ),
                      child: const Icon(
                        Icons.event_available,
                        color: AppTheme.primary,
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            event.namaAcara,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            event.tanggalAcara,
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppTheme.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Status Box
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    color: isCheckedIn
                        ? AppTheme.success.withOpacity(0.1)
                        : Colors.grey.shade50,
                    borderRadius: AppTheme.radiusMedium,
                  ),
                  child: Row(
                    children: [
                      Icon(
                        isCheckedIn ? Icons.check_circle : Icons.info_outline,
                        color: isCheckedIn
                            ? AppTheme.success
                            : AppTheme.textSecondary,
                        size: 20,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          isCheckedIn
                              ? 'Kehadiran Anda telah tercatat pada sistem.'
                              : 'Silakan lakukan absensi kehadiran.',
                          style: TextStyle(
                            fontSize: 13,
                            color: isCheckedIn
                                ? AppTheme.success
                                : AppTheme.textSecondary,
                            fontWeight: isCheckedIn
                                ? FontWeight.w600
                                : FontWeight.normal,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          
          // Ticket dashed line
          Row(
            children: [
              Container(
                width: 12,
                height: 24,
                decoration: const BoxDecoration(
                  color: AppTheme.background,
                  borderRadius: BorderRadius.horizontal(right: Radius.circular(12)),
                ),
              ),
              Expanded(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return Flex(
                      direction: Axis.horizontal,
                      mainAxisSize: MainAxisSize.max,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: List.generate(
                        (constraints.constrainWidth() / 10).floor(),
                        (index) => const SizedBox(
                          width: 5,
                          height: 1.5,
                          child: DecoratedBox(decoration: BoxDecoration(color: Colors.black12)),
                        ),
                      ),
                    );
                  },
                ),
              ),
              Container(
                width: 12,
                height: 24,
                decoration: const BoxDecoration(
                  color: AppTheme.background,
                  borderRadius: BorderRadius.horizontal(left: Radius.circular(12)),
                ),
              ),
            ],
          ),

          // CTA Block
          Padding(
            padding: const EdgeInsets.all(20),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => isCheckedIn
                    ? _handleCheckOut(event)
                    : _handleCheckIn(event),
                icon: Icon(
                  isCheckedIn ? Icons.logout : Icons.login,
                  color: Colors.white,
                ),
                label: Text(
                  isCheckedIn ? 'Check-Out' : 'Absen Sekarang',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: isCheckedIn
                      ? Colors.orange
                      : AppTheme.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(100), // Fully rounded
                  ),
                  elevation: 0,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
