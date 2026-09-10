import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/dashboard_service.dart';
import '../../models/user_model.dart';
import '../../models/dashboard_summary_model.dart';
import 'package:mobile/screens/auth/login_screen.dart';
import 'attendance_geofence_screen.dart';
import '../kas/kas_screen.dart';
import '../shared/user_pengumuman_screen.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/animations/fade_in_slide.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_error_state.dart';
import '../../services/chat_service.dart';
import '../widgets/common/community_activity_section.dart';

class AnggotaHomeScreen extends StatefulWidget {
  const AnggotaHomeScreen({super.key});

  @override
  State<AnggotaHomeScreen> createState() => _AnggotaHomeScreenState();
}

class _AnggotaHomeScreenState extends State<AnggotaHomeScreen> {
  UserModel? _user;
  DashboardSummary? _summary;
  DateTime _lastRefreshTime = DateTime.now();

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
        if (userResult['message']?.toString().toLowerCase().contains('sesi') ?? false) {
          _logout();
          return;
        }
        setState(() {
          _isLoading = false;
          _isError = true;
          _errorMessage = userResult['message']?.toString() ?? 'Gagal memuat profil.';
        });
        return;
      }

      // Initialize global socket connection once authenticated
      ChatService().initWebSocket();

      final summaryResult = await DashboardService.getSummary();
      if (!mounted) return;

      if (!summaryResult['success']) {
        setState(() {
          _isLoading = false;
          _isError = true;
          _errorMessage = summaryResult['message']?.toString() ?? 'Gagal memuat dashboard.';
        });
        return;
      }

      setState(() {
        _user = userResult['user'] as UserModel;
        _summary = summaryResult['summary'] as DashboardSummary;
        _lastRefreshTime = DateTime.now();
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
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CustomLoadingIndicator(color: AppTheme.primary),
      );
    }
    if (_isError || _user == null || _summary == null) {
      return Center(
        child: AppErrorState(message: _errorMessage, onRetry: _loadData),
      );
    }

    return Stack(
      children: [
        // Gradient Header Background
        Container(
          height: 240,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppTheme.primary, AppTheme.primary.withOpacity(0.8)],
            ),
            borderRadius: const BorderRadius.vertical(
              bottom: Radius.circular(32),
            ),
          ),
        ),
        // Content
        RefreshIndicator(
          onRefresh: _loadData,
          color: AppTheme.primary,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _buildHeaderContent(),
                FadeInSlide(
                  delay: 0.1,
                  child: CommunityActivitySection(
                    communityActivity: _summary?.communityActivity,
                  ),
                ),
                FadeInSlide(delay: 0.1, child: _buildKasInfo()),
                FadeInSlide(delay: 0.2, child: _buildUpcomingEvent()),
                FadeInSlide(delay: 0.3, child: _buildMyActiveLoan()),
                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
      ],
    );
  }

  String _formatRole(String role) {
    return role.substring(0, 1).toUpperCase() + role.substring(1);
  }

  Widget _buildKasInfo() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Card(
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        elevation: 2,
        shadowColor: AppTheme.primary.withOpacity(0.1),
        child: InkWell(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => KasScreen(user: _user)),
            );
          },
          borderRadius: AppTheme.radiusMedium,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: AppTheme.success.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(
                            Icons.account_balance_wallet,
                            color: AppTheme.success,
                            size: 20,
                          ),
                        ),
                        const SizedBox(width: 12),
                        const Text(
                          'Kas Terkini',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                      ],
                    ),
                    const Icon(
                      Icons.chevron_right,
                      color: AppTheme.textSecondary,
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  'Rp ${_summary!.kasBalance.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 24,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 16),
                const Divider(height: 1, color: Colors.black12),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              color: Colors.green.withOpacity(0.1),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.arrow_downward,
                              color: Colors.green,
                              size: 12,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Pemasukan',
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: AppTheme.textSecondary,
                                  ),
                                ),
                                Text(
                                  'Rp ${_summary!.kasPemasukan.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.green,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(width: 1, height: 30, color: Colors.black12),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              color: Colors.red.withOpacity(0.1),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.arrow_upward,
                              color: Colors.red,
                              size: 12,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Pengeluaran',
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: AppTheme.textSecondary,
                                  ),
                                ),
                                Text(
                                  'Rp ${_summary!.kasPengeluaran.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.red,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeaderContent() {
    return Padding(
      padding: const EdgeInsets.only(left: 24, right: 24, top: 24, bottom: 20),
      child: Row(
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: Colors.white24,
            child: Text(
              _user!.namaPanggilan.isNotEmpty
                  ? _user!.namaPanggilan.substring(0, 1).toUpperCase()
                  : 'U',
              style: const TextStyle(
                fontSize: 28,
                color: Colors.white,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Halo, ${_user!.namaPanggilan} 👋',
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    _formatRole(_user!.roleLevel),
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                  ),
                ),
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
              const Text(
                'Acara Terdekat',
                style: TextStyle(color: Colors.white70, fontSize: 12),
              ),
              const SizedBox(height: 8),
              Text(
                event.title,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${event.date} - ${event.time}',
                style: const TextStyle(color: Colors.white),
              ),
              const SizedBox(height: 12),
              if (event.isOngoing)
                ElevatedButton.icon(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const AttendanceGeofenceScreen(),
                    ),
                  ),
                  icon: const Icon(Icons.location_on),
                  label: const Text('Absen Lokasi'),
                ),
            ],
          ),
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
          title: Text(
            loan.inventoryName,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          subtitle: Text('Jumlah: ${loan.quantity} | Status: ${loan.status}'),
        ),
      ),
    );
  }
}
