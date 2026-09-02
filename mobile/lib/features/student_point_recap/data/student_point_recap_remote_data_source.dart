import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_point_recap/domain/student_point_recap.dart';

abstract interface class StudentPointRecapRemoteDataSource {
  Future<StudentPointRecapPage> fetch({
    required String query,
    required String attentionStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  });
  Future<StudentPointRecapDetail> fetchDetail(
    int studentId,
    int? academicYearId,
  );
}

final class DioStudentPointRecapRemoteDataSource
    implements StudentPointRecapRemoteDataSource {
  DioStudentPointRecapRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentPointRecapPage> fetch({
    required String query,
    required String attentionStatus,
    required int? academicYearId,
    required int? classId,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-poin-siswa',
        queryParameters: {
          'kata_kunci': query,
          'status_perhatian': attentionStatus,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': 15,
        },
      );
      return StudentPointRecapPage.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentPointRecapDetail> fetchDetail(
    int studentId,
    int? academicYearId,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-poin-siswa/$studentId',
        queryParameters: {'tahun_pelajaran_id': ?academicYearId},
      );
      return StudentPointRecapDetail.fromJson(_data(response));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _data(Response<Map<String, dynamic>> response) =>
      response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
}

final studentPointRecapRemoteDataSourceProvider =
    Provider<StudentPointRecapRemoteDataSource>(
      (ref) => DioStudentPointRecapRemoteDataSource(ref.watch(dioProvider)),
    );
