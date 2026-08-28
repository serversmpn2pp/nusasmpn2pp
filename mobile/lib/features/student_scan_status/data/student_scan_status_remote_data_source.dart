import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/student_scan_status/domain/student_scan_status.dart';

abstract interface class StudentScanStatusRemoteDataSource {
  Future<StudentScanStatusDashboard> fetch({
    required int? classId,
    required String status,
    required String query,
  });
}

final class DioStudentScanStatusRemoteDataSource
    implements StudentScanStatusRemoteDataSource {
  DioStudentScanStatusRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<StudentScanStatusDashboard> fetch({
    required int? classId,
    required String status,
    required String query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'status-scan-presensi-siswa',
        queryParameters: {
          'kelas_id': ?classId,
          'status': status,
          if (query.trim().isNotEmpty) 'cari': query.trim(),
        },
      );
      return StudentScanStatusDashboard.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final studentScanStatusRemoteDataSourceProvider =
    Provider<StudentScanStatusRemoteDataSource>(
      (ref) => DioStudentScanStatusRemoteDataSource(ref.watch(dioProvider)),
    );
