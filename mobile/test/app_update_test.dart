import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/services/app_update_service.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:package_info_plus/package_info_plus.dart';

class MockClient extends http.BaseClient {
  final http.Response response;
  MockClient(this.response);

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    return http.StreamedResponse(
      Stream.value(response.bodyBytes),
      response.statusCode,
      request: request,
    );
  }
}

void main() {
  setUp(() {
    PackageInfo.setMockInitialValues(
      appName: 'Kartar',
      packageName: 'id.kelolakasir.kartar',
      version: '1.0.0',
      buildNumber: '12',
      buildSignature: 'buildSignature',
    );
  });

  test('Newer server version shows update', () async {
    final client = MockClient(http.Response(
      jsonEncode({
        'status': true,
        'data': {
          'update_enabled': true,
          'version_name': '1.2.0',
          'version_code': 13,
          'download_url': 'https://example.com/app.apk',
          'release_notes': 'Update',
        }
      }),
      200,
    ));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, true);
    expect(service.latestVersionInfo?.downloadUrl, 'https://example.com/app.apk');
  });

  test('Same version shows no update', () async {
    final client = MockClient(http.Response(
      jsonEncode({
        'status': true,
        'data': {
          'update_enabled': true,
          'version_name': '1.0.0',
          'version_code': 12,
          'download_url': 'https://example.com/app.apk',
        }
      }),
      200,
    ));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, false);
  });

  test('Installed newer shows no update', () async {
    final client = MockClient(http.Response(
      jsonEncode({
        'status': true,
        'data': {
          'update_enabled': true,
          'version_name': '0.9.0',
          'version_code': 10,
          'download_url': 'https://example.com/app.apk',
        }
      }),
      200,
    ));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, false);
  });

  test('Disabled update shows no update', () async {
    final client = MockClient(http.Response(
      jsonEncode({
        'status': true,
        'data': {
          'update_enabled': false,
          'version_name': '2.0.0',
          'version_code': 99,
          'download_url': 'https://example.com/app.apk',
        }
      }),
      200,
    ));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, false);
  });

  test('Offline/500 error handles gracefully', () async {
    final client = MockClient(http.Response('Server Error', 500));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, false);
  });

  test('Malformed response handles gracefully', () async {
    final client = MockClient(http.Response('{"status": true', 200));

    final service = AppUpdateService(client: client);
    await service.checkForUpdates();
    
    expect(service.isUpdateAvailable, false);
  });
}
