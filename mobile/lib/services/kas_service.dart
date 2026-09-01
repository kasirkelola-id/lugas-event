import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/kas_model.dart';
import '../storage/auth_storage.dart';

class KasService {
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

  static Future<Map<String, dynamic>> getKasData() async {
    try {
      final response = await ApiClient.get('/kas');
      final result = await _handleResponse(response);
      if (result['success']) {
        try {
          final data = result['data'];
          final int saldo = data['saldo'] ?? 0;
          final List<dynamic> list = data['transaksi'];
          final transaksi = list.map((e) => KasModel.fromJson(e)).toList();
          return {'success': true, 'saldo': saldo, 'transaksi': transaksi};
        } catch (e) {
          return {'success': false, 'message': 'Data kas tidak dapat dimuat (Error Parsing)', 'isParsingError': true};
        }
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createTransaksi({
    required String jenis,
    required int nominal,
    required String keterangan,
    required String tanggal,
  }) async {
    try {
      final response = await ApiClient.post('/kas', {
        'jenis': jenis,
        'nominal': nominal,
        'keterangan': keterangan,
        'tanggal': tanggal,
      });
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> deleteTransaksi(int id) async {
    try {
      final headers = await ApiClient.getHeaders();
      final response = await http.delete(
        Uri.parse('${ApiClient.baseUrl}/kas/$id'),
        headers: headers,
      );
      return _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
