import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
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
      appBar: AppBar(
        title: const Text('Laporan Sistem'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _report == null) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _report == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.error)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _loadData, child: const Text('Coba Lagi')),
          ],
        ),
      );
    }

    if (_report == null) return const SizedBox();

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const Text(
          'Ringkasan Laporan Sistem Keseluruhan',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
        ),
        const SizedBox(height: 24),
        
        // Block 1: Event Stats
        const Text('Data Acara', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard('Total Acara', _report!.totalAcara.toString(), Icons.event_note, AppTheme.primary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard('Acara Aktif', _report!.acaraAktif.toString(), Icons.event_available, AppTheme.success),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: _buildStatCard('Total Hadir', _report!.totalHadir.toString(), Icons.how_to_reg, AppTheme.primary),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: _buildStatCard('Acara Selesai', _report!.acaraSelesai.toString(), Icons.event_busy, AppTheme.textSecondary),
            ),
          ],
        ),
        const SizedBox(height: 24),
        

        
        const SizedBox(height: 24),
        
        // Block 3: Percentage
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: AppTheme.primaryGradient,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowMedium,
          ),
          child: Column(
            children: [
              const Text('Rata-rata Kehadiran', style: TextStyle(color: Colors.white70, fontSize: 16, fontWeight: FontWeight.w600)),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _report!.totalAcara > 0 ? (_report!.totalHadir / _report!.totalAcara).toStringAsFixed(1) : '0',
                    style: const TextStyle(color: Colors.white, fontSize: 56, fontWeight: FontWeight.bold, height: 1),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'orang / acara',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 20.0, horizontal: 12.0),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: AppTheme.radiusMedium,
        border: Border.all(color: color.withValues(alpha: 0.2)),
        boxShadow: AppTheme.shadowSoft,
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: AppTheme.radiusSmall,
            ),
            child: Icon(icon, color: color, size: 28),
          ),
          const SizedBox(height: 16),
          Text(
            value,
            style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}


