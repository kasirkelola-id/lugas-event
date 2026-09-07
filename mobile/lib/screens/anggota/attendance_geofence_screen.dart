import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../models/event_model.dart';
import '../../services/event_service.dart';
import '../../services/attendance_service.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_dialog.dart';

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
    if (_errorMessage.isNotEmpty) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppTheme.error.withValues(alpha: 0.1),
          borderRadius: AppTheme.radiusLarge,
          border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
        ),
        child: Row(
          children: [
            const Icon(Icons.location_off, color: AppTheme.error, size: 32),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Lokasi Tidak Tersedia',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppTheme.error,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _errorMessage,
                    style: const TextStyle(fontSize: 12, color: AppTheme.error),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    if (_currentPosition != null) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppTheme.success.withValues(alpha: 0.1),
          borderRadius: AppTheme.radiusLarge,
          border: Border.all(color: AppTheme.success.withValues(alpha: 0.3)),
        ),
        child: const Row(
          children: [
            Icon(Icons.my_location, color: AppTheme.success, size: 32),
            SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Lokasi Ditemukan',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppTheme.success,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Akurasi GPS baik. Siap untuk absensi.',
                    style: TextStyle(fontSize: 12, color: AppTheme.success),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return const SizedBox();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Absensi Lokasi'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      backgroundColor: AppTheme.background,
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator(color: AppTheme.primary))
          : RefreshIndicator(
              onRefresh: _initLocationAndData,
              color: AppTheme.primary,
              child: ListView(
                padding: const EdgeInsets.all(20),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  _buildLocationStatusCard(),
                  const SizedBox(height: 24),
                  if (_nearbyEvents.isEmpty && _errorMessage.isEmpty)
                    _buildEmptyState()
                  else
                    ..._nearbyEvents.map((event) => _buildEventCard(event)),
                  if (_errorMessage.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 24),
                      child: ElevatedButton.icon(
                        onPressed: _initLocationAndData,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Coba Lagi'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.surface,
                          foregroundColor: AppTheme.primary,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(vertical: 16),
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
                        color: AppTheme.primary.withValues(alpha: 0.1),
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
                const SizedBox(height: 24),

                // Status Box
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: isCheckedIn
                        ? AppTheme.success.withValues(alpha: 0.05)
                        : AppTheme.background,
                    borderRadius: AppTheme.radiusMedium,
                    border: Border.all(
                      color: isCheckedIn
                          ? AppTheme.success.withValues(alpha: 0.2)
                          : Colors.grey.shade200,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        isCheckedIn ? Icons.check_circle : Icons.info_outline,
                        color: isCheckedIn
                            ? AppTheme.success
                            : AppTheme.textSecondary,
                        size: 24,
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

          // CTA Block
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppTheme.background,
              borderRadius: const BorderRadius.only(
                bottomLeft: Radius.circular(24),
                bottomRight: Radius.circular(24),
              ),
            ),
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
                    borderRadius: AppTheme.radiusMedium,
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
