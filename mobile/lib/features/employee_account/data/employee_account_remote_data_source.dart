import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_account/domain/employee_account.dart';

abstract interface class EmployeeAccountRemoteDataSource {
  Future<EmployeeAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<EmployeeAccountDetail> fetchAccount(int employeeId);

  Future<void> createAccount(int employeeId);

  Future<BulkAccountResult> createAllAccounts();

  Future<void> resetPassword(int employeeId);

  Future<void> updateStatus({required int employeeId, required bool active});

  Future<void> updateRoles({
    required int employeeId,
    required List<int> roleIds,
  });
}

final class DioEmployeeAccountRemoteDataSource
    implements EmployeeAccountRemoteDataSource {
  DioEmployeeAccountRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EmployeeAccountPage> fetchAccounts({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'akun-pegawai',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status_akun': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return EmployeeAccountPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<EmployeeAccountDetail> fetchAccount(int employeeId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'akun-pegawai/$employeeId',
      );
      return EmployeeAccountDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> createAccount(int employeeId) =>
      _request(path: 'akun-pegawai/$employeeId', method: 'POST');

  @override
  Future<BulkAccountResult> createAllAccounts() async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'akun-pegawai/buat-massal',
      );
      return BulkAccountResult.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> resetPassword(int employeeId) => _request(
    path: 'akun-pegawai/$employeeId/reset-kata-sandi',
    method: 'PATCH',
  );

  @override
  Future<void> updateStatus({required int employeeId, required bool active}) =>
      _request(
        path: 'akun-pegawai/$employeeId/status',
        method: 'PATCH',
        data: {'aktif': active},
      );

  @override
  Future<void> updateRoles({
    required int employeeId,
    required List<int> roleIds,
  }) => _request(
    path: 'akun-pegawai/$employeeId/peran',
    method: 'PATCH',
    data: {'peran_ids': roleIds},
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

final employeeAccountRemoteDataSourceProvider =
    Provider<EmployeeAccountRemoteDataSource>(
      (ref) => DioEmployeeAccountRemoteDataSource(ref.watch(dioProvider)),
    );
