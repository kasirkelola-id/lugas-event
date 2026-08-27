import 'package:qr/qr.dart';
import 'package:image/image.dart' as img;

void main() {
  final qrCode = QrCode(4, QrErrorCorrectLevel.M)..addData("Hello");
  final qrImage = QrImage(qrCode);
  print(qrImage.moduleCount);
}
