import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/auth_service.dart';
import '../../services/event_service.dart';
import '../../models/user_model.dart';
import '../../models/event_model.dart';
import '../widgets/app_drawer.dart';
import '../widgets/common/custom_button.dart';
import '../widgets/common/empty_state.dart';
import '../pengelola/create_event_screen.dart';
import '../pengelola/event_detail_screen.dart';

class AdminAcaraScreen extends StatefulWidget {
  const AdminAcaraScreen({super.key});

  @override
  State<AdminAcaraScreen> createState() => _AdminAcaraScreenState();
}

class _AdminAcaraScreenState extends State<AdminAcaraScreen> {
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
        title: const Text('Kelola Seluruh Acara'),
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateEventScreen()),
          );
          if (result == true) {
            _loadData();
          }
        },
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Buat Acara Baru', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
        backgroundColor: AppTheme.primary,
        elevation: 4,
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading && _events.isEmpty) {
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

    if (_events.isEmpty) {
      return EmptyStateWidget(
        icon: Icons.event_busy,
        title: 'Belum Ada Acara',
        subtitle: 'Belum ada acara di dalam sistem.',
        buttonText: 'Buat Acara',
        onButtonPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateEventScreen()),
          );
          if (result == true) {
            _loadData();
          }
        },
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.only(top: 16, left: 20, right: 20, bottom: 80),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: _events.length,
      itemBuilder: (context, index) {
        final event = _events[index];
        return _buildEventCard(event);
      },
    );
  }

  Widget _buildEventCard(EventModel event) {
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
        onTap: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => EventDetailScreen(eventId: event.id)),
          );
          if (result == true) {
            _loadData();
          }
        },
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      event.namaAcara,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: event.isActive ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.textSecondary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      event.isActive ? 'AKTIF' : 'SELESAI',
                      style: TextStyle(
                        color: event.isActive ? AppTheme.success : AppTheme.textSecondary,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  const Icon(Icons.calendar_month, size: 16, color: AppTheme.textSecondary),
                  const SizedBox(width: 8),
                  Text(
                    event.tanggalAcara,
                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 14),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.people_alt_outlined, size: 16, color: AppTheme.textSecondary),
                  const SizedBox(width: 8),
                  Text(
                    'Jumlah Hadir: ${event.jumlahHadir ?? 0}',
                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 14),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

