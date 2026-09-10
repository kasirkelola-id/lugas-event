import 'dart:convert';
import '../models/dashboard_summary_model.dart';
import '../core/network/api_client.dart';

class DashboardService {
  static Future<Map<String, dynamic>> getSummary() async {
    try {
      final response = await ApiClient.get('/dashboard');

      if (response.statusCode == 200) {
        try {
          final data = json.decode(response.body);
          if (data['status'] == true) {
            final summary = DashboardSummary.fromJson(data['data']);
            return {'success': true, 'summary': summary};
          }
        } catch (_) {
          return {'success': false, 'message': 'Gagal memproses data dashboard.'};
        }
      }
      return {'success': false, 'message': 'Gagal memuat dashboard'};
    } catch (e) {
      return {'success': false, 'message': 'Koneksi error: $e'};
    }
  }
}
