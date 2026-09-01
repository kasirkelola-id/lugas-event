import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';

class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  UserModel? _user;
  List<EventModel> _events = [];
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

    final eventsResult = await EventService.getEvents();
    if (!mounted) return;

    if (eventsResult['success']) {
      setState(() {
        _user = userResult['user'];
        _events = eventsResult['events'] as List<EventModel>;
        _isLoading = false;
      });
    } else {
      _handleError(eventsResult['message'] ?? 'Data acara gagal dimuat.');
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
        title: const Text('Dashboard Admin'),
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
    if (_isLoading && _events.isEmpty && _user == null) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _events.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(color: AppTheme.error.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            ),
            const SizedBox(height: 24),
            Text(_errorMessage!, style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 24),
            CustomButton(
              text: 'Coba Lagi',
              onPressed: _loadData,
              isFullWidth: false,
              icon: Icons.refresh,
            ),
          ],
        ),
      );
    }

    final totalEvents = _events.length;
    final activeEvents = _events.where((e) => e.isActive).length;

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        if (_user != null) ...[
          _buildGreetingSection(),
          const SizedBox(height: 32),
          _buildSummarySection(totalEvents, activeEvents),
          const SizedBox(height: 32),
        ],
        
        Text('Tinjauan Sistem', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusMedium,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.info.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.admin_panel_settings, color: AppTheme.info, size: 32),
              ),
              const SizedBox(width: 16),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Akses Administrator Aktif',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Anda dapat memantau seluruh acara, peserta, dan aktivitas pengelola melalui menu navigasi.',
                      style: TextStyle(color: AppTheme.textSecondary, height: 1.5),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildGreetingSection() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: AppTheme.primaryGradient,
        borderRadius: AppTheme.radiusLarge,
        boxShadow: AppTheme.shadowMedium,
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(2),
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            child: CircleAvatar(
              radius: 28,
              backgroundColor: AppTheme.surface,
              child: Text(
                _user!.namaPanggilan.substring(0, 1).toUpperCase(),
                style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.primary),
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Selamat datang,',
                  style: TextStyle(fontSize: 14, color: Colors.white.withValues(alpha: 0.8)),
                ),
                const SizedBox(height: 4),
                Text(
                  _user!.namaPanggilan,
                  style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummarySection(int totalEvents, int activeEvents) {
    return Row(
      children: [
        Expanded(
          child: _buildStatCard('Total Acara', totalEvents.toString(), Icons.event_note, AppTheme.primary),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: _buildStatCard('Acara Aktif', activeEvents.toString(), Icons.event_available, AppTheme.success),
        ),
      ],
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(20.0),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: AppTheme.radiusMedium,
        boxShadow: AppTheme.shadowSoft,
        border: Border.all(color: color.withValues(alpha: 0.1)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: AppTheme.radiusSmall,
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(height: 16),
          Text(
            value,
            style: TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: const TextStyle(fontSize: 14, color: AppTheme.textSecondary, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }
}

