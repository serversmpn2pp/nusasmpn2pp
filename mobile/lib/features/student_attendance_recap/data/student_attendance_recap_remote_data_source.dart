import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_attendance_recap/domain/student_attendance_recap.dart';

abstract interface class StudentAttendanceRecapRemoteDataSource {
  Future<StudentAttendanceRecapPage> fetch({
    required String date,
    int? academicYearId,
    int? classId,
    required String status,
    required String query,
    required int page,
  });
  Future<StudentAttendanceDetail> detail({
    required int classMemberId,
    required String date,
  });
  Future<StudentAttendanceWhatsAppMessage> whatsAppMessage({
    required String date,
    int? academicYearId,
    int? classId,
  });
  Future<void> correct({
    required int classMemberId,
    required String date,
    required AttendanceCorrectionValue value,
  });
}

final class DioStudentAttendanceRecapRemoteDataSource
    implements StudentAttendanceRecapRemoteDataSource {
  DioStudentAttendanceRecapRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentAttendanceRecapPage> fetch({
    required String date,
    int? academicYearId,
    int? classId,
    required String status,
    required String query,
    required int page,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-presensi-siswa',
        queryParameters: {
          'tanggal': date,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
          'status': status,
          'halaman': page,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return StudentAttendanceRecapPage.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAttendanceDetail> detail({
    required int classMemberId,
    required String date,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-presensi-siswa/$classMemberId',
        queryParameters: {'tanggal': date},
      );
      return StudentAttendanceDetail.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StudentAttendanceWhatsAppMessage> whatsAppMessage({
    required String date,
    int? academicYearId,
    int? classId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'rekap-presensi-siswa/pesan-whatsapp',
        queryParameters: {
          'tanggal': date,
          'tahun_pelajaran_id': ?academicYearId,
          'kelas_id': ?classId,
        },
      );
      return StudentAttendanceWhatsAppMessage.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> correct({
    required int classMemberId,
    required String date,
    required AttendanceCorrectionValue value,
  }) async {
    try {
      await _dio.patch<Map<String, dynamic>>(
        'rekap-presensi-siswa/$classMemberId/koreksi',
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

final studentAttendanceRecapRemoteDataSourceProvider =
    Provider<StudentAttendanceRecapRemoteDataSource>(
      (ref) =>
          DioStudentAttendanceRecapRemoteDataSource(ref.watch(dioProvider)),
    );
