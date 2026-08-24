import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student/domain/student.dart';

abstract interface class StudentRemoteDataSource {
  Future<StudentPage> fetchStudents({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<StudentDetail> fetchStudent(int id);
}

final class DioStudentRemoteDataSource implements StudentRemoteDataSource {
  DioStudentRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentPage> fetchStudents({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'siswa',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );

      return StudentPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentDetail> fetchStudent(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('siswa/$id');

      return StudentDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentRemoteDataSourceProvider = Provider<StudentRemoteDataSource>((
  ref,
) {
  return DioStudentRemoteDataSource(ref.watch(dioProvider));
});
