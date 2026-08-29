import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/employee_attendance_settings/domain/employee_attendance_settings.dart';

abstract interface class EmployeeAttendanceSettingsRemoteDataSource {
  Future<EmployeeAttendanceSettingsCatalog> fetch({
    required String query,
    required String day,
    required String scope,
    required String status,
  });

  Future<void> create(EmployeeAttendanceSettingsFormValue value);

  Future<void> update({
    required int id,
    required EmployeeAttendanceSettingsFormValue value,
  });
}

final class DioEmployeeAttendanceSettingsRemoteDataSource
    implements EmployeeAttendanceSettingsRemoteDataSource {
  DioEmployeeAttendanceSettingsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<EmployeeAttendanceSettingsCatalog> fetch({
    required String query,
    required String day,
    required String scope,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-presensi-pegawai',
        queryParameters: {
          if (query.trim().isNotEmpty) 'q': query.trim(),
          'hari': day,
          'cakupan': scope,
          'status': status,
        },
      );
      return EmployeeAttendanceSettingsCatalog.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(EmployeeAttendanceSettingsFormValue value) =>
      _send(path: 'pengaturan-presensi-pegawai', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required EmployeeAttendanceSettingsFormValue value,
  }) => _send(
    path: 'pengaturan-presensi-pegawai/$id',
    method: 'PATCH',
    value: value,
  );

  Future<void> _send({
    required String path,
    required String method,
    required EmployeeAttendanceSettingsFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama_jadwal': value.name.trim(),
          'cakupan': value.scope,
          'jenis_pegawai': value.scope == 'jenis_pegawai'
              ? _text(value.employeeType)
              : null,
          'pegawai_id': value.scope == 'pegawai' ? value.employeeId : null,
          'hari': value.day,
          'jam_scan_masuk_mulai': value.checkInScanStart,
          'jam_masuk': value.checkInTime,
          'jam_scan_masuk_selesai': value.checkInScanEnd,
          'jam_scan_pulang_mulai': value.checkOutScanStart,
          'jam_pulang': value.checkOutTime,
          'jam_scan_pulang_selesai': value.checkOutScanEnd,
          'aktif': value.active,
          'keterangan': _text(value.notes),
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final employeeAttendanceSettingsRemoteDataSourceProvider =
    Provider<EmployeeAttendanceSettingsRemoteDataSource>(
      (ref) =>
          DioEmployeeAttendanceSettingsRemoteDataSource(ref.watch(dioProvider)),
    );
