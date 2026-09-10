import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/wheel_model.dart';
import '../../services/wheel_service.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_error_state.dart';
import '../../models/user_model.dart';
import '../../services/auth_service.dart';
import '../widgets/app_drawer.dart';
import 'create_wheel_screen.dart';
import 'wheel_session_screen.dart';

class WheelListScreen extends StatefulWidget {
  final bool fromDrawer;
  const WheelListScreen({super.key, this.fromDrawer = false});

  @override
  State<WheelListScreen> createState() => _WheelListScreenState();
}

class _WheelListScreenState extends State<WheelListScreen> {
  List<WheelSessionModel> _sessions = [];
  bool _isLoading = true;
  bool _isError = false;
  String _errorMessage = '';
  UserModel? _currentUser;

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
    final userResult = await AuthService.getMe();

    if (!mounted) return;

    if (userResult['success']) {
      _currentUser = userResult['user'];
    }

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
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        drawer: (widget.fromDrawer && _currentUser != null) ? AppDrawer(user: _currentUser!) : null,
        backgroundColor: AppTheme.background,
        body: RefreshIndicator(
          onRefresh: _loadData,
          color: AppTheme.primary,
          child: NestedScrollView(
            headerSliverBuilder: (context, innerBoxIsScrolled) {
              return [
                SliverAppBar(
                  title: const Text(
                    'Daftar Undian',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  backgroundColor: AppTheme.primary,
                  iconTheme: const IconThemeData(color: Colors.white),
                  pinned: true,
                  floating: true,
                  elevation: 0,
                  flexibleSpace: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          AppTheme.primary,
                          AppTheme.primary.withOpacity(0.8),
                        ],
                      ),
                    ),
                  ),
                  bottom: TabBar(
                    indicatorColor: Colors.white,
                    indicatorWeight: 3,
                    labelColor: Colors.white,
                    unselectedLabelColor: Colors.white.withOpacity(0.6),
                    labelStyle: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 15,
                    ),
                    tabs: const [
                      Tab(text: 'Aktif'),
                      Tab(text: 'Selesai'),
                    ],
                  ),
                ),
              ];
            },
            body: TabBarView(
              children: [
                _buildList(true),
                _buildList(false),
              ],
            ),
          ),
        ),
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
      ),
    );
  }

  Widget _buildList(bool active) {
    if (_isLoading) {
      return const Center(child: CustomLoadingIndicator());
    }
    if (_isError) {
      return Center(
        child: AppErrorState(message: _errorMessage, onRetry: _loadData),
      );
    }

    final filteredSessions = _sessions.where((s) => (s.status == 'active') == active).toList();

    if (filteredSessions.isEmpty) {
      return Center(
        child: Text(
          active ? 'Belum ada sesi undian aktif' : 'Belum ada sesi undian selesai',
          style: const TextStyle(color: AppTheme.textSecondary),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: filteredSessions.length,
      itemBuilder: (context, index) {
        final session = filteredSessions[index];

        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: AppTheme.radiusLarge,
            boxShadow: AppTheme.shadowSoft,
            border: Border.all(
              color: active ? AppTheme.primary.withOpacity(0.3) : Colors.grey.shade200,
              width: active ? 2 : 1,
            ),
          ),
          child: InkWell(
            borderRadius: AppTheme.radiusLarge,
            onTap: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => WheelSessionScreen(sessionId: session.id),
                ),
              );
              _loadData(); // Refresh if state changed
            },
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: active
                          ? AppTheme.primary.withOpacity(0.1)
                          : Colors.grey.shade100,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.casino,
                      color: active ? AppTheme.primary : AppTheme.textSecondary,
                      size: 28,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          session.title,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.people,
                              size: 14,
                              color: AppTheme.textSecondary,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${session.itemCount} Kandidat',
                              style: const TextStyle(
                                color: AppTheme.textSecondary,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: active
                                ? AppTheme.success.withOpacity(0.1)
                                : AppTheme.textSecondary.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            active ? 'Aktif' : 'Selesai',
                            style: TextStyle(
                              color: active
                                  ? AppTheme.success
                                  : AppTheme.textSecondary,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(
                    Icons.chevron_right,
                    color: Colors.grey,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
