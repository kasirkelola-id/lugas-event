import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../storage/auth_storage.dart';
import 'dart:io';

class ProfileService {
  static Future<Map<String, dynamic>> _handleResponse(
    http.Response response,
  ) async {
    if (response.statusCode == 401) {
      await AuthStorage.removeToken();
      return {
        'success': false,
        'message': 'Sesi telah berakhir',
        'statusCode': 401,
      };
    }

    try {
      final data = jsonDecode(response.body);
      if (response.statusCode >= 200 &&
          response.statusCode < 300 &&
          data['status'] == true) {
        return {
          'success': true,
          'data': data['data'],
          'message': data['message'],
        };
      }

      String message = data['message'] ?? 'Terjadi kesalahan';
      if (response.statusCode == 422 && data['errors'] != null) {
        final errors = data['errors'] as Map<String, dynamic>;
        message = errors.values.first.toString();
      }

      return {
        'success': false,
        'message': message,
        'statusCode': response.statusCode,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Terjadi kesalahan sistem.',
        'statusCode': response.statusCode,
      };
    }
  }

  static Future<Map<String, dynamic>> updateProfile(
    Map<String, dynamic> data,
  ) async {
    try {
      final response = await ApiClient.put('/profile', data);
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> updatePassword(
    Map<String, dynamic> data,
  ) async {
    try {
      final response = await ApiClient.post('/profile/password', data);
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> updateProfilePhoto(File imageFile) async {
    try {
      final response = await ApiClient.uploadPhoto(imageFile);

      return _handleResponse(response);
    } catch (e) {
      return {
        'success': false,
        'message': 'Terjadi kesalahan jaringan saat mengunggah foto',
      };
    }
  }
}
