import 'package:flutter/material.dart';
import 'package:blue_thermal_printer/blue_thermal_printer.dart';
import '../../services/bluetooth_printer_service.dart';

class BluetoothPrinterDialog extends StatefulWidget {
  final String namaAcara;
  final String tanggalAcara;
  final String kodeQr;

  const BluetoothPrinterDialog({
    super.key,
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
  bool _isPrinting = false;

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
        _statusMessage = 'Bluetooth belum aktif.';
      });
      return;
    }

    final devices = await BluetoothPrinterService.getDevices();
    if (!mounted) return;
    
    if (devices.isEmpty) {
      setState(() {
        _isLoading = false;
        _statusMessage = 'Printer tidak ditemukan.';
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
      _isPrinting = true;
      _statusMessage = 'Menghubungkan printer...';
    });

    final connected = await BluetoothPrinterService.connect(device);
    if (!connected) {
      if (!mounted) return;
      setState(() {
        _isPrinting = false;
        _statusMessage = 'Gagal menghubungkan printer.';
      });
      return;
    }

    if (!mounted) return;
    setState(() {
      _statusMessage = 'Mencetak...';
    });

    final success = await BluetoothPrinterService.printEventQr(
      widget.namaAcara,
      widget.tanggalAcara,
      widget.kodeQr,
    );
    
    // Disconnect after printing
    await BluetoothPrinterService.disconnect();

    if (!mounted) return;

    setState(() {
      _isPrinting = false;
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
    return AlertDialog(
      title: const Text('Pilih Printer'),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(_statusMessage, style: TextStyle(
              fontWeight: FontWeight.bold,
              color: _statusMessage.contains('gagal') || _statusMessage.contains('belum') 
                  ? Colors.red 
                  : (_statusMessage.contains('berhasil') ? Colors.green : Colors.black),
            )),
            const SizedBox(height: 16),
            if (_isLoading || _isPrinting)
              const Center(child: CircularProgressIndicator())
            else if (_devices.isNotEmpty)
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _devices.length,
                  itemBuilder: (context, index) {
                    final device = _devices[index];
                    return ListTile(
                      leading: const Icon(Icons.print),
                      title: Text(device.name ?? 'Unknown Device'),
                      subtitle: Text(device.address ?? ''),
                      trailing: ElevatedButton(
                        onPressed: () => _connectAndPrint(device),
                        child: const Text('Hubungkan'),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
      actions: [
        if (!_isPrinting)
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tutup'),
          ),
      ],
    );
  }
}
