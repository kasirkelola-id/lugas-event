import 'package:flutter/material.dart';
import '../../models/attendance_model.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../auth/login_screen.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  State<AttendanceHistoryScreen> createState() => _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  List<AttendanceModel> _history = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await AttendanceService.getMyHistory();
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _history = result['history'] as List<AttendanceModel>;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Riwayat Absensi')),
      body: RefreshIndicator(
        onRefresh: _loadHistory,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _history.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _history.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadHistory, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_history.isEmpty) {
      return const Center(child: Text('Belum ada riwayat absensi.'));
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _history.length,
      itemBuilder: (context, index) {
        final item = _history[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: ListTile(
            title: Text(item.namaAcara, style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text('Waktu: ${item.waktuAbsen}\nTanggal Acara: ${item.tanggalAcara}'),
            isThreeLine: true,
            trailing: Chip(
              label: Text(item.isActive ? 'AKTIF' : 'SELESAI'),
              backgroundColor: item.isActive ? Colors.green.shade100 : Colors.grey.shade200,
              labelStyle: TextStyle(
                color: item.isActive ? Colors.green.shade800 : Colors.grey.shade700,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        );
      },
    );
  }
}
