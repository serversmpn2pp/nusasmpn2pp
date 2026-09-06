import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/exam_attendance/domain/exam_attendance.dart';

abstract interface class ExamAttendanceRemoteDataSource {
  Future<ExamAttendanceDashboard> fetch();

  Future<ExamAttendanceDetail> fetchDetail(int roomId);

  Future<ExamAttendanceScanResult> scan({
    required int roomId,
    required String rawValue,
  });

  Future<ExamAttendanceDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? note,
  });
}

final class DioExamAttendanceRemoteDataSource
    implements ExamAttendanceRemoteDataSource {
  DioExamAttendanceRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ExamAttendanceDashboard> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('presensi-ujian');
      return ExamAttendanceDashboard.fromJson(_map(response.data?['data']));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ExamAttendanceDetail> fetchDetail(int roomId) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'presensi-ujian/$roomId',
      );
      return ExamAttendanceDetail.fromJson(_map(response.data?['data']));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<ExamAttendanceScanResult> scan({
    required int roomId,
    required String rawValue,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'presensi-ujian/$roomId/scan',
        data: {'isi_scan': rawValue},
      );
      return ExamAttendanceScanResult.fromJson(_map(response.data?['data']));
    } on DioException catch (exception) {
      final response = exception.response;
      if (response?.statusCode == 422 && response?.data is Map) {
        final data = _map((response!.data as Map)['data']);
        if (data.containsKey('status')) {
          return ExamAttendanceScanResult.fromJson(data);
        }
      }
      throw mapDioException(exception);
    }
  }

  @override
  Future<ExamAttendanceDetail> changeAttendance({
    required int roomId,
    required int participantId,
    required String status,
    required String? note,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'presensi-ujian/$roomId/peserta/$participantId/kehadiran',
        data: {'status': status, 'catatan': ?note},
      );
      final payload = _map(response.data?['data']);
      return ExamAttendanceDetail.fromJson(_map(payload['detail']));
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final examAttendanceRemoteDataSourceProvider =
    Provider<ExamAttendanceRemoteDataSource>(
      (ref) => DioExamAttendanceRemoteDataSource(ref.watch(dioProvider)),
    );

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
