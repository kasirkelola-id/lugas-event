import 'dart:convert';
import '../models/wheel_model.dart';
import '../core/network/api_client.dart';

class WheelService {
  static Future<Map<String, dynamic>> getSessions() async {
    try {
      final response = await ApiClient.get('/wheels');
      if (response.statusCode == 200) {
        final body = json.decode(response.body);
        final List<dynamic> list = body['data'];
        final sessions = list
            .map((e) => WheelSessionModel.fromJson(e))
            .toList();
        return {'success': true, 'sessions': sessions};
      }
      return _handleError(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createSession({
    required String title,
    required String sourceType,
    required int spinDurationSeconds,
    required bool removeWinnerAfterSpin,
    required List<dynamic> items,
  }) async {
    try {
      final response = await ApiClient.post('/wheels', {
        'title': title,
        'source_type': sourceType,
        'spin_duration_seconds': spinDurationSeconds,
        'remove_winner_after_spin': removeWinnerAfterSpin ? 1 : 0,
        'items': items,
      });
      if (response.statusCode == 200 || response.statusCode == 201) {
        final body = json.decode(response.body);
        return {'success': true, 'session_id': body['session_id']};
      }
      return _handleError(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getSessionDetails(int id) async {
    try {
      final response = await ApiClient.get('/wheels/$id');
      if (response.statusCode == 200) {
        final body = json.decode(response.body);
        final data = body['data'];
        final session = WheelSessionModel.fromJson(data['session']);
        final items = (data['items'] as List)
            .map((e) => WheelItemModel.fromJson(e))
            .toList();
        final results = (data['results'] as List)
            .map((e) => WheelResultModel.fromJson(e))
            .toList();

        return {
          'success': true,
          'session': session,
          'items': items,
          'results': results,
        };
      }
      return _handleError(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> spin(int id) async {
    try {
      final response = await ApiClient.post('/wheels/$id/spin', {});
      if (response.statusCode == 200) {
        final body = json.decode(response.body);
        return {
          'success': true,
          'data': WheelResultModel.fromJson(body['data']),
        };
      }
      return _handleError(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> closeSession(int id) async {
    try {
      final response = await ApiClient.patch('/wheels/$id/status', {});
      if (response.statusCode == 200) {
        return {'success': true};
      }
      return _handleError(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Map<String, dynamic> _handleError(dynamic response) {
    String message = 'Terjadi kesalahan';
    if (response.body != null && response.body.isNotEmpty) {
      try {
        final body = json.decode(response.body);
        if (body['messages'] != null) {
          final messages = body['messages'];
          if (messages is Map) {
            message = messages.values.first.toString();
          } else if (messages is String) {
            message = messages;
          }
        }
      } catch (_) {}
    }
    return {'success': false, 'message': message};
  }
}
