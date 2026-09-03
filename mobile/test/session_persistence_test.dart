import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

void main() {
  group('Session Persistence Tests', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('no saved token -> hasToken is false', () async {
      final hasToken = await AuthStorage.hasToken();
      expect(hasToken, false);
    });

    test('valid saved token -> hasToken is true', () async {
      await AuthStorage.saveToken('valid_opaque_token');
      final hasToken = await AuthStorage.hasToken();
      expect(hasToken, true);
    });

    test('logout -> storage cleared', () async {
      await AuthStorage.saveToken('valid_opaque_token');
      await AuthStorage.saveTenant(1, 'Karang Taruna Test');
      
      await AuthStorage.removeToken();
      await AuthStorage.clearTenant();
      
      final hasToken = await AuthStorage.hasToken();
      final tenant = await AuthStorage.getTenant();
      
      expect(hasToken, false);
      expect(tenant, null);
    });
    
    test('tenant switch logic check', () async {
      await AuthStorage.saveTenant(1, 'Karang Taruna Test');
      final tenant = await AuthStorage.getTenant();
      expect(tenant?['id'], 1);
      expect(tenant?['name'], 'Karang Taruna Test');
    });
  });
}
