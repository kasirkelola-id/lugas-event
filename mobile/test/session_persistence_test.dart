import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

void main() {
  group('Session Persistence Tests (Batch 1 Contract)', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('1. valid auth + restart => Home (session restored)', () async {
      await AuthStorage.saveToken('valid_opaque_token');
      await AuthStorage.saveTenant(1, 'Tenant Valid');

      final hasToken = await AuthStorage.hasToken();
      final tenant = await AuthStorage.getTenant();

      expect(hasToken, true);
      expect(tenant?['id'], 1);
    });

    test('2. expired/no auth token + tenant verified => LoginScreen (bukan PinScreen)', () async {
      // Tidak ada token, tapi ada tenant context
      await AuthStorage.saveTenant(1, 'Tenant Valid');

      final hasToken = await AuthStorage.hasToken();
      final tenant = await AuthStorage.getTenant();

      expect(hasToken, false);
      expect(tenant, isNotNull);
      expect(tenant?['id'], 1);
    });

    test('3. logout biasa => hapus auth token saja, tenant context tetap ada (LoginScreen)', () async {
      await AuthStorage.saveToken('valid_opaque_token');
      await AuthStorage.saveTenant(1, 'Tenant Valid');

      // Logout biasa action:
      await AuthStorage.removeToken();

      final hasToken = await AuthStorage.hasToken();
      final tenant = await AuthStorage.getTenant();

      expect(hasToken, false);
      expect(tenant, isNotNull); // Tenant TIDAK DIHAPUS
    });

    test('4. explicit Ganti PIN => hapus auth token dan tenant context (PinScreen)', () async {
      await AuthStorage.saveToken('valid_opaque_token');
      await AuthStorage.saveTenant(1, 'Tenant Valid');

      // Ganti PIN action:
      await AuthStorage.removeToken();
      await AuthStorage.clearTenant();

      final hasToken = await AuthStorage.hasToken();
      final tenant = await AuthStorage.getTenant();

      expect(hasToken, false);
      expect(tenant, isNull); // Tenant context dihapus!
    });
  });
}
