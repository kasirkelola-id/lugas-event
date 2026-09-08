import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';
import '../../../core/theme/app_theme.dart';

class CustomLoadingIndicator extends StatelessWidget {
  final Color? color;
  final double size;

  const CustomLoadingIndicator({Key? key, this.color, this.size = 50.0})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    // Gunakan FadingCircle untuk tombol/komponen kecil agar tetap rapi
    if (size < 40) {
      return SpinKitFadingCircle(color: color ?? AppTheme.primary, size: size);
    }
    // Gunakan FadingCube atau FoldingCube untuk layar utama agar terasa premium
    return SpinKitFoldingCube(color: color ?? AppTheme.primary, size: size);
  }
}
