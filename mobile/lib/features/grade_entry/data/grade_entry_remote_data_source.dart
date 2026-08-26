import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/grade_entry/domain/grade_entry.dart';

abstract interface class GradeEntryRemoteDataSource {
  Future<GradeEntryPage> fetch({
    required int? assignmentId,
    required String semester,
    required int? componentId,
  });

  Future<String> save(GradeEntryFormValue value);

  Future<String> publish({required int assignmentId, required String semester});

  Future<String> unpublish({
    required int assignmentId,
    required String semester,
  });
}

final class DioGradeEntryRemoteDataSource
    implements GradeEntryRemoteDataSource {
  DioGradeEntryRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<GradeEntryPage> fetch({
    required int? assignmentId,
    required String semester,
    required int? componentId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'input-nilai',
        queryParameters: {
          'guru_mata_pelajaran_id': ?assignmentId,
          'semester': semester,
          'komponen_nilai_id': ?componentId,
        },
      );
      return GradeEntryPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<String> save(GradeEntryFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'input-nilai',
        data: value.toJson(),
      );
      return response.data?['pesan'] as String? ?? 'Nilai berhasil disimpan.';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<String> publish({
    required int assignmentId,
    required String semester,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'input-nilai/publikasi/$assignmentId/$semester',
      );
      return response.data?['pesan'] as String? ??
          'Nilai berhasil dipublikasikan.';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<String> unpublish({
    required int assignmentId,
    required String semester,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'input-nilai/publikasi/$assignmentId/$semester/draf',
      );
      return response.data?['pesan'] as String? ??
          'Nilai berhasil dikembalikan menjadi draf.';
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final gradeEntryRemoteDataSourceProvider = Provider<GradeEntryRemoteDataSource>(
  (ref) => DioGradeEntryRemoteDataSource(ref.watch(dioProvider)),
);
