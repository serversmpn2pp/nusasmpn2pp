import 'package:dio/dio.dart';
import 'package:nusa/core/errors/app_exception.dart';

AppException mapDioException(DioException exception) {
  final statusCode = exception.response?.statusCode;
  final data = exception.response?.data;

  if (statusCode == 401) {
    return UnauthorizedException(cause: exception);
  }

  if (statusCode == 403) {
    return NetworkException(
      'Anda tidak memiliki hak akses untuk membuka data ini.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  if (statusCode == 404) {
    return NetworkException(
      'Data yang diminta tidak ditemukan.',
      statusCode: statusCode,
      cause: exception,
    );
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

  if (statusCode != null && statusCode >= 500) {
    return NetworkException(
      'Layanan NUSA sedang mengalami gangguan. Silakan coba kembali '
      'beberapa saat lagi. Jika masih belum berhasil, hubungi admin sekolah.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  if (exception.type == DioExceptionType.connectionError ||
      exception.type == DioExceptionType.connectionTimeout ||
      exception.type == DioExceptionType.receiveTimeout ||
      exception.type == DioExceptionType.sendTimeout ||
      exception.type == DioExceptionType.badCertificate ||
      (exception.type == DioExceptionType.unknown &&
          exception.response == null)) {
    return NetworkException(
      'Koneksi ke NUSA belum tersedia. Pastikan perangkat terhubung ke '
      'internet, lalu coba lagi. Jika masih belum berhasil, hubungi admin '
      'sekolah.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  if (exception.type == DioExceptionType.cancel) {
    return NetworkException(
      'Permintaan dibatalkan. Silakan coba lagi.',
      statusCode: statusCode,
      cause: exception,
    );
  }

  return NetworkException(
    _messageFrom(data) ??
        'NUSA belum dapat memproses permintaan Anda. Silakan coba lagi. '
            'Jika masalah berlanjut, hubungi admin sekolah.',
    statusCode: statusCode,
    cause: exception,
  );
}

String? _messageFrom(Object? data) {
  if (data is Map && data['message'] is String) {
    final message = (data['message'] as String).trim();
    if (message.isEmpty || _looksTechnical(message)) return null;

    return message;
  }

  return null;
}

bool _looksTechnical(String message) {
  final value = message.toLowerCase();

  return [
    'sqlstate',
    'stack trace',
    'vendor/',
    'vendor\\',
    'undefined column',
    'undefined table',
    'queryexception',
    'fatal error',
  ].any(value.contains);
}
