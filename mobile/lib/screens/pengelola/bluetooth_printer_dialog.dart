import 'package:flutter/material.dart';
import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import '../../services/bluetooth_printer_service.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class BluetoothPrinterDialog extends StatefulWidget {
  final int eventId;
  final String namaAcara;
  final String tanggalAcara;
  final String kodeQr;

  const BluetoothPrinterDialog({
    super.key,
    required this.eventId,
    required this.namaAcara,
    required this.tanggalAcara,
    required this.kodeQr,
  });

  @override
  State<BluetoothPrinterDialog> createState() => _BluetoothPrinterDialogState();
}

class _BluetoothPrinterDialogState extends State<BluetoothPrinterDialog> {
  List<BluetoothDevice> _devices = [];
  bool _isLoading = true;
  String _statusMessage = 'Mencari printer...';
  BluetoothDevice? _connectingDevice;

  @override
  void initState() {
    super.initState();
    _initBluetooth();
  }

  Future<void> _initBluetooth() async {
    final isOn = await BluetoothPrinterService.isBluetoothOn();
    if (!isOn) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _statusMessage = 'Bluetooth sedang mati.';
      });
      return;
    }

    final devices = await BluetoothPrinterService.getDevices();
    if (!mounted) return;
    
    if (devices.isEmpty) {
      setState(() {
        _isLoading = false;
        _statusMessage = 'Belum ada printer yang dipasangkan.\nPasangkan printer melalui pengaturan Bluetooth terlebih dahulu.';
      });
    } else {
      setState(() {
        _devices = devices;
        _isLoading = false;
        _statusMessage = 'Pilih Printer';
      });
    }
  }

  Future<void> _connectAndPrint(BluetoothDevice device) async {
    setState(() {
      _connectingDevice = device;
      _statusMessage = 'Menghubungkan ke ${device.name ?? "Printer"}...';
    });

    final connected = await BluetoothPrinterService.connect(device);
    if (!connected) {
      if (!mounted) return;
      setState(() {
        _connectingDevice = null;
        _statusMessage = 'Gagal terhubung ke printer.';
      });
      return;
    }

    if (!mounted) return;
    setState(() {
      _statusMessage = '✓ Printer terhubung\nMencetak QR...';
    });

    final success = await BluetoothPrinterService.printEventQr(
      widget.eventId.toString(),
      widget.namaAcara,
      widget.tanggalAcara,
      widget.kodeQr,
    );
    
    // Disconnect after printing
    await BluetoothPrinterService.disconnect();

    if (!mounted) return;

    setState(() {
      _connectingDevice = null;
      _statusMessage = success ? 'QR berhasil dicetak.' : 'QR gagal dicetak.';
    });
    
    if (success) {
      Future.delayed(const Duration(seconds: 2), () {
        if (mounted) Navigator.pop(context);
      });
    }
  }

  @override
  void dispose() {
    // Ensure we disconnect if user closes dialog while connected
    BluetoothPrinterService.disconnect();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool isError = _statusMessage.contains('gagal') || _statusMessage.contains('mati') || _statusMessage.contains('Belum ada');
    final bool isSuccess = _statusMessage.contains('berhasil') || _statusMessage.contains('terhubung');
    final bool isWorking = _connectingDevice != null || _isLoading;

    return AlertDialog(
      title: const Text('Cetak QR Code', style: TextStyle(fontWeight: FontWeight.bold)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: isError ? Colors.red.withValues(alpha: 0.1) : (isSuccess ? Colors.green.withValues(alpha: 0.1) : Colors.blue.withValues(alpha: 0.1)),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: isError ? Colors.red.withValues(alpha: 0.3) : (isSuccess ? Colors.green.withValues(alpha: 0.3) : Colors.blue.withValues(alpha: 0.3))),
              ),
              child: Text(_statusMessage, style: TextStyle(
                fontWeight: FontWeight.w600,
                color: isError ? Colors.red : (isSuccess ? Colors.green : Colors.blue.shade800),
              ), textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            
            if (_isLoading)
              const Center(child: Padding(
                padding: EdgeInsets.all(24.0),
                child: CustomLoadingIndicator(),
              ))
            else if (_devices.isEmpty)
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 24.0),
                  child: Column(
                    children: [
                      const Icon(Icons.bluetooth_disabled, size: 48, color: Colors.grey),
                      const SizedBox(height: 16),
                      ElevatedButton.icon(
                        onPressed: () {
                          setState(() {
                            _isLoading = true;
                            _statusMessage = 'Mencari printer...';
                          });
                          _initBluetooth();
                        },
                        icon: const Icon(Icons.refresh),
                        label: const Text('Cari Lagi'),
                      ),
                    ],
                  ),
                ),
              )
            else
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _devices.length,
                  itemBuilder: (context, index) {
                    final device = _devices[index];
                    final isThisDeviceLoading = _connectingDevice?.address == device.address;
                    final isDisabled = _connectingDevice != null && !isThisDeviceLoading;
                    
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      elevation: 2,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: Padding(
                        padding: const EdgeInsets.all(12.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.print, color: Colors.blueGrey, size: 28),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(device.name ?? 'Unknown Device', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                      Text(device.address ?? '', style: const TextStyle(color: Colors.grey, fontSize: 12)),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton(
                                onPressed: (isDisabled || isThisDeviceLoading) ? null : () => _connectAndPrint(device),
                                style: ElevatedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                ),
                                child: isThisDeviceLoading 
                                  ? const SizedBox(width: 20, height: 20, child: CustomLoadingIndicator(size: 24, ))
                                  : const Text('Hubungkan & Cetak', style: TextStyle(fontWeight: FontWeight.bold)),
                              ),
                            )
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
      actions: [
        if (!isWorking)
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tutup', style: TextStyle(color: Colors.grey)),
          ),
      ],
    );
  }
}
