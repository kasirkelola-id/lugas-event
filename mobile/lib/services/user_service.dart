import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/user_model.dart';
import '../storage/auth_storage.dart';

class UserService {
  static Future<Map<String, dynamic>> _handleResponse(http.Response response) async {
    if (response.statusCode == 401) {
      await AuthStorage.removeToken();
      return {'success': false, 'message': 'Sesi telah berakhir', 'statusCode': 401};
    }
    
    try {
      final data = jsonDecode(response.body);
      if (response.statusCode >= 200 && response.statusCode < 300 && data['status'] == true) {
        return {'success': true, 'data': data['data']};
      }
      
      String message = data['message'] ?? 'Terjadi kesalahan';
      if (response.statusCode == 422 && data['errors'] != null) {
        final errors = data['errors'] as Map<String, dynamic>;
        message = errors.values.first.toString();
      }
      
      return {'success': false, 'message': message, 'statusCode': response.statusCode};
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.', 'statusCode': response.statusCode};
    }
  }

  static Future<Map<String, dynamic>> getUsers() async {
    try {
      final response = await ApiClient.get('/users');
      final result = await _handleResponse(response);
      if (result['success']) {
        final List<dynamic> list = result['data'];
        final users = list.map((e) => UserModel.fromJson(e)).toList();
        return {'success': true, 'users': users};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getRolesSummary() async {
    try {
      final response = await ApiClient.get('/users/roles-summary');
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createUser(Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.post('/users', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }

  static Future<Map<String, dynamic>> updateUser(int id, Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.put('/users/$id', data);
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }

  static Future<Map<String, dynamic>> toggleStatus(int id) async {
    try {
      final response = await ApiClient.patch('/users/$id/status');
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }

  static Future<Map<String, dynamic>> changeRole(int id, String role) async {
    try {
      final response = await ApiClient.patch('/users/$id/role', {'role_level': role});
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }

  static Future<Map<String, dynamic>> resetPassword(int id) async {
    try {
      print('[DEBUG] Requesting reset password for user $id via POST');
      final response = await ApiClient.post('/users/$id/reset-password', {});
      print('[DEBUG] Reset password response status: ${response.statusCode}');
      return _handleResponse(response);
    } catch (e, stackTrace) {
      print('[DEBUG] Reset password exception: $e');
      print('[DEBUG] StackTrace: $stackTrace');
      return {'success': false, 'message': 'Terjadi kesalahan sistem.'};
    }
  }
}
