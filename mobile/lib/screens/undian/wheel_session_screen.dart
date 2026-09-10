import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../models/wheel_model.dart';
import '../../services/wheel_service.dart';
import '../../services/chat_service.dart';
import '../../services/auth_service.dart';
import '../widgets/common/custom_loading_indicator.dart';
import '../widgets/common/app_dialog.dart';
import '../widgets/common/app_error_state.dart';
import 'create_wheel_screen.dart';

class WheelSessionScreen extends StatefulWidget {
  final int sessionId;
  const WheelSessionScreen({super.key, required this.sessionId});

  @override
  State<WheelSessionScreen> createState() => _WheelSessionScreenState();
}

class _WheelSessionScreenState extends State<WheelSessionScreen>
    with SingleTickerProviderStateMixin {
  WheelSessionModel? _session;
  List<WheelItemModel> _items = [];
  List<WheelResultModel> _results = [];
  bool _isLoading = true;
  bool _isError = false;
  int _currentUserId = 0;

  late AnimationController _spinController;
  late Animation<double> _spinAnimation;
  double _currentAngle = 0;
  bool _isSpinning = false;

  @override
  void initState() {
    super.initState();
    _spinController = AnimationController(vsync: this);
    _loadData();
    _setupSocket();
  }

  Future<void> _loadData() async {
    final user = await AuthService.getMe();
    if (user['success']) {
      _currentUserId = int.tryParse(user['user'].id.toString()) ?? 0;
    }

    final result = await WheelService.getSessionDetails(widget.sessionId);
    if (!mounted) return;

    if (result['success']) {
      setState(() {
        _session = result['session'];
        _items = result['items'];
        _results = result['results'];
        _isLoading = false;
      });
    } else {
      setState(() {
        _isError = true;
        _isLoading = false;
      });
    }
  }

  void _setupSocket() {
    final socket = ChatService().socket;
    if (socket == null) return;

    socket.emit('join_wheel', {'session_id': widget.sessionId});

    socket.on('wheel_spin_started', _onSpinStarted);
    socket.on('wheel_closed', _onWheelClosed);
  }

  void _onSpinStarted(dynamic data) {
    if (!mounted) return;

    // Convert to integers carefully
    final winnerId =
        int.tryParse(data['winner_item_id']?.toString() ?? '0') ?? 0;
    final durationSeconds =
        int.tryParse(data['duration_seconds']?.toString() ?? '10') ?? 10;

    final activeItems = _items.where((e) => e.isActive).toList();
    final winnerIndex = activeItems.indexWhere((e) => e.id == winnerId);

    if (winnerIndex == -1) return;

    // Calculate target angle
    final itemAngle = 2 * math.pi / activeItems.length;
    // We want the winner to end up pointing at the top (which is -PI/2 in canvas, but let's say our arrow is at the right, 0 radians).
    // Let's assume the arrow is at the right (0 rad).
    // The angle of an item is its center angle.
    final targetCenterAngle = (winnerIndex * itemAngle) + (itemAngle / 2);

    // We want the wheel to rotate such that: final_angle + targetCenterAngle = 0 (modulo 2PI)
    // To spin multiple times, we add 2PI * multiple spins
    final spins = durationSeconds; // roughly 1 spin per second

    final totalRotation =
        (math.pi * 2 * spins) + (math.pi * 2 - targetCenterAngle);

    setState(() {
      _isSpinning = true;
    });

    _spinController.duration = Duration(seconds: durationSeconds);
    _spinAnimation =
        Tween<double>(
          begin: _currentAngle,
          end: _currentAngle + totalRotation,
        ).animate(
          CurvedAnimation(parent: _spinController, curve: Curves.decelerate),
        );

    _spinAnimation.addListener(() {
      setState(() {
        _currentAngle = _spinAnimation.value;
      });
    });

    _spinAnimation.addStatusListener((status) {
      if (status == AnimationStatus.completed) {
        if (!mounted) return;
        setState(() {
          _isSpinning = false;
          _currentAngle = _currentAngle % (math.pi * 2);
        });
        _showWinnerDialog(activeItems[winnerIndex]);
        _loadData(); // reload to get new results list and updated items (if removed)
      }
    });

    _spinController.forward(from: 0);
  }

  void _onWheelClosed(dynamic data) {
    if (!mounted) return;
    _loadData();
  }

  void _showWinnerDialog(WheelItemModel winner) {
    AppDialog.showResult(
      context: context,
      title: 'Pemenang!',
      content: 'Selamat kepada:\n${winner.labelSnapshot}',
      type: DialogType.success,
    );
  }

  Future<void> _spinWheel() async {
    if (_isSpinning) return;

    AppDialog.showLoading(context, message: 'Memulai putaran...');
    final result = await WheelService.spin(widget.sessionId);
    if (!mounted) return;
    Navigator.pop(context); // close loading

    if (!result['success']) {
      AppDialog.showResult(
        context: context,
        title: 'Gagal',
        content: result['message'],
        type: DialogType.error,
      );
    }
    // If successful, the socket event 'wheel_spin_started' will trigger the animation
  }

  Future<void> _closeSession() async {
    final confirm = await AppDialog.showConfirmation(
      context: context,
      title: 'Tutup Undian?',
      content: 'Anda yakin ingin menutup sesi undian ini?',
    );
    if (confirm != true) return;

    if (!mounted) return;
    AppDialog.showLoading(context, message: 'Menutup...');
    final result = await WheelService.closeSession(widget.sessionId);
    if (!mounted) return;
    Navigator.pop(context);

    if (result['success']) {
      // socket event will trigger reload
    } else {
      AppDialog.showResult(
        context: context,
        title: 'Gagal',
        content: result['message'],
        type: DialogType.error,
      );
    }
  }

  @override
  void dispose() {
    _spinController.dispose();
    final socket = ChatService().socket;
    if (socket != null) {
      socket.off('wheel_spin_started');
      socket.off('wheel_closed');
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: Text(_session?.title ?? 'Undian'),
        backgroundColor: AppTheme.surface,
        actions: [
          if (_session != null &&
              _session!.status == 'active' &&
              _session!.creatorId == _currentUserId &&
              !_isSpinning)
            IconButton(
              icon: const Icon(Icons.close, color: AppTheme.error),
              tooltip: 'Tutup Sesi',
              onPressed: _closeSession,
            ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) return const Center(child: CustomLoadingIndicator());
    if (_isError || _session == null) {
      return Center(
        child: AppErrorState(
          message: 'Gagal memuat undian',
          onRetry: _loadData,
        ),
      );
    }

    final activeItems = _items.where((e) => e.isActive).toList();
    final isHost = _session!.creatorId == _currentUserId;
    final isActive = _session!.status == 'active';

    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          color: AppTheme.surface,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Status: ${isActive ? 'Aktif' : 'Selesai'}',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: isActive ? AppTheme.success : AppTheme.textSecondary,
                ),
              ),
              Text(
                'Kandidat Aktif: ${activeItems.length}',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ),

        Expanded(
          flex: 3,
          child: Center(
            child: activeItems.isEmpty
                ? const Text('Kandidat sudah habis')
                : Padding(
                    padding: const EdgeInsets.all(32.0),
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        Transform.rotate(
                          angle: _currentAngle,
                          child: CustomPaint(
                            size: const Size(300, 300),
                            painter: WheelPainter(items: activeItems),
                          ),
                        ),
                        // Indicator Arrow at the right side (0 radians)
                        Positioned(
                          right: -10,
                          child: Icon(
                            Icons.play_arrow,
                            color: AppTheme.primary,
                            size: 40,
                          ),
                        ),
                        // Center dot
                        Container(
                          width: 20,
                          height: 20,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(color: Colors.black26, blurRadius: 4),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
        ),

        if (isActive && isHost && activeItems.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16.0),
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(
                  horizontal: 40,
                  vertical: 16,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
              onPressed: _isSpinning ? null : _spinWheel,
              child: Text(
                activeItems.length == 1 ? 'PILIH OTOMATIS' : 'PUTAR SEKARANG',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
            ),
          ),

        if (!isActive && isHost)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 16.0),
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.primary,
                side: const BorderSide(color: AppTheme.primary, width: 2),
                padding: const EdgeInsets.symmetric(
                  horizontal: 30,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
              onPressed: () {
                Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(builder: (_) => const CreateWheelScreen()),
                );
              },
              icon: const Icon(Icons.refresh),
              label: const Text(
                'BUAT UNDIAN LAGI',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ),

        if (!isActive && _results.isNotEmpty)
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Text(
              'Pemenang Terakhir: ${_results.last.resultLabelSnapshot}',
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: AppTheme.success,
              ),
              textAlign: TextAlign.center,
            ),
          ),

        Expanded(
          flex: 2,
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 10,
                  offset: Offset(0, -2),
                ),
              ],
            ),
            child: Column(
              children: [
                const Padding(
                  padding: EdgeInsets.all(16.0),
                  child: Text(
                    'Riwayat Putaran',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
                const Divider(height: 1),
                Expanded(
                  child: _results.isEmpty
                      ? const Center(child: Text('Belum ada putaran'))
                      : ListView.builder(
                          itemCount: _results.length,
                          itemBuilder: (context, index) {
                            final res = _results.reversed.toList()[index];
                            return ListTile(
                              leading: CircleAvatar(
                                backgroundColor: AppTheme.primary.withOpacity(
                                  0.1,
                                ),
                                child: Text(
                                  '${res.spinSequence}',
                                  style: const TextStyle(
                                    color: AppTheme.primary,
                                  ),
                                ),
                              ),
                              title: Text(
                                res.resultLabelSnapshot,
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              subtitle: Text(
                                'Diputar: ${res.startedAt.toString().split('.')[0]}',
                              ),
                            );
                          },
                        ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class WheelPainter extends CustomPainter {
  final List<WheelItemModel> items;

  WheelPainter({required this.items});

  @override
  void paint(Canvas canvas, Size size) {
    if (items.isEmpty) return;

    final double radius = math.min(size.width / 2, size.height / 2);
    final center = Offset(size.width / 2, size.height / 2);
    final sweepAngle = 2 * math.pi / items.length;

    final paint = Paint()..style = PaintingStyle.fill;

    // Palette
    final colors = [
      const Color(0xFFF44336), // Red
      const Color(0xFF2196F3), // Blue
      const Color(0xFF4CAF50), // Green
      const Color(0xFFFF9800), // Orange
      const Color(0xFF9C27B0), // Purple
      const Color(0xFF009688), // Teal
    ];

    for (int i = 0; i < items.length; i++) {
      paint.color = colors[i % colors.length];
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius),
        i * sweepAngle,
        sweepAngle,
        true,
        paint,
      );

      // Draw text
      canvas.save();
      canvas.translate(center.dx, center.dy);
      canvas.rotate((i * sweepAngle) + (sweepAngle / 2));

      final textPainter = TextPainter(
        text: TextSpan(
          text: items[i].labelSnapshot.length > 15
              ? '${items[i].labelSnapshot.substring(0, 15)}...'
              : items[i].labelSnapshot,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 12,
          ),
        ),
        textDirection: TextDirection.ltr,
      );
      textPainter.layout();

      // Position text slightly inwards from the edge
      textPainter.paint(canvas, Offset(radius * 0.4, -textPainter.height / 2));

      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant WheelPainter oldDelegate) {
    return oldDelegate.items != items;
  }
}
