import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_account/domain/student_account.dart';

abstract interface class StudentAccountRemoteDataSource {
  Future<StudentAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int? classId,
    required int page,
    int perPage = 15,
  });

  Future<StudentAccountDetail> fetchAccount(int studentId);

  Future<void> createAccount(int studentId);

  Future<BulkStudentAccountResult> createClassAccounts(int classId);

  Future<void> resetPassword(int studentId);

  Future<void> updateStatus({required int studentId, required bool active});
}

final class DioStudentAccountRemoteDataSource
    implements StudentAccountRemoteDataSource {
  DioStudentAccountRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int? classId,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'akun-siswa',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status_akun': status,
          'kelas_id': ?classId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StudentAccountPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAccountDetail> fetchAccount(int studentId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'akun-siswa/$studentId',
      );
      return StudentAccountDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> createAccount(int studentId) =>
      _request(path: 'akun-siswa/$studentId', method: 'POST');

  @override
  Future<BulkStudentAccountResult> createClassAccounts(int classId) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'akun-siswa/kelas/$classId/buat-massal',
      );
      return BulkStudentAccountResult.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> resetPassword(int studentId) =>
      _request(path: 'akun-siswa/$studentId/reset-kata-sandi', method: 'PATCH');

  @override
  Future<void> updateStatus({required int studentId, required bool active}) =>
      _request(
        path: 'akun-siswa/$studentId/status',
        method: 'PATCH',
        data: {'aktif': active},
      );

  Future<void> _request({
    required String path,
    required String method,
    Map<String, dynamic>? data,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: data,
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentAccountRemoteDataSourceProvider =
    Provider<StudentAccountRemoteDataSource>(
      (ref) => DioStudentAccountRemoteDataSource(ref.watch(dioProvider)),
    );
