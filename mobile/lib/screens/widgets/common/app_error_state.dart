import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import 'custom_button.dart';

class AppErrorState extends StatelessWidget {
  final String title;
  final String message;
  final String? retryText;
  final VoidCallback? onRetry;
  final IconData icon;

  const AppErrorState({
    Key? key,
    this.title = 'Terjadi Kesalahan',
    required this.message,
    this.retryText = 'Coba Lagi',
    this.onRetry,
    this.icon = Icons.error_outline,
  }) : super(key: key);

  factory AppErrorState.network({VoidCallback? onRetry}) {
    return AppErrorState(
      title: 'Tidak Ada Koneksi',
      message: 'Periksa koneksi internet Anda lalu coba lagi.',
      icon: Icons.wifi_off_rounded,
      onRetry: onRetry,
    );
  }

  factory AppErrorState.server({VoidCallback? onRetry}) {
    return AppErrorState(
      title: 'Gangguan Server',
      message: 'Terjadi gangguan pada server. Silakan coba beberapa saat lagi.',
      icon: Icons.cloud_off_rounded,
      onRetry: onRetry,
    );
  }

  factory AppErrorState.permission({VoidCallback? onRetry}) {
    return AppErrorState(
      title: 'Akses Ditolak',
      message: 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
      icon: Icons.lock_outline_rounded,
      retryText: 'Kembali',
      onRetry: onRetry,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 48),
      width: double.infinity,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppTheme.error.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 72, color: AppTheme.error),
          ),
          const SizedBox(height: 24),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 14,
              color: AppTheme.textSecondary,
              height: 1.5,
            ),
          ),
          if (onRetry != null) ...[
            const SizedBox(height: 32),
            CustomButton(
              text: retryText ?? 'Coba Lagi',
              onPressed: onRetry!,
              type: ButtonType.outline,
            ),
          ],
        ],
      ),
    );
  }
}
