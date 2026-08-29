import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_attendance_recap/domain/employee_attendance_recap.dart';

abstract interface class EmployeeAttendanceRecapRemoteDataSource {
  Future<EmployeeAttendanceRecapPage> fetch({
    required String date,
    String? employeeType,
    int? employeeId,
    required String employeeStatus,
    required String status,
    required String query,
    required int page,
  });
  Future<EmployeeAttendanceDetail> detail({
    required int employeeId,
    required String date,
  });
  Future<void> correct({
    required int employeeId,
    required String date,
    required EmployeeAttendanceCorrectionValue value,
  });
}

final class DioEmployeeAttendanceRecapRemoteDataSource
    implements EmployeeAttendanceRecapRemoteDataSource {
  DioEmployeeAttendanceRecapRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<EmployeeAttendanceRecapPage> fetch({
    required String date,
    String? employeeType,
    int? employeeId,
    required String employeeStatus,
    required String status,
    required String query,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-presensi-pegawai',
        queryParameters: {
          'tanggal': date,
          'jenis_pegawai': ?employeeType,
          'pegawai_id': ?employeeId,
          'status_pegawai': employeeStatus,
          'status': status,
          'halaman': page,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return EmployeeAttendanceRecapPage.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<EmployeeAttendanceDetail> detail({
    required int employeeId,
    required String date,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-presensi-pegawai/$employeeId',
        queryParameters: {'tanggal': date},
      );
      return EmployeeAttendanceDetail.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> correct({
    required int employeeId,
    required String date,
    required EmployeeAttendanceCorrectionValue value,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'rekap-presensi-pegawai/$employeeId/koreksi',
        data: {
          'tanggal': date,
          'status_kehadiran': value.status,
          'jam_masuk': value.status == 'hadir' ? value.checkInTime : null,
          'jam_pulang': value.status == 'hadir' ? value.checkOutTime : null,
          'catatan': value.notes.trim(),
        },
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final employeeAttendanceRecapRemoteDataSourceProvider =
    Provider<EmployeeAttendanceRecapRemoteDataSource>(
      (ref) =>
          DioEmployeeAttendanceRecapRemoteDataSource(ref.watch(dioProvider)),
    );
