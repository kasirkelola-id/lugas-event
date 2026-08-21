import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';

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
    return showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Absensi'),
        content: Text('Apakah Anda ingin melakukan absensi pada acara dengan kode:\n\n$qrCode?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Absen Sekarang'),
          ),
        ],
      ),
    );
  }

  void _submitAbsensi(String qrCode) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => const Center(child: CircularProgressIndicator()),
    );

    final result = await AttendanceService.submitAttendance(qrCode);
    
    if (!mounted) return;
    Navigator.pop(context); // close loading dialog

    if (result['success']) {
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          title: const Text('Absensi berhasil!'),
          content: Text('Anda telah tercatat hadir pada acara:\n${result['data']['nama_acara'] ?? qrCode}'),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.pop(context); // close scanner screen and return to dashboard
              },
              child: const Text('Selesai'),
            ),
          ],
        ),
      );
    } else {
      await showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Absensi Gagal'),
          content: Text(result['message'] ?? 'Terjadi kesalahan.'),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('OK'),
            ),
          ],
        ),
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
      appBar: AppBar(title: const Text('Scan QR Acara')),
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
            child: MobileScanner(
              controller: _scannerController,
              onDetect: _onDetect,
            ),
          ),
        ],
      ),
    );
  }
}
