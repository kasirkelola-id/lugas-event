import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/announcement_model.dart';
import '../storage/auth_storage.dart';

class AnnouncementService {
  static Future<Map<String, dynamic>> _handleResponse(http.Response response) async {
    if (response.statusCode == 401) {
      await AuthStorage.removeToken();
      return {'success': false, 'message': 'Sesi telah berakhir', 'statusCode': 401};
    }
    
    try {
      final data = jsonDecode(response.body);
      if (response.statusCode >= 200 && response.statusCode < 300 && data['status'] == true) {
        return {'success': true, 'data': data['data'], 'message': data['message']};
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

  static Future<Map<String, dynamic>> getAnnouncements() async {
    try {
      final response = await ApiClient.get('/announcements');
      final result = await _handleResponse(response);
      
      if (result['success']) {
        print('[DEBUG] API response announcements diterima. Memulai parsing...');
        try {
          final List<dynamic> list = result['data'];
          final announcements = list.map((e) => AnnouncementModel.fromJson(e)).toList();
          print('[DEBUG] Parsing announcements berhasil.');
          return {'success': true, 'data': announcements};
        } catch (e, stackTrace) {
          print('[DEBUG] Parsing announcements GAGAL!');
          print('Exception: $e');
          print('StackTrace: $stackTrace');
          return {'success': false, 'message': 'Data aplikasi tidak dapat dimuat (Error Parsing Announcement)', 'isParsingError': true};
        }
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createAnnouncement(Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.post('/announcements', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> updateAnnouncement(int id, Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.put('/announcements/$id', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> toggleStatus(int id) async {
    try {
      final headers = await ApiClient.getHeaders();
      final response = await http.patch(
        Uri.parse('${ApiClient.baseUrl}/announcements/$id/status'),
        headers: headers,
      );
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> deleteAnnouncement(int id) async {
    try {
      final headers = await ApiClient.getHeaders();
      final response = await http.delete(
        Uri.parse('${ApiClient.baseUrl}/announcements/$id'),
        headers: headers,
      );
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
