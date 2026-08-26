import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/grade_weight_scheme/domain/grade_weight_scheme.dart';

abstract interface class GradeWeightSchemeRemoteDataSource {
  Future<GradeWeightSchemePage> fetch({
    required int? academicYearId,
    required String semester,
    required String grade,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(GradeWeightSchemeFormValue value);

  Future<void> update({
    required int id,
    required GradeWeightSchemeFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioGradeWeightSchemeRemoteDataSource
    implements GradeWeightSchemeRemoteDataSource {
  DioGradeWeightSchemeRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<GradeWeightSchemePage> fetch({
    required int? academicYearId,
    required String semester,
    required String grade,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'skema-bobot-nilai',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
          'tingkat': grade,
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GradeWeightSchemePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(GradeWeightSchemeFormValue value) =>
      _send(path: 'skema-bobot-nilai', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required GradeWeightSchemeFormValue value,
  }) => _send(path: 'skema-bobot-nilai/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('skema-bobot-nilai/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _send({
    required String path,
    required String method,
    required GradeWeightSchemeFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: value.toJson(),
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final gradeWeightSchemeRemoteDataSourceProvider =
    Provider<GradeWeightSchemeRemoteDataSource>(
      (ref) => DioGradeWeightSchemeRemoteDataSource(ref.watch(dioProvider)),
    );
