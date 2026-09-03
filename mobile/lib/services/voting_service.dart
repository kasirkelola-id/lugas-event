import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/voting_model.dart';
import '../storage/auth_storage.dart';

class VotingService {
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

  static Future<Map<String, dynamic>> getVotings() async {
    try {
      final response = await ApiClient.get('/votings');
      final result = await _handleResponse(response);
      if (result['success']) {
        final List<dynamic> list = result['data'];
        final votings = list.map((e) => Voting.fromJson(e)).toList();
        return {'success': true, 'votings': votings};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getVotingDetail(int id) async {
    try {
      final response = await ApiClient.get('/votings/$id');
      final result = await _handleResponse(response);
      if (result['success']) {
        return {'success': true, 'voting': Voting.fromJson(result['data'])};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createVoting(Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.post('/votings', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> submitVote(int votingId, int optionId) async {
    try {
      final response = await ApiClient.post('/votings/$votingId/vote', {'option_id': optionId});
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> changeStatus(int votingId, String status) async {
    try {
      final response = await ApiClient.patch('/votings/$votingId/status', {'status': status});
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
