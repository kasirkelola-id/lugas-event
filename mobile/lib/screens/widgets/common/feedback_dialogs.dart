import 'package:flutter/material.dart';
import 'app_dialog.dart';
import 'app_snackbar.dart';

class FeedbackDialogs {
  static Future<bool?> showConfirmation({
    required BuildContext context,
    required String title,
    required String content,
    String confirmText = 'Konfirmasi',
    String cancelText = 'Batal',
    bool isDestructive = false,
  }) {
    return AppDialog.showConfirmation(
      context: context,
      title: title,
      content: content,
      confirmText: confirmText,
      cancelText: cancelText,
      type: isDestructive ? DialogType.error : DialogType.info,
    );
  }

  static void showSnackbar(BuildContext context, String message, {bool isError = false}) {
    if (isError) {
      AppSnackBar.showError(context, message);
    } else {
      AppSnackBar.showSuccess(context, message);
    }
  }
}
