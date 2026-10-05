import 'dart:convert';

class PasswordPolicy {
  static String? validate(String? value) {
    if (value == null || value.runes.length < 12) {
      return 'Minimal 12 karakter';
    }
    if (utf8.encode(value).length > 72) {
      return 'Maksimal 72 byte';
    }
    if (['superadmin123', 'lugasjosjis', 'kartarjosjis'].contains(value)) {
      return 'Tidak boleh gunakan password bawaan';
    }
    return null;
  }
}
