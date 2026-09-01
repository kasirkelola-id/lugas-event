import 'package:flutter/foundation.dart';

class ApiConfig {
  static const String baseUrl = 'https://kartar.kelolakasir.id/api';

  static String get socketUrl {
      return 'https://kartar.kelolakasir.id';
    // if (kReleaseMode) {
    // } else {
    //   // Use 10.0.2.2 for Android emulator to connect to localhost Node.js server
    //   return 'http://10.0.2.2:3000';
    // }
  }
}
