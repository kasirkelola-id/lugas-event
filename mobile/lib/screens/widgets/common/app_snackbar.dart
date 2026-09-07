import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';

enum SnackBarType { success, info, warning, error }

class AppSnackBar {
  static void show(
    BuildContext context, {
    required String message,
    SnackBarType type = SnackBarType.info,
    Duration duration = const Duration(seconds: 3),
  }) {
    if (!context.mounted) return;

    Color backgroundColor;
    IconData icon;

    switch (type) {
      case SnackBarType.success:
        backgroundColor = AppTheme.success;
        icon = Icons.check_circle_outline;
        break;
      case SnackBarType.warning:
        backgroundColor = AppTheme.warning;
        icon = Icons.warning_amber_rounded;
        break;
      case SnackBarType.error:
        backgroundColor = AppTheme.error;
        icon = Icons.error_outline;
        break;
      case SnackBarType.info:
      default:
        backgroundColor = AppTheme.primary;
        icon = Icons.info_outline;
        break;
    }

    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(icon, color: Colors.white, size: 24),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
        backgroundColor: backgroundColor,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: AppTheme.radiusSmall,
        ),
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        duration: duration,
        elevation: 4,
      ),
    );
  }

  static void showSuccess(BuildContext context, String message) =>
      show(context, message: message, type: SnackBarType.success);

  static void showInfo(BuildContext context, String message) =>
      show(context, message: message, type: SnackBarType.info);

  static void showWarning(BuildContext context, String message) =>
      show(context, message: message, type: SnackBarType.warning);

  static void showError(BuildContext context, String message) =>
      show(context, message: message, type: SnackBarType.error);
}
