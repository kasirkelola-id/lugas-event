import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/attendance_model.dart';
import '../../services/attendance_service.dart';
import '../../services/auth_service.dart';
import '../../models/user_model.dart';
import '../auth/login_screen.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/empty_state.dart';
import 'package:intl/intl.dart';
import 'package:intl/date_symbol_data_local.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  State<AttendanceHistoryScreen> createState() => _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  List<AttendanceModel> _history = [];
  UserModel? _user;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    initializeDateFormatting('id_ID', null).then((_) {
      _loadHistory();
    });
  }

  Future<void> _loadHistory() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final userResult = await AuthService.getMe();
    final result = await AttendanceService.getMyHistory();
    if (!mounted) return;

    if (userResult['success']) {
      _user = userResult['user'] as UserModel;
    }

    if (result['success']) {
      setState(() {
        _history = result['history'] as List<AttendanceModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = (result['message'].toString().toLowerCase().contains('sesi') || 
                         result['message'].toString().toLowerCase().contains('berakhir'))
            ? result['message']
            : 'Riwayat absensi gagal dimuat.';
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
      appBar: AppBar(
        title: const Text('Riwayat Absensi'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      drawer: _user != null ? AppDrawer(user: _user!) : null,
      backgroundColor: AppTheme.background,
      body: RefreshIndicator(
        onRefresh: _loadHistory,
        color: AppTheme.primary,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _history.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }

    if (_errorMessage != null && _history.isEmpty) {
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
              onPressed: _loadHistory,
              isFullWidth: false,
              icon: Icons.refresh,
            ),
          ],
        ),
      );
    }

    if (_history.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.history,
        title: 'Belum Ada Riwayat',
        subtitle: 'Anda belum memiliki riwayat absensi.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.only(top: 16, left: 20, right: 20, bottom: 80),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _history.length,
      itemBuilder: (context, index) {
        final item = _history[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusMedium,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: Padding(
            padding: const EdgeInsets.all(20.0),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.check_circle, color: AppTheme.primary),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              item.namaAcara,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: item.isActive ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.textSecondary.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              item.isActive ? 'AKTIF' : 'SELESAI',
                              style: TextStyle(
                                color: item.isActive ? AppTheme.success : AppTheme.textSecondary,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          const Icon(Icons.access_time, size: 14, color: AppTheme.textSecondary),
                          const SizedBox(width: 4),
                          Text(_formatTime(item.waktuAbsen), style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.calendar_month, size: 14, color: AppTheme.textSecondary),
                          const SizedBox(width: 4),
                          Text(_formatDate(item.tanggalAcara), style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  String _formatTime(String timeString) {
    try {
      if (timeString.isEmpty) return '-';
      final dateTime = DateTime.parse('1970-01-01 $timeString');
      return '${DateFormat('HH.mm', 'id_ID').format(dateTime)} WIB';
    } catch (e) {
      return timeString;
    }
  }

  String _formatDate(String dateString) {
    try {
      if (dateString.isEmpty) return '-';
      final dateTime = DateTime.parse(dateString);
      return DateFormat('EEEE, dd MMMM yyyy', 'id_ID').format(dateTime);
    } catch (e) {
      return dateString;
    }
  }
}
