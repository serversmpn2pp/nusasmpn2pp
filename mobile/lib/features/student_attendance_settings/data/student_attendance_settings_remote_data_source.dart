import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';

abstract interface class StudentAttendanceSettingsRemoteDataSource {
  Future<StudentAttendanceSettingsCatalog> fetch({
    required String day,
    required String status,
  });

  Future<void> create(StudentAttendanceSettingsFormValue value);

  Future<void> update({
    required int id,
    required StudentAttendanceSettingsFormValue value,
  });
}

final class DioStudentAttendanceSettingsRemoteDataSource
    implements StudentAttendanceSettingsRemoteDataSource {
  DioStudentAttendanceSettingsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentAttendanceSettingsCatalog> fetch({
    required String day,
    required String status,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-presensi-siswa',
        queryParameters: {'hari': day, 'status': status},
      );
      return StudentAttendanceSettingsCatalog.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(StudentAttendanceSettingsFormValue value) =>
      _send(path: 'pengaturan-presensi-siswa', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required StudentAttendanceSettingsFormValue value,
  }) => _send(
    path: 'pengaturan-presensi-siswa/$id',
    method: 'PATCH',
    value: value,
  );

  Future<void> _send({
    required String path,
    required String method,
    required StudentAttendanceSettingsFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
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

final studentAttendanceSettingsRemoteDataSourceProvider =
    Provider<StudentAttendanceSettingsRemoteDataSource>(
      (ref) =>
          DioStudentAttendanceSettingsRemoteDataSource(ref.watch(dioProvider)),
    );
