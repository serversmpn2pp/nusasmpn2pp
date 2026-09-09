import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/bk_grade_assignment/domain/bk_grade_assignment.dart';

abstract interface class BkGradeAssignmentRemoteDataSource {
  Future<BkGradeAssignmentPage> fetch({int? academicYearId});
  Future<BkGradeAssignmentMutation> create(BkGradeAssignmentPayload payload);
  Future<BkGradeAssignmentMutation> end(int id);
}

final class DioBkGradeAssignmentRemoteDataSource
    implements BkGradeAssignmentRemoteDataSource {
  DioBkGradeAssignmentRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<BkGradeAssignmentPage> fetch({int? academicYearId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'penugasan-guru-bk-tingkat',
        queryParameters: {'tahun_pelajaran_id': ?academicYearId},
      );
      return BkGradeAssignmentPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<BkGradeAssignmentMutation> create(
    BkGradeAssignmentPayload payload,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'penugasan-guru-bk-tingkat',
        data: payload.toJson(),
      );
      return BkGradeAssignmentMutation.fromJson(response.data!);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<BkGradeAssignmentMutation> end(int id) async {
    try {
      final response = await _dio.delete<Map<String, dynamic>>(
        'penugasan-guru-bk-tingkat/$id',
      );
      return BkGradeAssignmentMutation.fromJson(response.data!);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final bkGradeAssignmentRemoteDataSourceProvider =
    Provider<BkGradeAssignmentRemoteDataSource>(
      (ref) => DioBkGradeAssignmentRemoteDataSource(ref.watch(dioProvider)),
    );
