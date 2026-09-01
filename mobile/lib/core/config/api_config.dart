import 'package:flutter/foundation.dart';

class ApiConfig {
  static const String baseUrl = 'https://lugas.full.diskon.cloud/api';

  static String get socketUrl {
    if (kReleaseMode) {
      return 'https://kartar.kelolakasir.id';
    } else {
      // Use 10.0.2.2 for Android emulator to connect to localhost Node.js server
      return 'http://10.0.2.2:3000';
    }
  }
}
