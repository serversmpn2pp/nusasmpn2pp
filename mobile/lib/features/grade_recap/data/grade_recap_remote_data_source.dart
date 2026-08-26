import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/grade_recap/domain/grade_recap.dart';

abstract interface class GradeRecapRemoteDataSource {
  Future<GradeRecapPage> fetch({
    required int? assignmentId,
    required String semester,
  });
}

final class DioGradeRecapRemoteDataSource
    implements GradeRecapRemoteDataSource {
  DioGradeRecapRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<GradeRecapPage> fetch({
    required int? assignmentId,
    required String semester,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-nilai-rapor',
        queryParameters: {
          'guru_mata_pelajaran_id': ?assignmentId,
          'semester': semester,
        },
      );
      return GradeRecapPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final gradeRecapRemoteDataSourceProvider = Provider<GradeRecapRemoteDataSource>(
  (ref) => DioGradeRecapRemoteDataSource(ref.watch(dioProvider)),
);
