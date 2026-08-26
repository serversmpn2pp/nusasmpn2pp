import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/grade_component/domain/grade_component.dart';

abstract interface class GradeComponentRemoteDataSource {
  Future<GradeComponentPage> fetch({
    required String search,
    required int? academicYearId,
    required String semester,
    required String type,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(GradeComponentFormValue value);

  Future<void> update({
    required int id,
    required GradeComponentFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioGradeComponentRemoteDataSource
    implements GradeComponentRemoteDataSource {
  DioGradeComponentRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<GradeComponentPage> fetch({
    required String search,
    required int? academicYearId,
    required String semester,
    required String type,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'komponen-nilai',
        queryParameters: {
          'cari': search.trim().isEmpty ? null : search.trim(),
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
          'jenis_komponen': type,
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GradeComponentPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(GradeComponentFormValue value) =>
      _send(path: 'komponen-nilai', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required GradeComponentFormValue value,
  }) => _send(path: 'komponen-nilai/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('komponen-nilai/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _send({
    required String path,
    required String method,
    required GradeComponentFormValue value,
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

final gradeComponentRemoteDataSourceProvider =
    Provider<GradeComponentRemoteDataSource>(
      (ref) => DioGradeComponentRemoteDataSource(ref.watch(dioProvider)),
    );
