import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/attendance_exceptions/domain/attendance_exception.dart';

class AttendanceExceptionsRepository {
  AttendanceExceptionsRepository(this._dio);
  final Dio _dio;
  Future<ExceptionData> list(ExceptionData filter) =>
      _get('pengecualian-presensi', filter);
  Future<ExceptionData> references() => _get('pengecualian-presensi/referensi');
  Future<ExceptionData> detail(int id) => _get('pengecualian-presensi/$id');
  Future<ExceptionData> preview(ExceptionData values) =>
      _post('pengecualian-presensi/pratinjau', values);
  Future<ExceptionData> apply(String token) =>
      _post('pengecualian-presensi', {'token': token, 'konfirmasi': true});
  Future<ExceptionData> cancel(int id, String reason) => _post(
    'pengecualian-presensi/$id/batalkan',
    {'alasan_pembatalan': reason, 'konfirmasi': true},
  );

  Future<ExceptionData> _get(
    String path, [
    ExceptionData query = const {},
  ]) async {
    try {
      final r = await _dio.get<ExceptionData>(path, queryParameters: query);
      return exceptionMap(r.data?['data']);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }

  Future<ExceptionData> _post(String path, ExceptionData values) async {
    try {
      final r = await _dio.post<ExceptionData>(path, data: values);
      return exceptionMap(r.data?['data']);
    } on DioException catch (e) {
      throw mapDioException(e);
    }
  }
}

final attendanceExceptionsRepositoryProvider =
    Provider<AttendanceExceptionsRepository>(
      (ref) => AttendanceExceptionsRepository(ref.watch(dioProvider)),
    );
