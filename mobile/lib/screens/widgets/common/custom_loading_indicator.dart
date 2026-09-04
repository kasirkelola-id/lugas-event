import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import '../../core/theme/app_theme.dart';

class CustomLoadingIndicator extends StatelessWidget {
  final Color? color;
  final double size;

  const CustomLoadingIndicator({
    Key? key,
    this.color,
    this.size = 50.0,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SpinKitThreeBounce(
      color: color ?? AppTheme.primary,
      size: size,
    );
  }
}
