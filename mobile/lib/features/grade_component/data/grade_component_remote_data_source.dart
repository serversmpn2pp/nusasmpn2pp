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
    String dateOrder = 'terbaru',
    int perPage = 15,
  });

  Future<String> create(
    GradeComponentFormValue value, {
    required bool collective,
  });

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
    String dateOrder = 'terbaru',
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
          'urut_tanggal': dateOrder,
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
  Future<String> create(
    GradeComponentFormValue value, {
    required bool collective,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        collective ? 'komponen-nilai/kolektif' : 'komponen-nilai',
        data: value.toJson(collective: collective),
      );
      return response.data?['pesan'] as String? ??
          'Komponen nilai berhasil ditambahkan.';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> update({
    required int id,
    required GradeComponentFormValue value,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'komponen-nilai/$id',
        data: value.toJson(),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('komponen-nilai/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final gradeComponentRemoteDataSourceProvider =
    Provider<GradeComponentRemoteDataSource>(
      (ref) => DioGradeComponentRemoteDataSource(ref.watch(dioProvider)),
    );
