import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_scan_status/domain/employee_scan_status.dart';

abstract interface class EmployeeScanStatusRemoteDataSource {
  Future<EmployeeScanStatusDashboard> fetch({
    required String? employeeType,
    required String status,
    required String query,
  });
}

final class DioEmployeeScanStatusRemoteDataSource
    implements EmployeeScanStatusRemoteDataSource {
  DioEmployeeScanStatusRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EmployeeScanStatusDashboard> fetch({
    required String? employeeType,
    required String status,
    required String query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'status-scan-presensi-pegawai',
        queryParameters: {
          if (employeeType?.trim().isNotEmpty == true)
            'jenis_pegawai': employeeType!.trim(),
          'status': status,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return EmployeeScanStatusDashboard.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final employeeScanStatusRemoteDataSourceProvider =
    Provider<EmployeeScanStatusRemoteDataSource>(
      (ref) => DioEmployeeScanStatusRemoteDataSource(ref.watch(dioProvider)),
    );
