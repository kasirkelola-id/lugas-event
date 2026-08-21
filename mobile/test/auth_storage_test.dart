import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile/storage/auth_storage.dart';

void main() {
  group('AuthStorage', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('saveToken and getToken work correctly', () async {
      await AuthStorage.saveToken('test_token_123');
      final token = await AuthStorage.getToken();
      expect(token, 'test_token_123');
      
      final hasToken = await AuthStorage.hasToken();
      expect(hasToken, true);
    });

    test('removeToken clears the token', () async {
      await AuthStorage.saveToken('test_token_123');
      await AuthStorage.removeToken();
      
      final token = await AuthStorage.getToken();
      expect(token, null);
      
      final hasToken = await AuthStorage.hasToken();
      expect(hasToken, false);
    });

    test('hasToken returns false when no token', () async {
      final hasToken = await AuthStorage.hasToken();
      expect(hasToken, false);
    });
  });
}
