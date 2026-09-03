import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/network/api_client.dart';
import '../models/inventory_model.dart';
import '../models/inventory_loan_model.dart';
import '../storage/auth_storage.dart';

class InventoryService {
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

  static Future<Map<String, dynamic>> getInventories() async {
    try {
      final response = await ApiClient.get('/inventories');
      final result = await _handleResponse(response);
      if (result['success']) {
        final List<dynamic> list = result['data'];
        final inventories = list.map((e) => Inventory.fromJson(e)).toList();
        return {'success': true, 'inventories': inventories};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> createInventory(Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.post('/inventories', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> getLoans() async {
    try {
      final response = await ApiClient.get('/inventories/loans');
      final result = await _handleResponse(response);
      if (result['success']) {
        final List<dynamic> list = result['data'];
        final loans = list.map((e) => InventoryLoan.fromJson(e)).toList();
        return {'success': true, 'loans': loans};
      }
      return result;
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> requestLoan(Map<String, dynamic> data) async {
    try {
      final response = await ApiClient.post('/inventories/loans', data);
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }

  static Future<Map<String, dynamic>> changeLoanStatus(int loanId, String status) async {
    try {
      final response = await ApiClient.patch('/inventories/loans/$loanId/status', {'status': status});
      return await _handleResponse(response);
    } catch (e) {
      return {'success': false, 'message': 'Terjadi kesalahan jaringan'};
    }
  }
}
