import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/my_attendance/domain/my_attendance.dart';

abstract interface class MyAttendanceRemoteDataSource {
  Future<MyAttendancePage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  });
}

final class DioMyAttendanceRemoteDataSource
    implements MyAttendanceRemoteDataSource {
  DioMyAttendanceRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<MyAttendancePage> fetch({
    required int? studentId,
    required int? academicYearId,
    required String? month,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kehadiran-saya',
        queryParameters: {
          'siswa_id': ?studentId,
          'tahun_pelajaran_id': ?academicYearId,
          'bulan': ?month,
        },
      );
      return MyAttendancePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final myAttendanceRemoteDataSourceProvider =
    Provider<MyAttendanceRemoteDataSource>(
      (ref) => DioMyAttendanceRemoteDataSource(ref.watch(dioProvider)),
    );
