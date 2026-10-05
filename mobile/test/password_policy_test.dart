import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/security/password_policy.dart';

void main() {
  test('New passwords need 12 characters without composition rules', () {
    expect(PasswordPolicy.validate('elevenchars'), isNotNull);
    expect(PasswordPolicy.validate('twelve chars'), isNull);
    expect(PasswordPolicy.validate('simple personal passphrase'), isNull);
  });
  test('Bcrypt maximum is enforced in UTF-8 bytes', () {
    expect(PasswordPolicy.validate('a' * 72), isNull);
    expect(PasswordPolicy.validate('a' * 73), isNotNull);
    expect(PasswordPolicy.validate('界' * 24), isNull);
    expect(PasswordPolicy.validate('界' * 25), isNotNull);
  });
  test('Known default password cannot be reused', () {
    expect(PasswordPolicy.validate('superadmin123'), isNotNull);
  });
}
