import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../../models/event_model.dart';
import '../../services/event_service.dart';
import '../../services/attendance_service.dart';
import '../widgets/common/custom_button.dart';

class AttendanceGeofenceScreen extends StatefulWidget {
  const AttendanceGeofenceScreen({Key? key}) : super(key: key);

  @override
  State<AttendanceGeofenceScreen> createState() => _AttendanceGeofenceScreenState();
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
          _errorMessage = 'Layanan Lokasi (GPS) tidak aktif. Mohon aktifkan GPS Anda.';
          _isLoading = false;
        });
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          setState(() {
            _errorMessage = 'Izin lokasi ditolak.';
            _isLoading = false;
          });
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        setState(() {
          _errorMessage = 'Izin lokasi ditolak secara permanen. Mohon ubah di pengaturan aplikasi.';
          _isLoading = false;
        });
        return;
      }

      // Ambil lokasi
      _currentPosition = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      
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
        _errorMessage = 'Gagal mendapatkan lokasi Anda.';
        _isLoading = false;
      });
    }
  }

  Future<void> _fetchData() async {
    final eventResult = await EventService.getEvents();
    final statusResult = await AttendanceService.getStatus();

    if (eventResult['success'] && statusResult['success']) {
      final List<EventModel> allEvents = eventResult['events'];
      _activeCheckinEventIds = statusResult['active_event_ids'] as List<int>;
      
      _nearbyEvents = [];
      for (var event in allEvents) {
        if (!event.isActive) continue;
        
        if (event.requireGps && event.latitude != null && event.longitude != null && event.radius != null) {
          double distance = Geolocator.distanceBetween(
            _currentPosition!.latitude, 
            _currentPosition!.longitude, 
            event.latitude!, 
            event.longitude!
          );
          
          if (distance <= event.radius!) {
            _nearbyEvents.add(event);
          }
        } else {
          // If event doesn't require GPS, it's always "nearby"
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
    setState(() => _isLoading = true);
    final result = await AttendanceService.checkIn(
      event.id,
      userLat: _currentPosition!.latitude,
      userLng: _currentPosition!.longitude,
      accuracy: _currentPosition!.accuracy,
    );
    
    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'] ?? 'Check-in berhasil!')));
      await _fetchData(); // Refresh
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'] ?? 'Gagal check-in'), backgroundColor: Colors.red));
    }
  }

  Future<void> _handleCheckOut(EventModel event) async {
    setState(() => _isLoading = true);
    final result = await AttendanceService.checkOut(
      event.id,
      userLat: _currentPosition!.latitude,
      userLng: _currentPosition!.longitude,
      accuracy: _currentPosition!.accuracy,
    );
    
    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'] ?? 'Check-out berhasil!')));
      await _fetchData(); // Refresh
    } else {
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'] ?? 'Gagal check-out'), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Absensi Lokasi')),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage.isNotEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline, size: 48, color: Colors.red),
                      const SizedBox(height: 16),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 24.0),
                        child: Text(_errorMessage, textAlign: TextAlign.center, style: TextStyle(color: Colors.red)),
                      ),
                      const SizedBox(height: 24),
                      CustomButton(text: 'Coba Lagi', onPressed: _initLocationAndData),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _initLocationAndData,
                  child: _nearbyEvents.isEmpty
                      ? ListView(
                          children: [
                            SizedBox(height: MediaQuery.of(context).size.height * 0.3),
                            const Icon(Icons.location_off, size: 64, color: Colors.grey),
                            const SizedBox(height: 16),
                            const Center(child: Text('Tidak ada acara terdekat di lokasi Anda saat ini.')),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _nearbyEvents.length,
                          itemBuilder: (context, index) {
                            final event = _nearbyEvents[index];
                            final isCheckedIn = _activeCheckinEventIds.contains(event.id);
                            
                            return Card(
                              elevation: 2,
                              margin: const EdgeInsets.only(bottom: 16),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(event.namaAcara, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                                    const SizedBox(height: 4),
                                    Text('Tanggal: ${event.tanggalAcara}', style: TextStyle(color: Colors.grey[700])),
                                    const SizedBox(height: 16),
                                    if (isCheckedIn)
                                      CustomButton(
                                        text: 'Sudah Absen (Check-Out)',
                                        onPressed: () => _handleCheckOut(event),
                                        type: ButtonType.danger,
                                      )
                                    else
                                      CustomButton(
                                        text: 'Check-In Kehadiran',
                                        onPressed: () => _handleCheckIn(event),
                                        type: ButtonType.primary,
                                      )
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
