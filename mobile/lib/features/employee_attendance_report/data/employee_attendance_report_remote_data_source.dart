import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_attendance_report/domain/employee_attendance_report.dart';

abstract interface class EmployeeAttendanceReportRemoteDataSource {
  Future<EmployeeAttendanceReportPage> fetch(Map<String, dynamic> query);
  Future<EmployeeAttendanceReportDetail> detail(
    int employeeId,
    Map<String, dynamic> query,
  );
}

final class DioEmployeeAttendanceReportRemoteDataSource
    implements EmployeeAttendanceReportRemoteDataSource {
  DioEmployeeAttendanceReportRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<EmployeeAttendanceReportPage> fetch(Map<String, dynamic> query) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-presensi-pegawai',
        queryParameters: query,
      );
      return EmployeeAttendanceReportPage.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  @override
  Future<EmployeeAttendanceReportDetail> detail(
    int employeeId,
    Map<String, dynamic> query,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-presensi-pegawai/$employeeId',
        queryParameters: query,
      );
      return EmployeeAttendanceReportDetail.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}

final employeeAttendanceReportRemoteDataSourceProvider =
    Provider<EmployeeAttendanceReportRemoteDataSource>(
      (ref) =>
          DioEmployeeAttendanceReportRemoteDataSource(ref.watch(dioProvider)),
    );
