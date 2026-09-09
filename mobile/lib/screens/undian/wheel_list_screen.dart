import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/wheel_model.dart';
import '../../services/wheel_service.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_error_state.dart';
import 'create_wheel_screen.dart';
import 'wheel_session_screen.dart';

class WheelListScreen extends StatefulWidget {
  const WheelListScreen({super.key});

  @override
  State<WheelListScreen> createState() => _WheelListScreenState();
}

class _WheelListScreenState extends State<WheelListScreen> {
  List<WheelSessionModel> _sessions = [];
  bool _isLoading = true;
  bool _isError = false;
  String _errorMessage = '';

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

    final result = await WheelService.getSessions();
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _sessions = result['sessions'] as List<WheelSessionModel>;
        _isLoading = false;
      });
    } else {
      setState(() {
        _isError = true;
        _errorMessage = result['message'];
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Daftar Undian'),
        backgroundColor: AppTheme.surface,
        elevation: 0,
      ),
      body: _buildBody(),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const CreateWheelScreen()),
          );
          if (result == true) {
            _loadData();
          }
        },
        backgroundColor: AppTheme.primary,
        child: const Icon(Icons.add, color: Colors.white),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CustomLoadingIndicator());
    }
    if (_isError) {
      return Center(
        child: AppErrorState(message: _errorMessage, onRetry: _loadData),
      );
    }
    if (_sessions.isEmpty) {
      return const Center(
        child: Text(
          'Belum ada sesi undian',
          style: TextStyle(color: AppTheme.textSecondary),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      color: AppTheme.primary,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _sessions.length,
        itemBuilder: (context, index) {
          final session = _sessions[index];
          final isActive = session.status == 'active';

          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(
              borderRadius: AppTheme.radiusMedium,
              side: BorderSide(
                color: isActive
                    ? AppTheme.primary.withOpacity(0.3)
                    : Colors.black12,
              ),
            ),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 8,
              ),
              title: Text(
                session.title,
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              subtitle: Text(
                'Kandidat: ${session.itemCount} • ${isActive ? 'Aktif' : 'Selesai'}',
                style: TextStyle(
                  color: isActive ? AppTheme.success : AppTheme.textSecondary,
                ),
              ),
              trailing: const Icon(
                Icons.chevron_right,
                color: AppTheme.textSecondary,
              ),
              onTap: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => WheelSessionScreen(sessionId: session.id),
                  ),
                );
                _loadData(); // Refresh if state changed
              },
            ),
          );
        },
      ),
    );
  }
}
