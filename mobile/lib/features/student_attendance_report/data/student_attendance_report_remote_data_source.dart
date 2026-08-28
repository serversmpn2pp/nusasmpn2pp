import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_attendance_report/domain/student_attendance_report.dart';

abstract interface class StudentAttendanceReportRemoteDataSource {
  Future<StudentAttendanceReportPage> fetch(Map<String, dynamic> query);
  Future<StudentAttendanceReportDetail> detail(
    int classMemberId,
    Map<String, dynamic> query,
  );
  Future<AttendanceReportDownload> download(Map<String, dynamic> query);
}

final class DioStudentAttendanceReportRemoteDataSource
    implements StudentAttendanceReportRemoteDataSource {
  DioStudentAttendanceReportRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StudentAttendanceReportPage> fetch(Map<String, dynamic> query) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-presensi-siswa',
        queryParameters: query,
      );
      return StudentAttendanceReportPage.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  @override
  Future<StudentAttendanceReportDetail> detail(
    int classMemberId,
    Map<String, dynamic> query,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'laporan-presensi-siswa/$classMemberId',
        queryParameters: query,
      );
      return StudentAttendanceReportDetail.fromJson(
        Map<String, dynamic>.from(response.data!['data'] as Map),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  @override
  Future<AttendanceReportDownload> download(Map<String, dynamic> query) async {
    try {
      final response = await _dio.get<List<int>>(
        'laporan-presensi-siswa/export',
        queryParameters: query,
        options: Options(responseType: ResponseType.bytes),
      );
      final disposition = response.headers.value('content-disposition') ?? '';
      final match = RegExp(r'''filename\*?=(?:UTF-8'')?["']?([^"';]+)''')
          .firstMatch(disposition);
      final name = match == null
          ? 'laporan-presensi-siswa.xlsx'
          : Uri.decodeComponent(match.group(1)!);
      return AttendanceReportDownload(
        fileName: name,
        bytes: Uint8List.fromList(response.data ?? const []),
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}

final studentAttendanceReportRemoteDataSourceProvider =
    Provider<StudentAttendanceReportRemoteDataSource>(
      (ref) =>
          DioStudentAttendanceReportRemoteDataSource(ref.watch(dioProvider)),
    );
