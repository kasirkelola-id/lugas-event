import 'package:qr/qr.dart';
import 'package:image/image.dart' as img;

void main() {
  final qrCode = QrCode.fromData(data: 'Hello', errorCorrectLevel: QrErrorCorrectLevel.M);
  final qrImage = QrImage(qrCode);
  final moduleCount = qrImage.moduleCount;
  final scale = (200 / moduleCount).floor();
  final actualSize = moduleCount * scale;
  
  final image = img.Image(width: actualSize, height: actualSize);
  
  img.fill(image, color: img.ColorRgb8(255, 255, 255));
  
  for (int y = 0; y < moduleCount; y++) {
    for (int x = 0; x < moduleCount; x++) {
      if (qrImage.isDark(y, x)) {
        img.fillRect(
          image, 
          x1: x * scale, 
          y1: y * scale, 
          x2: (x * scale) + scale - 1, 
          y2: (y * scale) + scale - 1, 
          color: img.ColorRgb8(0, 0, 0)
        );
      }
    }
  }
  print('Image created successfully.');
}
