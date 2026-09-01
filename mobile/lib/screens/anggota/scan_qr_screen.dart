import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:geolocator/geolocator.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/feedback_dialogs.dart';

class ScanQrScreen extends StatefulWidget {
  const ScanQrScreen({super.key});

  @override
  State<ScanQrScreen> createState() => _ScanQrScreenState();
}

class _ScanQrScreenState extends State<ScanQrScreen> {
  final MobileScannerController _scannerController = MobileScannerController();
  bool _isProcessing = false;

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) async {
    if (_isProcessing) return;

    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;
    
    final String? rawValue = barcodes.first.rawValue;
    if (rawValue == null || rawValue.isEmpty) return;

    setState(() {
      _isProcessing = true;
    });

    _scannerController.stop();

    final confirm = await _showConfirmationDialog(rawValue);
    
    if (confirm != true) {
      setState(() {
        _isProcessing = false;
      });
      _scannerController.start();
      return;
    }

    _submitAbsensi(rawValue);
  }

  Future<bool?> _showConfirmationDialog(String qrCode) {
    return FeedbackDialogs.showConfirmation(
      context: context,
      title: 'Catat Kehadiran',
      content: 'Apakah Anda yakin ingin memverifikasi kehadiran untuk acara ini?',
    );
  }

  Future<Position?> _getCurrentLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Layanan lokasi dinonaktifkan. Silakan hidupkan GPS.')));
      }
      return null;
    }

    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Izin lokasi ditolak.')));
        }
        return null;
      }
    }
    
    if (permission == LocationPermission.deniedForever) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Izin lokasi ditolak secara permanen. Tidak dapat melakukan presensi dengan GPS.')));
      }
      return null;
    } 

    return await Geolocator.getCurrentPosition();
  }

  void _submitAbsensi(String qrCode) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        content: Row(
          children: const [
            CircularProgressIndicator(),
            SizedBox(width: 24),
            Expanded(child: Text('Memverifikasi lokasi & absensi...')),
          ],
        ),
      ),
    );

    // Get location just in case the event requires GPS.
    // If it doesn't, sending coordinates is harmless.
    final position = await _getCurrentLocation();
    
    if (position != null && position.isMocked) {
      if (!mounted) return;
      Navigator.pop(context); // close loading dialog
      await FeedbackDialogs.showConfirmation(
        context: context,
        title: 'Lokasi Palsu Terdeteksi',
        content: 'Anda terdeteksi menggunakan aplikasi Fake GPS (Mock Location). Harap matikan pemalsu lokasi untuk melakukan presensi.',
        confirmText: 'Mengerti',
        isDestructive: true,
      );
      setState(() {
        _isProcessing = false;
      });
      _scannerController.start();
      return;
    }
    
    final result = await AttendanceService.submitAttendance(
      qrCode,
      userLat: position?.latitude,
      userLng: position?.longitude,
    );
    
    if (!mounted) return;
    Navigator.pop(context); // close loading dialog

    if (result['success']) {
      await FeedbackDialogs.showConfirmation(
        context: context,
        title: 'Absensi Berhasil',
        content: 'Anda telah tercatat hadir pada acara ini.',
        confirmText: 'Selesai',
      );
      if (!mounted) return;
      Navigator.pop(context); // close scanner screen and return to dashboard
    } else {
      String title = 'Absensi Gagal';
      IconData iconData = Icons.error_outline;
      Color iconColor = Colors.red;
      
      final msg = (result['message'] ?? '').toString().toLowerCase();
      if (msg.contains('sudah melakukan absensi')) {
        title = 'Absensi Sudah Tercatat';
        iconData = Icons.info_outline;
        iconColor = Colors.orange;
      } else if (msg.contains('acara sudah ditutup')) {
        title = 'Acara Sudah Ditutup';
      } else if (msg.contains('tidak valid')) {
        title = 'QR Code Tidak Valid';
      }

      await FeedbackDialogs.showConfirmation(
        context: context,
        title: title,
        content: result['message'] ?? 'Koneksi Bermasalah',
        confirmText: 'OK',
        isDestructive: true,
      );
      
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        setState(() {
          _isProcessing = false;
        });
        _scannerController.start();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan QR Acara'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Column(
        children: [
          const Padding(
            padding: EdgeInsets.all(16.0),
            child: Text(
              'Arahkan kamera ke QR Code acara',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              textAlign: TextAlign.center,
            ),
          ),
          Expanded(
            child: Stack(
              alignment: Alignment.center,
              children: [
                MobileScanner(
                  controller: _scannerController,
                  onDetect: _onDetect,
                ),
                // QR Overlay Box visual cue
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white.withValues(alpha: 0.5), width: 2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  width: 250,
                  height: 250,
                ),
                if (_isProcessing)
                  Container(
                    color: Colors.black54,
                    child: const Center(
                      child: CircularProgressIndicator(color: Colors.white),
                    ),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(24.0),
            width: double.infinity,
            child: CustomButton(
              text: 'Batal & Kembali',
              onPressed: () => Navigator.pop(context),
              icon: Icons.close,
              isFullWidth: true,
              type: ButtonType.danger,
            ),
          ),
        ],
      ),
    );
  }
}
