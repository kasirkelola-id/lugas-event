import 'package:flutter/material.dart';
import '../../services/auth_service.dart';
import '../../storage/auth_storage.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/common/custom_button.dart';
import 'login_screen.dart';

class PinScreen extends StatefulWidget {
  const PinScreen({super.key});

  @override
  State<PinScreen> createState() => _PinScreenState();
}

class _PinScreenState extends State<PinScreen> with SingleTickerProviderStateMixin {
  final List<TextEditingController> _controllers = List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _focusNodes = List.generate(6, (_) => FocusNode());
  final List<bool> _obscured = List.generate(6, (_) => true);
  bool _isLoading = false;
  String? _errorMessage;

  int _currentIndex = 0;
  late AnimationController _cursorController;

  @override
  void initState() {
    super.initState();
    _cursorController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _cursorController.dispose();
    for (var controller in _controllers) {
      controller.dispose();
    }
    for (var node in _focusNodes) {
      node.dispose();
    }
    super.dispose();
  }

  void _onNumPressed(String num) {
    if (_currentIndex < 6) {
      final index = _currentIndex;
      setState(() {
        _controllers[index].text = num;
        _obscured[index] = false; // Tampilkan angka sebentar
        _currentIndex++;
      });
      
      // Ubah kembali menjadi titik setelah jeda
      Future.delayed(const Duration(milliseconds: 600), () {
        if (mounted) {
          setState(() {
            _obscured[index] = true;
          });
        }
      });

      if (_currentIndex == 6) {
        _verifyPin();
      }
    }
  }

  void _onBackspacePressed() {
    if (_currentIndex > 0) {
      setState(() {
        _currentIndex--;
        _controllers[_currentIndex].text = '';
      });
    }
  }

  void _verifyPin() async {
    final pin = _controllers.map((c) => c.text).join();
    if (pin.length < 6) {
      setState(() {
        _errorMessage = 'Harap masukkan 6 digit PIN';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await AuthService.verifyPin(pin);

    if (!mounted) return;

    if (result['success']) {
      final data = result['data'];
      await AuthStorage.saveTenant(
        data['karang_taruna_id'], 
        data['nama_organisasi'],
        logoUrl: data['logo_url']
      );
      
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = result['message'];
        
        // Reset PIN fields on error
        for (var i = 0; i < 6; i++) {
          _controllers[i].text = '';
          _obscured[i] = true;
        }
        _currentIndex = 0;
      });
    }
  }

  Widget _buildPinField(int index) {
    final text = _controllers[index].text;
    final isActive = index == _currentIndex;
    final isFilled = text.isNotEmpty;
    
    return AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      curve: Curves.easeOut,
      width: 44,
      height: 54,
      decoration: BoxDecoration(
        color: isActive ? Colors.white : Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isActive ? AppTheme.primary : (isFilled ? Colors.grey.shade400 : Colors.grey.shade200),
          width: isActive ? 2.0 : 1.0,
        ),
        boxShadow: isActive ? [
          BoxShadow(
            color: AppTheme.primary.withValues(alpha: 0.1),
            blurRadius: 8,
            offset: const Offset(0, 2),
          )
        ] : null,
      ),
      child: Center(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 150),
          transitionBuilder: (Widget child, Animation<double> animation) {
            return FadeTransition(
              opacity: animation,
              child: ScaleTransition(
                scale: Tween<double>(begin: 0.8, end: 1.0).animate(
                  CurvedAnimation(parent: animation, curve: Curves.easeOut),
                ),
                child: child,
              ),
            );
          },
          child: text.isEmpty 
              ? (isActive 
                  ? FadeTransition(
                      key: const ValueKey('cursor'),
                      opacity: _cursorController,
                      child: Container(
                        width: 2, 
                        height: 26, 
                        color: AppTheme.primary.withValues(alpha: 0.6)
                      ),
                    ) 
                  : const SizedBox(key: ValueKey('empty')))
              : Text(
                  _obscured[index] ? '•' : text,
                  key: ValueKey('digit_${index}_${_obscured[index]}'),
                  style: TextStyle(
                    fontSize: _obscured[index] ? 40 : 28, 
                    fontWeight: FontWeight.bold, 
                    color: AppTheme.textPrimary,
                    height: _obscured[index] ? 1.2 : null,
                  ),
                ),
        ),
      ),
    );
  }

  Widget _buildNumPad() {
    return Column(
      children: [
        for (var i = 0; i < 3; i++) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              for (var j = 1; j <= 3; j++)
                _buildNumButton((i * 3 + j).toString()),
            ],
          ),
          const SizedBox(height: 12),
        ],
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            _buildIconButton(Icons.backspace_outlined, _onBackspacePressed, color: AppTheme.error),
            _buildNumButton('0'),
            _buildIconButton(Icons.check_circle_outline, _verifyPin, color: AppTheme.primary),
          ],
        ),
      ],
    );
  }

  Widget _buildNumButton(String num) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _isLoading ? null : () => _onNumPressed(num),
        borderRadius: BorderRadius.circular(40),
        splashColor: AppTheme.primary.withValues(alpha: 0.2),
        highlightColor: AppTheme.primary.withValues(alpha: 0.1),
        child: Ink(
          width: 65,
          height: 65,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.grey.shade100,
          ),
          child: Center(
            child: Text(
              num,
              style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w600, color: AppTheme.textPrimary),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildIconButton(IconData icon, VoidCallback onTap, {Color color = AppTheme.textSecondary}) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _isLoading ? null : onTap,
        borderRadius: BorderRadius.circular(40),
        splashColor: color.withValues(alpha: 0.2),
        highlightColor: color.withValues(alpha: 0.1),
        child: Ink(
          width: 65,
          height: 65,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.grey.shade100,
          ),
          child: Center(
            child: Icon(icon, size: 28, color: color),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: AppTheme.radiusLarge,
                side: BorderSide(color: Colors.grey.shade200),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 32.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [AppTheme.primary, AppTheme.info],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.primary.withValues(alpha: 0.3),
                            blurRadius: 16,
                            offset: const Offset(0, 8),
                          )
                        ]
                      ),
                      child: const Icon(
                        Icons.vpn_key_rounded,
                        size: 56,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 28),
                    const Text(
                      'Masukkan PIN',
                      style: TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'Ketik 6-digit PIN Karang Taruna Anda\nuntuk melanjutkan',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 15,
                        height: 1.5,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 40),
                    if (_errorMessage != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 24),
                        decoration: BoxDecoration(
                          color: AppTheme.error.withValues(alpha: 0.1),
                          borderRadius: AppTheme.radiusSmall,
                          border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline, color: AppTheme.error, size: 20),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: const TextStyle(color: AppTheme.error, fontSize: 14),
                              ),
                            ),
                          ],
                        ),
                      ),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                      children: List.generate(6, (index) => _buildPinField(index)),
                    ),
                    const SizedBox(height: 40),
                    if (_isLoading)
                      const CircularProgressIndicator(color: AppTheme.primary)
                    else
                      _buildNumPad(),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
