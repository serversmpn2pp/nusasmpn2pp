import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/parent_child_guidance/domain/parent_child_guidance.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

abstract interface class ParentChildGuidanceRemoteDataSource {
  Future<ParentChildGuidancePage> fetch({
    required ParentChildGuidanceTab tab,
    required int page,
    int? studentId,
    int? academicYearId,
  });

  Future<StudentCaseDetail> fetchDetail(int id);
}

final class DioParentChildGuidanceRemoteDataSource
    implements ParentChildGuidanceRemoteDataSource {
  DioParentChildGuidanceRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ParentChildGuidancePage> fetch({
    required ParentChildGuidanceTab tab,
    required int page,
    int? studentId,
    int? academicYearId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pembinaan-poin-anak',
        queryParameters: {
          'tab': tab.apiValue,
          'halaman': page,
          'per_halaman': 10,
          'siswa_id': ?studentId,
          'tahun_pelajaran_id': ?academicYearId,
        },
      );
      return ParentChildGuidancePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentCaseDetail> fetchDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pembinaan-poin-anak/$id',
      );
      return StudentCaseDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final parentChildGuidanceRemoteDataSourceProvider =
    Provider<ParentChildGuidanceRemoteDataSource>(
      (ref) => DioParentChildGuidanceRemoteDataSource(ref.watch(dioProvider)),
    );
