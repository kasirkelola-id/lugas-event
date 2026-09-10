import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import '../../../models/wheel_model.dart';
import '../../../services/wheel_service.dart';
import '../../undian/wheel_session_screen.dart';
import '../../undian/wheel_list_screen.dart';

class WheelBanner extends StatefulWidget {
  final int currentUserId;

  const WheelBanner({super.key, required this.currentUserId});

  @override
  State<WheelBanner> createState() => _WheelBannerState();
}

class _WheelBannerState extends State<WheelBanner> {
  bool _isLoading = true;
  WheelSessionModel? _latestActiveWheel;
  bool _isWheelSpinning = false;
  WheelResultModel? _latestWheelResult;
  int _activeWheelsCount = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
    });

    try {
      final wheelsResult = await WheelService.getSessions();
      if (!mounted) return;

      if (wheelsResult['success']) {
        final sessions = (wheelsResult['sessions'] as List<dynamic>)
            .cast<WheelSessionModel>();
        final activeWheels = sessions
            .where((s) => s.status == 'active')
            .toList();

        WheelSessionModel? latestActive;
        bool isSpinning = false;
        WheelResultModel? latestResult;

        if (activeWheels.isNotEmpty) {
          latestActive = activeWheels.first;
          final detailsResult = await WheelService.getSessionDetails(
            latestActive.id,
          );
          if (detailsResult['success'] && mounted) {
            final results = (detailsResult['results'] as List<dynamic>)
                .cast<WheelResultModel>();
            if (results.isNotEmpty) {
              latestResult = results.last;
              final endTime = latestResult.startedAt.add(
                Duration(seconds: latestResult.durationSeconds),
              );
              isSpinning = DateTime.now().isBefore(endTime);
            }
          }
        }

        if (mounted) {
          setState(() {
            _activeWheelsCount = activeWheels.length;
            _latestActiveWheel = latestActive;
            _isWheelSpinning = isSpinning;
            _latestWheelResult = latestResult;
            _isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // If loading or no active wheel or error, show nothing.
    // We don't block the dashboard with an error or infinite spinner for the banner.
    if (_isLoading || _latestActiveWheel == null) return const SizedBox();

    final isHost = _latestActiveWheel!.creatorId == widget.currentUserId;
    final title = _latestActiveWheel!.title;
    final itemCount = _latestActiveWheel!.itemCount;

    String label = isHost
        ? 'Undian Anda sedang berlangsung'
        : 'Undian sedang berlangsung';
    String statusText = 'Undian aktif';
    Color statusColor = AppTheme.success;

    if (_isWheelSpinning) {
      statusText = 'Sedang berputar...';
      statusColor = AppTheme.warning;
    } else if (_latestWheelResult != null) {
      statusText = 'Pemenang baru saja dipilih';
      statusColor = AppTheme.primary;
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.surface,
              borderRadius: AppTheme.radiusMedium,
              border: Border.all(color: AppTheme.primary.withOpacity(0.3)),
              boxShadow: AppTheme.shadowSoft,
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.casino,
                    color: AppTheme.primary,
                    size: 28,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        label,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppTheme.textSecondary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(
                            Icons.people,
                            size: 14,
                            color: AppTheme.textSecondary,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            '$itemCount Kandidat',
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppTheme.textSecondary,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: statusColor.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              statusText,
                              style: TextStyle(
                                fontSize: 10,
                                color: statusColor,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 36,
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => WheelSessionScreen(
                                  sessionId: _latestActiveWheel!.id,
                                ),
                              ),
                            ).then((_) => _loadData());
                          },
                          child: const Text(
                            'Lihat Undian',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (_activeWheelsCount > 1)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const WheelListScreen()),
                  ).then((_) => _loadData());
                },
                child: Text(
                  '+${_activeWheelsCount - 1} Undian lainnya',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppTheme.primary,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
