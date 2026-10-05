import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('AuthStorage Tenant Tests', () {
    test('bearer save never writes a plaintext preference', () async {
      await AuthStorage.saveToken('synthetic-token');
      expect(await AuthStorage.getToken(), 'synthetic-token');
      expect((await SharedPreferences.getInstance()).getString('auth_token'), isNull);
    });

    test('legacy token migrates once and plaintext is removed', () async {
      SharedPreferences.setMockInitialValues({'auth_token': 'synthetic-legacy'});
      expect(await AuthStorage.getToken(), 'synthetic-legacy');
      expect((await SharedPreferences.getInstance()).getString('auth_token'), isNull);
      expect(await AuthStorage.getToken(), 'synthetic-legacy');
    });
    test('saveTenant and getTenant work correctly', () async {
      // Act
      await AuthStorage.saveTenant(123, 'KT Mawar');
      final tenant = await AuthStorage.getTenant();

      // Assert
      expect(tenant, isNotNull);
      expect(tenant!['id'], 123);
      expect(tenant['name'], 'KT Mawar');
    });

    test('clearTenant removes tenant data', () async {
      // Arrange
      await AuthStorage.saveTenant(456, 'KT Melati');

      // Act
      await AuthStorage.clearTenant();
      final tenant = await AuthStorage.getTenant();

      // Assert
      expect(tenant, isNull);
    });
  });
}
