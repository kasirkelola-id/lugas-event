import 'dart:convert';

class PasswordPolicy {
  static String? validate(String? value) {
    if (value == null || value.isEmpty) {
      return 'Password wajib diisi';
    }
    if (value.contains('\u0000')) {
      return 'Password tidak boleh mengandung byte NUL';
    }
    if (utf8.encode(value).length > 72) {
      return 'Maksimal 72 byte';
    }
    return null;
  }
}
