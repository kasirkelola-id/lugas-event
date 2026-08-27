import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';
import 'package:qr/qr.dart';
import 'package:image/image.dart' as img;

class BluetoothPrinterService {
  static final BlueThermalPrinter _printer = BlueThermalPrinter.instance;

  static void _log(String action, {String status = '', String message = ''}) {
    if (kDebugMode) {
      debugPrint('\n========== [PRINTER] ==========');
      debugPrint('ACTION: $action');
      if (status.isNotEmpty) debugPrint('STATUS: $status');
      if (message.isNotEmpty) debugPrint('MESSAGE: $message');
      debugPrint('================================\n');
    }
  }

  static Future<bool> isBluetoothAvailable() async {
    return await _printer.isAvailable ?? false;
  }

  static Future<bool> isBluetoothOn() async {
    return await _printer.isOn ?? false;
  }

  static Future<List<BluetoothDevice>> getDevices() async {
    try {
      _log('DISCOVER', status: 'STARTED');
      final devices = await _printer.getBondedDevices();
      
      if (kDebugMode) {
        debugPrint('\n========== [PRINTER] ==========');
        debugPrint('ACTION: DISCOVER');
        debugPrint('FOUND: ${devices.length} DEVICE(S)');
        for (var device in devices) {
          debugPrint('NAME: ${device.name ?? "Unknown"}');
          debugPrint('ADDRESS: ${device.address ?? "Unknown"}');
        }
        debugPrint('================================\n');
      }
      
      return devices;
    } catch (e) {
      _log('DISCOVER', status: 'FAILED', message: e.toString());
      return [];
    }
  }

  static Future<bool> isConnected() async {
    return await _printer.isConnected ?? false;
  }

  static Future<bool> connect(BluetoothDevice device) async {
    try {
      _log('CONNECT', message: 'Connecting to ${device.name ?? "Unknown"} (${device.address ?? "Unknown"})...');
      
      if (await _printer.isConnected ?? false) {
        await _printer.disconnect();
      }
      
      final connected = await _printer.connect(device).timeout(const Duration(seconds: 10));
      
      _log('CONNECT', status: (connected == true) ? 'SUCCESS' : 'FAILED');
      return connected ?? false;
    } on TimeoutException {
      _log('CONNECT', status: 'FAILED', message: 'Connection timeout after 10 seconds');
      return false;
    } catch (e) {
      _log('CONNECT', status: 'FAILED', message: e.toString());
      return false;
    }
  }

  static Future<void> disconnect() async {
    try {
      if (await _printer.isConnected ?? false) {
        await _printer.disconnect();
        _log('DISCONNECT', status: 'SUCCESS');
      }
    } catch (e) {
      _log('DISCONNECT', status: 'FAILED', message: e.toString());
    }
  }

  static img.Image _generateQrImage(String text, {int size = 200}) {
    final qrCode = QrCode.fromData(data: text, errorCorrectLevel: QrErrorCorrectLevel.M);
    final qrImage = QrImage(qrCode);
    final moduleCount = qrImage.moduleCount;
    final scale = (size / moduleCount).floor();
    final actualSize = moduleCount * scale;
    
    final image = img.Image(width: actualSize, height: actualSize);
    
    img.fill(image, color: img.ColorRgb8(255, 255, 255));
    
    for (int y = 0; y < moduleCount; y++) {
      for (int x = 0; x < moduleCount; x++) {
        if (qrImage.isDark(y, x)) {
          img.fillRect(
            image, 
            x1: x * scale, 
            y1: y * scale, 
            x2: (x * scale) + scale - 1, 
            y2: (y * scale) + scale - 1, 
            color: img.ColorRgb8(0, 0, 0)
          );
        }
      }
    }
    return image;
  }

  static Future<bool> printEventQr(String eventId, String namaAcara, String tanggalAcara, String kodeQr) async {
    _log('PRINT', message: 'EVENT_ID: $eventId, CONNECTED: ${await _printer.isConnected}');
    
    if (!(await _printer.isConnected ?? false)) {
      _log('PRINT', status: 'FAILED', message: 'Printer is not connected.');
      return false;
    }

    try {
      final success = await Future(() async {
        final profile = await CapabilityProfile.load();
        final generator = Generator(PaperSize.mm58, profile);
        List<int> bytes = [];

        // Header
        bytes += generator.text('LUGAS', styles: const PosStyles(align: PosAlign.center, bold: true, height: PosTextSize.size2, width: PosTextSize.size2));
        bytes += generator.feed(1);
        
        // Event Info
        bytes += generator.text(namaAcara, styles: const PosStyles(align: PosAlign.center, bold: true));
        if (tanggalAcara.isNotEmpty) {
          bytes += generator.text(tanggalAcara, styles: const PosStyles(align: PosAlign.center));
        }
        bytes += generator.feed(1);
        
        // QR Code Image Fallback (Fixes 'qr create err' on unsupported printers)
        final qrImg = _generateQrImage(kodeQr, size: 250);
        bytes += generator.image(qrImg, align: PosAlign.center);
        bytes += generator.feed(1);
        
        // Footer
        bytes += generator.text('Scan untuk Absensi', styles: const PosStyles(align: PosAlign.center));
        bytes += generator.feed(3);
        
        // Send bytes
        await _printer.writeBytes(Uint8List.fromList(bytes));
        return true;
      }).timeout(const Duration(seconds: 15));
      
      _log('PRINT', status: 'SUCCESS', message: 'QR_GENERATED: true');
      return success;
    } on TimeoutException {
      _log('PRINT', status: 'FAILED', message: 'Print timeout after 15 seconds');
      return false;
    } catch (e) {
      _log('PRINT', status: 'FAILED', message: e.toString());
      return false;
    }
  }
}
