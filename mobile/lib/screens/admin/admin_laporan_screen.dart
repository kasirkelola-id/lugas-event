import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../services/report_service.dart';
import '../../models/user_model.dart';
import '../../models/report_model.dart';
import '../widgets/app_drawer.dart';

class AdminLaporanScreen extends StatefulWidget {
  const AdminLaporanScreen({super.key});

  @override
  State<AdminLaporanScreen> createState() => _AdminLaporanScreenState();
}

class _AdminLaporanScreenState extends State<AdminLaporanScreen> {
  UserModel? _user;
  ReportModel? _report;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    if (!userResult['success']) {
      if (mounted) _handleError(userResult['message']);
      return;
    }

    final reportResult = await ReportService.getSummary();
    if (!mounted) return;

    if (reportResult['success']) {
      setState(() {
        _user = userResult['user'];
        _report = reportResult['data'] as ReportModel;
        _isLoading = false;
      });
    } else {
      _handleError(reportResult['message'] ?? 'Data laporan gagal dimuat.');
    }
  }

  void _handleError(String message) {
    setState(() {
      _isLoading = false;
      _errorMessage = message;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan Sistem')),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: Colors.grey.shade50,
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _report == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null && _report == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_report == null) return const SizedBox();

    return ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const Text(
          'Ringkasan Laporan Sistem Keseluruhan',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.indigo),
        ),
        const SizedBox(height: 24),
        
        // Block 1: Event Stats
        const Text('Data Acara', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87)),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard('Total Acara', _report!.totalAcara.toString(), Icons.event_note, Colors.indigo),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard('Acara Aktif', _report!.acaraAktif.toString(), Icons.event_available, Colors.teal),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard('Selesai', _report!.acaraSelesai.toString(), Icons.history, Colors.grey.shade700),
            ),
          ],
        ),
        
        const SizedBox(height: 24),
        
        // Block 2: Participant Stats
        const Text('Data Kehadiran Peserta', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87)),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard('Total Peserta', _report!.totalPeserta.toString(), Icons.people_alt, Colors.blue.shade700),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard('Hadir', _report!.totalHadir.toString(), Icons.check_circle_outline, Colors.green.shade600),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard('Belum Hadir', _report!.totalBelumHadir.toString(), Icons.pending_actions, Colors.orange.shade700),
            ),
          ],
        ),
        
        const SizedBox(height: 24),
        
        // Block 3: Percentage
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [Colors.indigo.shade600, Colors.blue.shade500]),
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(color: Colors.blue.withValues(alpha: 0.3), blurRadius: 10, offset: const Offset(0, 5)),
            ],
          ),
          child: Column(
            children: [
              const Text('Persentase Kehadiran', style: TextStyle(color: Colors.white70, fontSize: 16)),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _report!.persentaseKehadiran.toStringAsFixed(1),
                    style: const TextStyle(color: Colors.white, fontSize: 48, fontWeight: FontWeight.bold),
                  ),
                  const Padding(
                    padding: EdgeInsets.only(bottom: 8.0, left: 4.0),
                    child: Text('%', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              LinearProgressIndicator(
                value: _report!.totalPeserta == 0 ? 0 : (_report!.totalHadir / _report!.totalPeserta),
                backgroundColor: Colors.white.withValues(alpha: 0.3),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                minHeight: 8,
                borderRadius: BorderRadius.circular(4),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16.0, horizontal: 8.0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.2)),
        boxShadow: [
          BoxShadow(color: Colors.grey.withValues(alpha: 0.1), blurRadius: 4, offset: const Offset(0, 2)),
        ],
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: color),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Colors.grey.shade700, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

