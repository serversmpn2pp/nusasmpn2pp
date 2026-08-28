import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';

class TeacherDutyRemoteDataSource {
  TeacherDutyRemoteDataSource(this._dio);
  final Dio _dio;

  Future<DutyScheduleCatalog> fetchSchedules({
    int? academicYearId,
    required String day,
    required String status,
    required String query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jadwal-guru-piket',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'hari': day,
          'status': status,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return DutyScheduleCatalog.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<DutyScheduleReference> fetchReference([int? academicYearId]) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jadwal-guru-piket/referensi',
        queryParameters: {'tahun_pelajaran_id': ?academicYearId},
      );
      return DutyScheduleReference.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> createSchedule(DutyScheduleFormValue value) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        'jadwal-guru-piket',
        data: _payload(value, bulk: true),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> updateSchedule(int id, DutyScheduleFormValue value) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'jadwal-guru-piket/$id',
        data: _payload(value, bulk: false),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> deleteSchedule(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('jadwal-guru-piket/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<MyDutyDashboard> fetchMyDuty({
    int? classId,
    required String status,
    required String query,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'piket-saya',
        queryParameters: {
          'kelas_id': ?classId,
          'status': status,
          'halaman': page,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return MyDutyDashboard.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> recordAttendance({
    required int classMemberId,
    required String status,
    required String notes,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'piket-saya/kehadiran/$classMemberId',
        data: {'status_kehadiran': status, 'catatan': notes.trim()},
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Map<String, dynamic> _payload(
    DutyScheduleFormValue value, {
    required bool bulk,
  }) => {
    'tahun_pelajaran_id': value.academicYearId,
    'hari': value.day,
    if (bulk)
      'pegawai_ids': value.teacherIds
    else
      'pegawai_id': value.teacherIds.single,
    'aktif': value.active,
    'keterangan': value.notes?.trim().isEmpty == true
        ? null
        : value.notes?.trim(),
  };
}

final teacherDutyRemoteDataSourceProvider =
    Provider<TeacherDutyRemoteDataSource>(
      (ref) => TeacherDutyRemoteDataSource(ref.watch(dioProvider)),
    );
