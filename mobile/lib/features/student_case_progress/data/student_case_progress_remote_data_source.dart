import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

abstract interface class StudentCaseProgressRemoteDataSource {
  Future<StudentCaseProgressPage> fetch({required int page});
  Future<StudentCaseDetail> fetchDetail(int id);
}

final class DioStudentCaseProgressRemoteDataSource
    implements StudentCaseProgressRemoteDataSource {
  DioStudentCaseProgressRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentCaseProgressPage> fetch({required int page}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'progress-kasus-saya',
        queryParameters: {'halaman': page, 'per_halaman': 10},
      );
      return StudentCaseProgressPage.fromJson(
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
        'progress-kasus-saya/$id',
      );
      return StudentCaseDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentCaseProgressRemoteDataSourceProvider =
    Provider<StudentCaseProgressRemoteDataSource>(
      (ref) => DioStudentCaseProgressRemoteDataSource(ref.watch(dioProvider)),
    );
