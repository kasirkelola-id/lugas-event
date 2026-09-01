import 'package:shared_preferences/shared_preferences.dart';

class AuthStorage {
  static const String _tokenKey = 'auth_token';
  static const String _ktIdKey = 'karang_taruna_id';
  static const String _ktNameKey = 'nama_organisasi';

  static Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
  }

  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> removeToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
  }

  static Future<bool> hasToken() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  static Future<void> saveTenant(int id, String name) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_ktIdKey, id);
    await prefs.setString(_ktNameKey, name);
  }

  static Future<Map<String, dynamic>?> getTenant() async {
    final prefs = await SharedPreferences.getInstance();
    final id = prefs.getInt(_ktIdKey);
    final name = prefs.getString(_ktNameKey);
    if (id != null && name != null) {
      return {'id': id, 'name': name};
    }
    return null;
  }

  static Future<void> clearTenant() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_ktIdKey);
    await prefs.remove(_ktNameKey);
  }
}
