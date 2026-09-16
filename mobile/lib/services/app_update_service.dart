import 'dart:convert';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:http/http.dart' as http;
import '../core/config/api_config.dart';

class AppVersionInfo {
  final bool updateEnabled;
  final String versionName;
  final int versionCode;
  final String downloadUrl;
  final String releaseNotes;

  AppVersionInfo({
    required this.updateEnabled,
    required this.versionName,
    required this.versionCode,
    required this.downloadUrl,
    required this.releaseNotes,
  });

  factory AppVersionInfo.fromJson(Map<String, dynamic> json) {
    return AppVersionInfo(
      updateEnabled: json['update_enabled'] ?? false,
      versionName: json['version_name'] ?? '',
      versionCode: json['version_code'] ?? 1,
      downloadUrl: json['download_url'] ?? '',
      releaseNotes: json['release_notes'] ?? '',
    );
  }
}

class AppUpdateService {
  static final AppUpdateService _instance = AppUpdateService._internal();

  factory AppUpdateService({http.Client? client}) {
    if (client != null) {
      _instance._client = client;
    }
    return _instance;
  }

  AppUpdateService._internal();

  http.Client _client = http.Client();

  AppVersionInfo? _latestVersionInfo;
  bool _updateAvailable = false;

  bool get isUpdateAvailable => _updateAvailable;
  AppVersionInfo? get latestVersionInfo => _latestVersionInfo;

  Future<void> checkForUpdates() async {
    try {
      final response = await _client.get(
        Uri.parse('${ApiConfig.baseUrl}/app-version'),
        headers: {
          'Accept': 'application/json',
        },
      ).timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == true && data['data'] != null) {
          _latestVersionInfo = AppVersionInfo.fromJson(data['data']);

          if (_latestVersionInfo!.updateEnabled) {
            PackageInfo packageInfo = await PackageInfo.fromPlatform();
            int currentVersionCode = int.tryParse(packageInfo.buildNumber) ?? 1;

            if (_latestVersionInfo!.versionCode > currentVersionCode) {
              _updateAvailable = true;
            } else {
              _updateAvailable = false;
            }
          } else {
            _updateAvailable = false;
          }
        }
      }
    } catch (e) {
      // Silently fail on network error as requested
      _updateAvailable = false;
    }
  }
}
