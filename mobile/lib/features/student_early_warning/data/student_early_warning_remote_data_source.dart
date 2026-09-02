import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_early_warning/domain/student_early_warning.dart';

abstract interface class StudentEarlyWarningRemoteDataSource {
  Future<StudentEarlyWarningPage> fetch({
    required String query,
    required String type,
    required String level,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  });
  Future<StudentEarlyWarningDetail> fetchDetail(int id);
  Future<StudentWarningProcessResult> process(int? academicYearId);
}

final class DioStudentEarlyWarningRemoteDataSource
    implements StudentEarlyWarningRemoteDataSource {
  DioStudentEarlyWarningRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentEarlyWarningPage> fetch({
    required String query,
    required String type,
    required String level,
    required String status,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peringatan-dini-siswa',
        queryParameters: {
          'kata_kunci': query,
          'jenis': type,
          'tingkat': level,
          'status': status,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return StudentEarlyWarningPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentEarlyWarningDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peringatan-dini-siswa/$id',
      );
      return StudentEarlyWarningDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentWarningProcessResult> process(int? academicYearId) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'peringatan-dini-siswa/proses',
        data: {'tahun_pelajaran_id': ?academicYearId},
      );
      return StudentWarningProcessResult.fromJson(
        response.data ?? <String, dynamic>{},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final studentEarlyWarningRemoteDataSourceProvider =
    Provider<StudentEarlyWarningRemoteDataSource>(
      (ref) => DioStudentEarlyWarningRemoteDataSource(ref.watch(dioProvider)),
    );
