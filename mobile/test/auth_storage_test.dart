import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('AuthStorage Tenant Tests', () {
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
