import 'dart:async';
import 'dart:io';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/storage/auth_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class UploadClient extends MockClient {
  UploadClient(super.handler);
  bool closed = false;
  @override
  void close() {
    closed = true;
    super.close();
  }
}

void main() {
  late Directory directory;
  late File image;
  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    await AuthStorage.saveToken('synthetic-bearer');
    await AuthStorage.saveTenant(101, 'Synthetic');
    directory = await Directory.systemTemp.createTemp('kartar-upload-test-');
    image = await File(
      '${directory.path}/synthetic.png',
    ).writeAsBytes([1, 2, 3]);
  });
  tearDown(() async {
    await image.delete();
    await directory
        .delete(); // Exact empty test-created directory; no recursive removal.
  });

  test('multipart uses scoped auth and closes its owned client', () async {
    final client = UploadClient((request) async {
      expect(request.headers['Authorization'], 'Bearer synthetic-bearer');
      expect(request.headers['X-Karang-Taruna-ID'], '101');
      expect(
        request.headers['content-type'],
        contains('multipart/form-data; boundary='),
      );
      expect(request.body, contains('name="photo"'));
      return http.Response('{"status":true,"data":{}}', 200);
    });
    final response = await http.runWithClient(
      () => ApiClient.uploadPhoto(image),
      () => client,
    );
    expect(response.statusCode, 200);
    expect(client.closed, isTrue);
  });

  test('oversized file is rejected before any transport send', () async {
    final handle = await image.open(mode: FileMode.write);
    await handle.truncate(5 * 1024 * 1024 + 1);
    await handle.close();
    var requests = 0;
    final client = UploadClient((_) async {
      requests++;
      return http.Response('', 200);
    });
    final response = await http.runWithClient(
      () => ApiClient.uploadPhoto(image),
      () => client,
    );
    expect(response.statusCode, 422);
    expect(requests, 0);
    expect(client.closed, isTrue);
  });

  test(
    'stalled upload reaches the actual shared deadline and closes transport',
    () async {
      final stalled = Completer<http.Response>();
      final client = UploadClient((_) => stalled.future);
      final response = await http.runWithClient(
        () => ApiClient.uploadPhoto(image),
        () => client,
      );
      expect(response.statusCode, 408);
      expect(client.closed, isTrue);
    },
    timeout: const Timeout(Duration(seconds: 45)),
  );
}
