import 'dart:typed_data';
import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import 'package:esc_pos_utils_plus/esc_pos_utils_plus.dart';

class BluetoothPrinterService {
  static final BlueThermalPrinter _printer = BlueThermalPrinter.instance;

  static Future<bool> isBluetoothAvailable() async {
    return await _printer.isAvailable ?? false;
  }

  static Future<bool> isBluetoothOn() async {
    return await _printer.isOn ?? false;
  }

  static Future<List<BluetoothDevice>> getDevices() async {
    try {
      return await _printer.getBondedDevices();
    } catch (e) {
      return [];
    }
  }

  static Future<bool> isConnected() async {
    return await _printer.isConnected ?? false;
  }

  static Future<bool> connect(BluetoothDevice device) async {
    try {
      if (await _printer.isConnected ?? false) {
        await _printer.disconnect();
      }
      return await _printer.connect(device) ?? false;
    } catch (e) {
      return false;
    }
  }

  static Future<void> disconnect() async {
    try {
      if (await _printer.isConnected ?? false) {
        await _printer.disconnect();
      }
    } catch (e) {
      // ignore
    }
  }

  static Future<bool> printEventQr(String namaAcara, String tanggalAcara, String kodeQr) async {
    if (!(await _printer.isConnected ?? false)) return false;

    try {
      final profile = await CapabilityProfile.load();
      final generator = Generator(PaperSize.mm58, profile);
      List<int> bytes = [];

      bytes += generator.text('LUGASKU', styles: const PosStyles(align: PosAlign.center, bold: true, height: PosTextSize.size2, width: PosTextSize.size2));
      bytes += generator.text('ABSENSI ACARA', styles: const PosStyles(align: PosAlign.center, bold: true));
      bytes += generator.feed(1);
      
      bytes += generator.text(namaAcara, styles: const PosStyles(align: PosAlign.center));
      bytes += generator.text(tanggalAcara, styles: const PosStyles(align: PosAlign.center));
      bytes += generator.feed(1);
      
      bytes += generator.qrcode(kodeQr, size: QRSize.size6, cor: QRCorrection.H);
      
      bytes += generator.feed(1);
      bytes += generator.text('Scan QR untuk melakukan', styles: const PosStyles(align: PosAlign.center));
      bytes += generator.text('absensi', styles: const PosStyles(align: PosAlign.center));
      bytes += generator.feed(2);
      bytes += generator.cut();

      await _printer.writeBytes(Uint8List.fromList(bytes));
      return true;
    } catch (e) {
      return false;
    }
  }
}
