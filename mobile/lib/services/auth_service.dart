import 'dart:convert';
import '../core/network/api_client.dart';
import '../models/user_model.dart';
import '../storage/auth_storage.dart';

class AuthService {
  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final response = await ApiClient.post('/login', {
        'username': username,
        'password': password,
      });
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        final token = data['data']['token'];
        await AuthStorage.saveToken(token);
        return {'success': true, 'user': UserModel.fromJson(data['data']['user'])};
      }
      return {'success': false, 'message': data['message'] ?? 'Login gagal'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getMe() async {
    try {
      final response = await ApiClient.get('/me');
      if (response.statusCode == 401) {
        await AuthStorage.removeToken();
        return {'success': false, 'message': 'Sesi Anda telah berakhir, silakan login kembali.'};
      }
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        return {'success': true, 'user': UserModel.fromJson(data['data'])};
      }
      return {'success': false, 'message': data['message'] ?? 'Gagal mengambil profil'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<void> logout() async {
    try {
      await ApiClient.post('/logout', {});
    } catch (e) {
      // Ignore network errors on logout
    }
    await AuthStorage.removeToken();
  }
}
