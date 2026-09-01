import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/event_model.dart';
import '../storage/auth_storage.dart';

class EventService {
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

  static Future<Map<String, dynamic>> getEvents() async {
    try {
      final response = await ApiClient.get('/events');
      final result = await _handleResponse(response);
      if (result['success']) {
        print('[DEBUG] API response events diterima. Memulai parsing...');
        try {
          final List<dynamic> list = result['data'];
          final events = list.map((e) => EventModel.fromJson(e)).toList();
          print('[DEBUG] Parsing events berhasil.');
          return {'success': true, 'events': events};
        } catch (e, stackTrace) {
          print('[DEBUG] Parsing events GAGAL!');
          print('Exception: $e');
          print('StackTrace: $stackTrace');
          return {'success': false, 'message': 'Data aplikasi tidak dapat dimuat (Error Parsing Event)', 'isParsingError': true};
        }
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getEvent(int id) async {
    try {
      final response = await ApiClient.get('/events/$id');
      final result = await _handleResponse(response);
      if (result['success']) {
        return {'success': true, 'event': EventModel.fromJson(result['data'])};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createEvent({
    required String nama, 
    required String tanggal,
    bool requireGps = false,
    double? latitude,
    double? longitude,
    int? radius,
  }) async {
    try {
      final response = await ApiClient.post('/events', {
        'nama_acara': nama,
        'tanggal_acara': tanggal,
        'require_gps': requireGps ? 1 : 0,
        if (requireGps && latitude != null) 'latitude': latitude,
        if (requireGps && longitude != null) 'longitude': longitude,
        if (requireGps && radius != null) 'radius': radius,
      });
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> updateEvent({
    required int id, 
    required String nama, 
    required String tanggal,
    bool requireGps = false,
    double? latitude,
    double? longitude,
    int? radius,
  }) async {
    try {
      final headers = await ApiClient.getHeaders();
      final response = await http.put(
        Uri.parse('${ApiClient.baseUrl}/events/$id'),
        headers: headers,
        body: jsonEncode({
          'nama_acara': nama,
          'tanggal_acara': tanggal,
          'require_gps': requireGps ? 1 : 0,
          if (requireGps && latitude != null) 'latitude': latitude,
          if (requireGps && longitude != null) 'longitude': longitude,
          if (requireGps && radius != null) 'radius': radius,
        }),
      );
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> closeEvent(int id) async {
    try {
      final headers = await ApiClient.getHeaders();
      final response = await http.patch(
        Uri.parse('${ApiClient.baseUrl}/events/$id/status'),
        headers: headers,
        body: jsonEncode({'status_aktif': 0}),
      );
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
