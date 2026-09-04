import 'dart:convert';
import '../core/network/api_client.dart';
import '../models/user_model.dart';
import '../storage/auth_storage.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class AuthService {
  static Future<Map<String, dynamic>> verifyPin(String pin) async {
    try {
      final response = await ApiClient.post('/tenant/verify-pin', {
        'pin': pin,
      });
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        return {'success': true, 'data': data['data']};
      }
      return {'success': false, 'message': data['message'] ?? 'PIN tidak valid'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final tenant = await AuthStorage.getTenant();
      if (tenant == null) {
        return {'success': false, 'message': 'Karang Taruna belum diatur. Silakan masukkan PIN terlebih dahulu.'};
      }

      final response = await ApiClient.post('/login', {
        'karang_taruna_id': tenant['id'],
        'username': username,
        'password': password,
      });
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        final token = data['data']['token'];
        await AuthStorage.saveToken(token);
        return {
            'success': true, 
            'user': UserModel.fromJson(data['data']['user']),
            'memberships': data['data']['memberships'] ?? [],
            'requires_tenant_selection': data['data']['requires_tenant_selection'] ?? false,
        };
      }
      return {'success': false, 'message': data['message'] ?? 'Login gagal'};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan: $e'};
    }
  }

  static Future<Map<String, dynamic>> register(Map<String, dynamic> data) async {
    try {
      final tenant = await AuthStorage.getTenant();
      if (tenant != null) {
        data['karang_taruna_id'] = tenant['id'];
      }

      final response = await ApiClient.post('/register', data);
      final responseData = jsonDecode(response.body);
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        if (responseData['status'] == true) {
          return {'success': true, 'message': responseData['message']};
        }
      }
      
      String message = responseData['message'] ?? 'Gagal mendaftar';
      if (response.statusCode == 422 && responseData['errors'] != null) {
         final errors = responseData['errors'] as Map<String, dynamic>;
         message = errors.values.first.toString();
      } else if (responseData['data'] != null && responseData['data'] is Map) {
         final errors = responseData['data'] as Map<String, dynamic>;
         message = errors.values.first.toString();
      }
      
      return {'success': false, 'message': message};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }

  static Future<Map<String, dynamic>> getMe() async {
    try {
      final response = await ApiClient.get('/me');
      if (response.statusCode == 401) {
        await AuthStorage.removeToken();
        return {'success': false, 'message': 'Sesi Anda telah berakhir, silakan login kembali.', 'statusCode': 401};
      }
      if (response.statusCode == 403) {
        return {'success': false, 'message': 'Akses ke Karang Taruna ini ditolak atau keanggotaan tidak aktif.', 'statusCode': 403};
      }
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        return {'success': true, 'user': UserModel.fromJson(data['data'])};
      }
      return {'success': false, 'message': data['message'] ?? 'Gagal mengambil profil', 'statusCode': response.statusCode};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getMemberships() async {
    try {
      // Intentionally omitting tenant_id headers to hit the global /memberships endpoint
      final response = await ApiClient.get('/memberships', excludeTenantHeader: true);
      if (response.statusCode == 401) {
        return {'success': false, 'message': 'Sesi Anda telah berakhir, silakan login kembali.', 'statusCode': 401};
      }
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        return {'success': true, 'data': data['data']};
      }
      return {'success': false, 'message': data['message'] ?? 'Gagal mengambil memberships', 'statusCode': response.statusCode};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<void> logout() async {
    try {
      // First, remove FCM Token from backend
      try {
        final fcmToken = await FirebaseMessaging.instance.getToken();
        if (fcmToken != null) {
          await ApiClient.delete('/fcm-token', {'fcm_token': fcmToken});
        }
      } catch (e) {
        // Ignore FCM errors
      }
      
      await ApiClient.post('/logout', {});
    } catch (e) {
      // Ignore network errors on logout
    }
    await AuthStorage.removeToken();
  }

  static Future<Map<String, dynamic>> updatePassword(String newPassword, String confirmPassword) async {
    try {
      final response = await ApiClient.post('/profile/password', {
        'new_password': newPassword,
        'confirm_password': confirmPassword,
      });
      final data = jsonDecode(response.body);
      if (response.statusCode == 200 && data['status'] == true) {
        return {'success': true, 'message': data['message']};
      }
      
      String message = data['message'] ?? 'Gagal mengubah password';
      if (response.statusCode == 422 && data['errors'] != null) {
         final errors = data['errors'] as Map<String, dynamic>;
         message = errors.values.first.toString();
      } else if (data['data'] != null && data['data'] is Map) {
         final errors = data['data'] as Map<String, dynamic>;
         message = errors.values.first.toString();
      }
      
      return {'success': false, 'message': message};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<void> updateFcmToken(String token) async {
    try {
      await ApiClient.post('/fcm-token', {
        'fcm_token': token,
        'device_type': 'android',
      });
    } catch (e) {
      // Ignore network errors for token update
    }
  }
}
