import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../../storage/auth_storage.dart';

class ApiClient {
  static String get baseUrl => ApiConfig.baseUrl;
  static const Duration _timeout = Duration(seconds: 30);

  static Future<Map<String, String>> getHeaders() async {
    final token = await AuthStorage.getToken();
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  static void _logRequest(String method, String url, Map<String, String> headers, [String? body]) {
    if (kDebugMode) {
      final safeHeaders = Map<String, String>.from(headers);
      if (safeHeaders.containsKey('Authorization')) {
        safeHeaders['Authorization'] = 'Bearer [REDACTED]';
      }

      String safeBody = '';
      if (body != null && body.isNotEmpty) {
        try {
          final Map<String, dynamic> parsedBody = jsonDecode(body);
          final safeMap = Map<String, dynamic>.from(parsedBody);
          final sensitiveFields = ['password', 'password_confirmation', 'old_password', 'new_password', 'confirm_password'];
          for (var field in sensitiveFields) {
            if (safeMap.containsKey(field)) {
              safeMap[field] = '[REDACTED]';
            }
          }
          safeBody = jsonEncode(safeMap);
        } catch (_) {
          safeBody = body; // If not JSON, print as is (but this app uses JSON)
        }
      }

      debugPrint('\n========== [API REQUEST] ==========');
      debugPrint('METHOD: $method');
      debugPrint('URL: $url');
      debugPrint('HEADERS: $safeHeaders');
      if (safeBody.isNotEmpty) {
        debugPrint('BODY: $safeBody');
      }
      debugPrint('===================================\n');
    }
  }

  static void _logResponse(String method, String url, int statusCode, String body) {
    if (kDebugMode) {
      // Body is not redacted usually for responses, unless response returns token/password. 
      // Lugas API returns token on login. We'll redact token from response just in case.
      String safeBody = body;
      try {
        final Map<String, dynamic> parsedBody = jsonDecode(body);
        if (parsedBody['data'] != null && parsedBody['data'] is Map) {
          if (parsedBody['data'].containsKey('token')) {
            final Map<String, dynamic> safeMap = Map.from(parsedBody);
            final Map<String, dynamic> safeData = Map.from(safeMap['data']);
            safeData['token'] = '[REDACTED]';
            safeMap['data'] = safeData;
            safeBody = jsonEncode(safeMap);
          }
        }
      } catch (_) {}

      if (statusCode >= 200 && statusCode < 300) {
        debugPrint('\n========== [API RESPONSE] =========');
        debugPrint('STATUS: $statusCode');
        debugPrint('URL: $url');
        debugPrint('BODY: $safeBody');
        debugPrint('===================================\n');
      } else {
        debugPrint('\n========== [API ERROR] ============');
        debugPrint('STATUS: $statusCode');
        debugPrint('URL: $url');
        debugPrint('BODY: $safeBody');
        debugPrint('ERROR: HTTP Error $statusCode');
        debugPrint('===================================\n');
      }
    }
  }

  static void _logException(String url, dynamic e) {
    if (kDebugMode) {
      debugPrint('\n========== [API EXCEPTION] ========');
      debugPrint('URL: $url');
      debugPrint('EXCEPTION: $e');
      debugPrint('===================================\n');
    }
  }

  static Future<http.Response> _executeRequest(String method, String endpoint, Future<http.Response> Function() requestFunc, {Map<String, String>? headers, String? body}) async {
    final fullUrl = '${ApiConfig.baseUrl}$endpoint';
    
    _logRequest(method, fullUrl, headers ?? {}, body);

    try {
      final response = await requestFunc().timeout(_timeout);
      _logResponse(method, fullUrl, response.statusCode, response.body);
      return response;
    } on TimeoutException catch (e) {
      _logException(fullUrl, e);
      return http.Response(jsonEncode({'status': false, 'message': 'Request timeout'}), 408);
    } on SocketException catch (e) {
      _logException(fullUrl, e);
      return http.Response(jsonEncode({'status': false, 'message': 'Tidak dapat terhubung ke server'}), 503);
    } catch (e) {
      _logException(fullUrl, e);
      return http.Response(jsonEncode({'status': false, 'message': 'Terjadi kesalahan internal'}), 500);
    }
  }

  static Future<http.Response> get(String endpoint) async {
    final headers = await getHeaders();
    return _executeRequest(
      'GET', 
      endpoint, 
      () => http.get(Uri.parse('${ApiConfig.baseUrl}$endpoint'), headers: headers),
      headers: headers
    );
  }

  static Future<http.Response> post(String endpoint, Map<String, dynamic> body) async {
    final headers = await getHeaders();
    final jsonBody = jsonEncode(body);
    return _executeRequest(
      'POST', 
      endpoint, 
      () => http.post(Uri.parse('${ApiConfig.baseUrl}$endpoint'), headers: headers, body: jsonBody),
      headers: headers,
      body: jsonBody
    );
  }

  static Future<http.Response> put(String endpoint, Map<String, dynamic> body) async {
    final headers = await getHeaders();
    final jsonBody = jsonEncode(body);
    return _executeRequest(
      'PUT', 
      endpoint, 
      () => http.put(Uri.parse('${ApiConfig.baseUrl}$endpoint'), headers: headers, body: jsonBody),
      headers: headers,
      body: jsonBody
    );
  }

  static Future<http.Response> patch(String endpoint, [Map<String, dynamic>? body]) async {
    final headers = await getHeaders();
    final jsonBody = body != null ? jsonEncode(body) : null;
    return _executeRequest(
      'PATCH', 
      endpoint, 
      () => http.patch(Uri.parse('${ApiConfig.baseUrl}$endpoint'), headers: headers, body: jsonBody),
      headers: headers,
      body: jsonBody
    );
  }

  static Future<http.Response> delete(String endpoint) async {
    final headers = await getHeaders();
    return _executeRequest(
      'DELETE', 
      endpoint, 
      () => http.delete(Uri.parse('${ApiConfig.baseUrl}$endpoint'), headers: headers),
      headers: headers
    );
  }
}
