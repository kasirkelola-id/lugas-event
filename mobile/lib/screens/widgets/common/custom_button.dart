import 'package:flutter/material.dart';
import '../../../core/theme/app_theme.dart';
import 'package:mobile/screens/widgets/common/custom_loading_indicator.dart';

enum ButtonType { primary, secondary, outline, danger }

class CustomButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final ButtonType type;
  final IconData? icon;
  final bool isFullWidth;
  final EdgeInsetsGeometry? padding;

  const CustomButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.type = ButtonType.primary,
    this.icon,
    this.isFullWidth = true,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    Widget buttonContent = isLoading
        ? const SizedBox(
            height: 20,
            width: 20,
            child: CustomLoadingIndicator(size: 24, color: Colors.white),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 20),
                const SizedBox(width: 8),
              ],
              Text(
                text,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
            ],
          );

    Widget button;
    switch (type) {
      case ButtonType.secondary:
        button = ElevatedButton(
          onPressed: isLoading ? null : onPressed,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.secondary,
            foregroundColor: Colors.white,
            padding: padding,
          ),
          child: buttonContent,
        );
        break;
      case ButtonType.danger:
        button = ElevatedButton(
          onPressed: isLoading ? null : onPressed,
          style: ElevatedButton.styleFrom(
            backgroundColor: AppTheme.error,
            foregroundColor: Colors.white,
            padding: padding,
          ),
          child: buttonContent,
        );
        break;
      case ButtonType.outline:
        buttonContent = isLoading
            ? const SizedBox(
                height: 20,
                width: 20,
                child: CustomLoadingIndicator(size: 24, color: AppTheme.primary),
              )
            : Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 20),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    text,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ],
              );
        button = OutlinedButton(
          onPressed: isLoading ? null : onPressed,
          style: OutlinedButton.styleFrom(padding: padding),
          child: buttonContent,
        );
        break;
      case ButtonType.primary:
      default:
        button = ElevatedButton(
          onPressed: isLoading ? null : onPressed,
          style: ElevatedButton.styleFrom(padding: padding),
          child: buttonContent,
        );
        break;
    }

    if (isFullWidth) {
      return SizedBox(width: double.infinity, child: button);
    }
    return button;
  }
}
