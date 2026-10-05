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

  // Revocation must use the old session, never an account that logged in later.
  static Future<int> revokeSession(String token) async {
    final client = http.Client();
    try {
      final response = await client
          .post(
            Uri.parse('${ApiConfig.baseUrl}/logout'),
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer $token',
            },
            body: '{}',
          )
          .timeout(const Duration(seconds: 3));
      logResponse('POST', '', response.statusCode, '');
      return response.statusCode;
    } catch (_) {
      logException('', null);
      return 503;
    } finally {
      client.close();
    }
  }

  static Future<Map<String, String>> getHeaders({
    bool excludeTenantHeader = false,
  }) async {
    final token = await AuthStorage.getToken();
    final tenant = await AuthStorage.getTenant();
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }
    if (tenant != null && tenant['id'] != null && !excludeTenantHeader) {
      headers['X-Karang-Taruna-ID'] = tenant['id'].toString();
    }
    return headers;
  }

  @visibleForTesting
  static void logRequest(
    String method,
    String url,
    Map<String, String> headers, [
    String? body,
  ]) {
    if (kDebugMode) debugPrint('API request: $method');
  }

  @visibleForTesting
  static void logResponse(
    String method,
    String url,
    int statusCode,
    String body,
  ) {
    if (kDebugMode) debugPrint('API response: $statusCode');
  }

  @visibleForTesting
  static void logException(String url, dynamic e) {
    if (kDebugMode) debugPrint('API transport failed');
  }

  static Future<http.Response> _executeRequest(
    String method,
    String endpoint,
    Future<http.Response> Function() requestFunc, {
    Map<String, String>? headers,
    String? body,
  }) async {
    final fullUrl = '${ApiConfig.baseUrl}$endpoint';

    logRequest(method, fullUrl, headers ?? {}, body);

    try {
      final response = await requestFunc().timeout(_timeout);
      logResponse(method, fullUrl, response.statusCode, response.body);
      return response;
    } on TimeoutException catch (e) {
      logException(fullUrl, e);
      return http.Response(
        jsonEncode({'status': false, 'message': 'Request timeout'}),
        408,
      );
    } on SocketException catch (e) {
      logException(fullUrl, e);
      return http.Response(
        jsonEncode({
          'status': false,
          'message': 'Tidak dapat terhubung ke server',
        }),
        503,
      );
    } catch (e) {
      logException(fullUrl, e);
      return http.Response(
        jsonEncode({
          'status': false,
          'message': 'Terjadi gangguan pada server. Silakan coba lagi.',
        }),
        500,
      );
    }
  }

  static Future<http.Response> get(
    String endpoint, {
    bool excludeTenantHeader = false,
  }) async {
    final headers = await getHeaders(excludeTenantHeader: excludeTenantHeader);
    return _executeRequest(
      'GET',
      endpoint,
      () => http.get(
        Uri.parse('${ApiConfig.baseUrl}$endpoint'),
        headers: headers,
      ),
      headers: headers,
    );
  }

  static Future<http.Response> post(
    String endpoint,
    Map<String, dynamic> body, {
    Map<String, String>? requestHeaders,
  }) async {
    final headers = requestHeaders ?? await getHeaders();
    final jsonBody = jsonEncode(body);
    return _executeRequest(
      'POST',
      endpoint,
      () => http.post(
        Uri.parse('${ApiConfig.baseUrl}$endpoint'),
        headers: headers,
        body: jsonBody,
      ),
      headers: headers,
      body: jsonBody,
    );
  }

  static Future<http.Response> put(
    String endpoint,
    Map<String, dynamic> body,
  ) async {
    final headers = await getHeaders();
    final jsonBody = jsonEncode(body);
    return _executeRequest(
      'PUT',
      endpoint,
      () => http.put(
        Uri.parse('${ApiConfig.baseUrl}$endpoint'),
        headers: headers,
        body: jsonBody,
      ),
      headers: headers,
      body: jsonBody,
    );
  }

  static Future<http.Response> patch(
    String endpoint, [
    Map<String, dynamic>? body,
  ]) async {
    final headers = await getHeaders();
    final jsonBody = body != null ? jsonEncode(body) : null;
    return _executeRequest(
      'PATCH',
      endpoint,
      () => http.patch(
        Uri.parse('${ApiConfig.baseUrl}$endpoint'),
        headers: headers,
        body: jsonBody,
      ),
      headers: headers,
      body: jsonBody,
    );
  }

  static Future<http.Response> delete(
    String endpoint, [
    Map<String, dynamic>? body,
  ]) async {
    final headers = await getHeaders();
    final jsonBody = body != null ? jsonEncode(body) : null;
    return _executeRequest(
      'DELETE',
      endpoint,
      () => http.delete(
        Uri.parse('${ApiConfig.baseUrl}$endpoint'),
        headers: headers,
        body: jsonBody,
      ),
      headers: headers,
      body: jsonBody,
    );
  }
}
