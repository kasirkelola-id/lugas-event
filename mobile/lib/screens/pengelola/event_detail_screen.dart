import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../models/event_model.dart';
import '../../services/event_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';
import 'edit_event_screen.dart';
import 'attendance_list_screen.dart';

class EventDetailScreen extends StatefulWidget {
  final int eventId;
  const EventDetailScreen({super.key, required this.eventId});

  @override
  State<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends State<EventDetailScreen> {
  EventModel? _event;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadEvent();
  }

  Future<void> _loadEvent() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await EventService.getEvent(widget.eventId);
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _event = result['event'];
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = result['message'];
      });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
    }
  }

  void _closeEvent() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Tutup Acara'),
        content: const Text('Apakah Anda yakin ingin menutup acara ini?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Tutup Acara'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() {
      _isLoading = true;
    });

    final result = await EventService.closeEvent(widget.eventId);
    
    if (!mounted) return;

    if (result['success']) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Acara berhasil ditutup.')));
      _loadEvent(); // Refresh data
    } else {
      setState(() { _isLoading = false; });
      if (result['statusCode'] == 401) {
        await AuthService.logout();
        if (!mounted) return;
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'])));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Acara'),
        actions: [
          if (_event != null)
            IconButton(
              icon: const Icon(Icons.edit),
              onPressed: () async {
                final result = await Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => EditEventScreen(event: _event!)),
                );
                if (result == true) {
                  _loadEvent();
                }
              },
            )
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _event == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _event == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadEvent, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_event == null) return const SizedBox();

    return RefreshIndicator(
      onRefresh: _loadEvent,
      child: ListView(
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_event!.namaAcara, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 8),
                  Text('Tanggal: ${_event!.tanggalAcara}'),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Text('Status: '),
                      Chip(
                        label: Text(_event!.isActive ? 'AKTIF' : 'SELESAI'),
                        backgroundColor: _event!.isActive ? Colors.green.shade100 : Colors.grey.shade200,
                        labelStyle: TextStyle(
                          color: _event!.isActive ? Colors.green.shade800 : Colors.grey.shade700,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(_event!.jumlahHadir == null || _event!.jumlahHadir == 0 
                       ? 'Belum ada peserta' 
                       : 'Jumlah Hadir: ${_event!.jumlahHadir} peserta', 
                       style: const TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                children: [
                  const Text('QR CODE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                  const SizedBox(height: 16),
                  if (_event!.kodeQr.isEmpty)
                    const Text('QR Code tidak tersedia.', style: TextStyle(color: Colors.red))
                  else
                    Container(
                      color: Colors.white,
                      padding: const EdgeInsets.all(16),
                      child: QrImageView(
                        data: _event!.kodeQr,
                        version: QrVersions.auto,
                        size: 250.0,
                        backgroundColor: Colors.white,
                      ),
                    ),
                  const SizedBox(height: 16),
                  Text(_event!.kodeQr, style: const TextStyle(fontFamily: 'monospace', fontSize: 16, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  if (_event!.isActive)
                    const Text('Scan QR untuk melakukan absensi', style: TextStyle(color: Colors.green, fontWeight: FontWeight.bold))
                  else
                    const Text('Acara sudah selesai', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 32),
          ElevatedButton.icon(
            onPressed: _isLoading ? null : () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => AttendanceListScreen(event: _event!)),
              );
            },
            icon: const Icon(Icons.people),
            label: const Text('Lihat Daftar Hadir'),
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
          ),
          const SizedBox(height: 16),
          if (_event!.isActive)
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red, 
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
              onPressed: _isLoading ? null : _closeEvent,
              child: _isLoading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('Tutup Acara'),
            )
          else
            const Center(
              child: Text('Acara sudah selesai', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
            ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }
}
