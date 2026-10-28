import 'package:flutter_test/flutter_test.dart';
import 'package:lugas/core/security/password_policy.dart';

void main() {
  test('Passwords may be short or use formerly blocked values', () {
    for (final value in [
      'a',
      'abcd',
      'abcdef',
      'abcdefgh',
      'twelve chars',
      'superadmin123',
    ]) {
      expect(PasswordPolicy.validate(value), isNull);
    }
  });
  test('Empty and NUL inputs are rejected', () {
    expect(PasswordPolicy.validate(null), isNotNull);
    expect(PasswordPolicy.validate(''), isNotNull);
    expect(PasswordPolicy.validate('ab\u0000cd'), isNotNull);
  });
  test('Bcrypt maximum is enforced in UTF-8 bytes', () {
    expect(PasswordPolicy.validate('a' * 72), isNull);
    expect(PasswordPolicy.validate('a' * 73), isNotNull);
    expect(PasswordPolicy.validate('\u754c' * 24), isNull);
    expect(PasswordPolicy.validate('\u754c' * 25), isNotNull);
  });
}
