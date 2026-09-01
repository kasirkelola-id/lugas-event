import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import 'custom_button.dart';

class FeedbackDialogs {
  static Future<bool?> showConfirmation({
    required BuildContext context,
    required String title,
    required String content,
    String confirmText = 'Konfirmasi',
    String cancelText = 'Batal',
    bool isDestructive = false,
  }) {
    return showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        content: Text(content),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(cancelText, style: const TextStyle(color: AppTheme.textSecondary)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: isDestructive ? AppTheme.error : AppTheme.primary,
            ),
            child: Text(confirmText),
          ),
        ],
      ),
    );
  }

  static void showSnackbar(BuildContext context, String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? AppTheme.error : AppTheme.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: AppTheme.radiusMedium),
        margin: const EdgeInsets.all(16),
      ),
    );
  }
}
