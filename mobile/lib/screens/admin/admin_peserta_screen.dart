import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/empty_state.dart';
import '../pengelola/attendance_list_screen.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

class AdminPesertaScreen extends StatefulWidget {
  const AdminPesertaScreen({super.key});

  @override
  State<AdminPesertaScreen> createState() => _AdminPesertaScreenState();
}

class _AdminPesertaScreenState extends State<AdminPesertaScreen> {
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
        title: const Text('Peserta & Absensi'),
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
    if (_isLoading && _events.isEmpty) {
      return const Center(child: CustomLoadingIndicator(color: AppTheme.primary));
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

    if (_events.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.event_busy,
        title: 'Belum Ada Acara',
        subtitle: 'Belum ada acara di sistem untuk dilihat daftar hadirnya.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _events.length + 2,
      itemBuilder: (context, index) {
        if (index == 0) {
          int totalKehadiran = _events.fold(0, (sum, event) => sum + (event.jumlahHadir ?? 0));
          return Container(
            margin: const EdgeInsets.only(bottom: 24),
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: AppTheme.primaryGradient,
              borderRadius: AppTheme.radiusLarge,
              boxShadow: AppTheme.shadowMedium,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                Column(
                  children: [
                    const Text('Total Acara', style: TextStyle(color: Colors.white70, fontSize: 14)),
                    const SizedBox(height: 4),
                    Text('${_events.length}', style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold)),
                  ],
                ),
                Container(width: 1, height: 40, color: Colors.white24),
                Column(
                  children: [
                    const Text('Total Kehadiran', style: TextStyle(color: Colors.white70, fontSize: 14)),
                    const SizedBox(height: 4),
                    Text('$totalKehadiran', style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold)),
                  ],
                ),
              ],
            ),
          );
        }

        if (index == 1) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Text(
              'Pilih Acara untuk Lihat Kehadiran',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
            ),
          );
        }

        final event = _events[index - 2];
        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusMedium,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: InkWell(
            borderRadius: AppTheme.radiusMedium,
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => AttendanceListScreen(event: event)),
              );
            },
            child: Padding(
              padding: const EdgeInsets.all(20.0),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: event.isActive ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.textSecondary.withValues(alpha: 0.1),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.people_alt, color: event.isActive ? AppTheme.success : AppTheme.textSecondary),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          event.namaAcara,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.calendar_month, size: 14, color: AppTheme.textSecondary),
                            const SizedBox(width: 4),
                            Text(event.tanggalAcara, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: event.isActive ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.textSecondary.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text(
                                event.isActive ? 'Aktif' : 'Selesai',
                                style: TextStyle(color: event.isActive ? AppTheme.success : AppTheme.textSecondary, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        if (event.jumlahHadir != null) ...[
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              const Icon(Icons.people, size: 14, color: AppTheme.primary),
                              const SizedBox(width: 4),
                              Text('${event.jumlahHadir} hadir', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 13)),
                            ],
                          ),
                        ]
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

