import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/dashboard_service.dart';
import '../../models/user_model.dart';
import '../../models/dashboard_summary_model.dart';
import '../auth/login_screen.dart';
import 'attendance_geofence_screen.dart';
import '../shared/user_pengumuman_screen.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';

class AnggotaHomeScreen extends StatefulWidget {
  const AnggotaHomeScreen({super.key});

  @override
  State<AnggotaHomeScreen> createState() => _AnggotaHomeScreenState();
}

class _AnggotaHomeScreenState extends State<AnggotaHomeScreen> {
  UserModel? _user;
  DashboardSummary? _summary;
  
  bool _isLoading = true;
  bool _isError = false;
  String _errorMessage = 'Koneksi bermasalah.';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _isError = false;
    });

    try {
      final userResult = await AuthService.getMe();
      if (!mounted) return;

      if (!userResult['success']) {
        if (userResult['message'].toString().toLowerCase().contains('sesi')) {
          _logout();
          return;
        }
        setState(() {
          _isError = true;
          _errorMessage = userResult['message'];
        });
        return;
      }

      final summaryResult = await DashboardService.getSummary();
      if (!mounted) return;

      if (!summaryResult['success']) {
        setState(() {
          _isError = true;
          _errorMessage = summaryResult['message'];
        });
        return;
      }

      setState(() {
        _user = userResult['user'] as UserModel;
        _summary = summaryResult['summary'] as DashboardSummary;
        _isLoading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isError = true;
          _errorMessage = 'Gagal memuat dashboard.';
        });
      }
    }
  }

  void _logout() async {
    await AuthService.logout();
    if (!mounted) return;
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Beranda Anggota'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }
    if (_isError || _user == null || _summary == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.error),
            const SizedBox(height: 16),
            Text(_errorMessage, style: const TextStyle(color: AppTheme.textSecondary)),
            const SizedBox(height: 16),
            CustomButton(text: 'Coba Lagi', onPressed: _loadData, isFullWidth: false),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _buildHeaderSection(),
          const SizedBox(height: 16),
          _buildUpcomingEvent(),
          _buildLatestAnnouncement(),
          _buildActiveVoting(),
          _buildMyActiveLoan(),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildHeaderSection() {
    return Container(
      padding: const EdgeInsets.only(left: 24, right: 24, top: 16, bottom: 24),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(24)),
        boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, 4))],
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: AppTheme.primary.withOpacity(0.1),
            child: Text(
              _user!.namaPanggilan.isNotEmpty ? _user!.namaPanggilan.substring(0, 1).toUpperCase() : 'U',
              style: const TextStyle(fontSize: 28, color: AppTheme.primary, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Halo, ${_user!.namaPanggilan} 👋',
                  style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                const Text('Role: Anggota', style: TextStyle(color: AppTheme.textSecondary)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUpcomingEvent() {
    if (_summary!.upcomingEvent == null) return const SizedBox();
    final event = _summary!.upcomingEvent!;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Card(
        color: AppTheme.primary,
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusLarge),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Acara Terdekat', style: TextStyle(color: Colors.white70, fontSize: 12)),
              const SizedBox(height: 8),
              Text(event.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Text('${event.date} - ${event.time}', style: const TextStyle(color: Colors.white)),
              const SizedBox(height: 12),
              ElevatedButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AttendanceGeofenceScreen())),
                icon: const Icon(Icons.location_on),
                label: const Text('Absen Lokasi'),
              )
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLatestAnnouncement() {
    if (_summary!.latestAnnouncement == null) return const SizedBox();
    final ann = _summary!.latestAnnouncement!;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Card(
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        child: ListTile(
          leading: const Icon(Icons.campaign, color: AppTheme.warning),
          title: Text(ann.title, style: const TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text(ann.preview, maxLines: 2, overflow: TextOverflow.ellipsis),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const UserPengumumanScreen())),
        ),
      ),
    );
  }

  Widget _buildActiveVoting() {
    if (_summary!.activeVoting == null) return const SizedBox();
    final vote = _summary!.activeVoting!;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Card(
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        child: ListTile(
          leading: const Icon(Icons.how_to_vote, color: AppTheme.primary),
          title: Text(vote.title, style: const TextStyle(fontWeight: FontWeight.bold)),
          subtitle: const Text('Voting Aktif'),
        ),
      ),
    );
  }

  Widget _buildMyActiveLoan() {
    if (_summary!.myActiveLoan == null) return const SizedBox();
    final loan = _summary!.myActiveLoan!;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Card(
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        child: ListTile(
          leading: const Icon(Icons.inventory, color: AppTheme.success),
          title: Text(loan.inventoryName, style: const TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text('Jumlah: ${loan.quantity} | Status: ${loan.status}'),
        ),
      ),
    );
  }
}
