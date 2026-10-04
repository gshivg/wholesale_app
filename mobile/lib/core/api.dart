import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'app_config.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.status, this.code, this.fieldErrors = const {}});
  final String message;
  final int? status;
  final String? code;
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

/// Thin Dio wrapper: bearer token, JSON errors → [ApiException], 401 → [onUnauthorized].
class Api {
  Api(this._prefs) {
    _dio = Dio(BaseOptions(
      baseUrl: '${AppConfig.apiBase}/api/v1',
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
    ));
    _dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
      final t = token;
      if (t != null) o.headers['Authorization'] = 'Bearer $t';
      o.headers['Accept-Language'] = locale;
      h.next(o);
    }, onError: (e, h) {
      if (e.response?.statusCode == 401 && token != null) onUnauthorized?.call();
      h.next(e);
    }));
  }

  final SharedPreferences _prefs;
  late final Dio _dio;
  void Function()? onUnauthorized;
  String locale = 'en';

  static const _tokenKey = 'auth_token';
  String? get token => _prefs.getString(_tokenKey);
  Future<void> setToken(String? t) => t == null ? _prefs.remove(_tokenKey) : _prefs.setString(_tokenKey, t);

  Future<T> _run<T>(Future<Response<dynamic>> Function() call, T Function(dynamic json) parse) async {
    try {
      final r = await call();
      return parse(r.data);
    } on DioException catch (e) {
      throw _toException(e);
    }
  }

  ApiException _toException(DioException e) {
    final res = e.response;
    if (res == null) {
      return ApiException('Cannot reach the server. Check your connection and try again.');
    }
    final data = res.data;
    var message = 'Something went wrong (${res.statusCode}).';
    final fields = <String, String>{};
    String? code;
    if (data is Map) {
      message = (data['message'] as String?) ?? message;
      code = data['code'] as String?;
      final errors = data['errors'];
      if (errors is Map) {
        errors.forEach((k, v) => fields['$k'] = v is List && v.isNotEmpty ? '${v.first}' : '$v');
        if (fields.isNotEmpty) message = fields.values.first;
      }
    }
    return ApiException(message, status: res.statusCode, code: code, fieldErrors: fields);
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _run(() => _dio.get(path, queryParameters: query), (j) => Map<String, dynamic>.from(j as Map));

  Future<Map<String, dynamic>> post(String path, {Object? data}) =>
      _run(() => _dio.post(path, data: data), (j) => Map<String, dynamic>.from((j ?? {}) as Map));

  Future<Map<String, dynamic>> put(String path, {Object? data}) =>
      _run(() => _dio.put(path, data: data), (j) => Map<String, dynamic>.from((j ?? {}) as Map));

  Future<Map<String, dynamic>> delete(String path) =>
      _run(() => _dio.delete(path), (j) => Map<String, dynamic>.from((j ?? {}) as Map));
}
