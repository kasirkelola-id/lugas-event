import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/attendance_model.dart';
import '../storage/auth_storage.dart';

class AttendanceService {
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

  static Future<Map<String, dynamic>> getMyHistory() async {
    try {
      final response = await ApiClient.get('/absensi/my');
      final result = await _handleResponse(response);
      if (result['success']) {
        print('[DEBUG] API response attendance my_history diterima. Memulai parsing...');
        try {
          final List<dynamic> list = result['data'];
          final history = list.map((e) => AttendanceModel.fromJson(e)).toList();
          print('[DEBUG] Parsing attendance my_history berhasil.');
          return {'success': true, 'history': history};
        } catch (e, stackTrace) {
          print('[DEBUG] Parsing attendance my_history GAGAL!');
          print('Exception: $e');
          print('StackTrace: $stackTrace');
          return {'success': false, 'message': 'Data aplikasi tidak dapat dimuat (Error Parsing Attendance)', 'isParsingError': true};
        }
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> submitAttendance(String kodeQr, {double? userLat, double? userLng}) async {
    try {
      final payload = <String, dynamic>{
        'kode_qr': kodeQr,
      };
      if (userLat != null) payload['user_lat'] = userLat;
      if (userLng != null) payload['user_lng'] = userLng;

      final response = await ApiClient.post('/absensi', payload);
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Server tidak dapat dihubungi. Periksa koneksi internet Anda.'};
    }
  }

  static Future<Map<String, dynamic>> getEventAttendance(int eventId) async {
    try {
      final response = await ApiClient.get('/events/$eventId/absensi');
      final result = await _handleResponse(response);
      if (result['success']) {
        print('[DEBUG] API response event_attendance diterima. Memulai parsing...');
        try {
          final List<dynamic> list = result['data'];
          final attendees = list.map((e) => AttendanceModel.fromJson(e)).toList();
          print('[DEBUG] Parsing event_attendance berhasil.');
          return {'success': true, 'attendees': attendees};
        } catch (e, stackTrace) {
          print('[DEBUG] Parsing event_attendance GAGAL!');
          print('Exception: $e');
          print('StackTrace: $stackTrace');
          return {'success': false, 'message': 'Data aplikasi tidak dapat dimuat (Error Parsing Event Attendance)', 'isParsingError': true};
        }
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
