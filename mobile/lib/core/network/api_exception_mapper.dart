import 'package:dio/dio.dart';
import 'package:nusa/core/errors/app_exception.dart';

AppException mapDioException(DioException exception) {
  final statusCode = exception.response?.statusCode;
  final data = exception.response?.data;

  if (statusCode == 401) {
    return UnauthorizedException(cause: exception);
  }

  if (statusCode == 422) {
    final errors = <String, List<String>>{};

    if (data is Map && data['errors'] is Map) {
      for (final entry in (data['errors'] as Map).entries) {
        final value = entry.value;
        errors[entry.key.toString()] = switch (value) {
          List() => value.map((item) => item.toString()).toList(),
          _ => [value.toString()],
        };
      }
    }

    return ValidationException(
      _messageFrom(data) ?? 'Data yang dikirim belum valid.',
      errors: errors,
      cause: exception,
    );
  }

  if (statusCode == 429) {
    return NetworkException(
      'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  if (exception.type == DioExceptionType.connectionError ||
      exception.type == DioExceptionType.connectionTimeout ||
      exception.type == DioExceptionType.receiveTimeout ||
      exception.type == DioExceptionType.sendTimeout) {
    return NetworkException(
      'Tidak dapat terhubung ke server NUSA. Periksa koneksi dan alamat API.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  return NetworkException(
    _messageFrom(data) ?? 'Terjadi gangguan saat menghubungi server NUSA.',
    statusCode: statusCode,
    cause: exception,
  );
}

String? _messageFrom(Object? data) {
  if (data is Map && data['message'] is String) {
    return data['message'] as String;
  }

  return null;
}
