import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/my_teaching_schedule/domain/my_teaching_schedule.dart';

abstract interface class MyTeachingScheduleRemoteDataSource {
  Future<MyTeachingSchedulePage> fetch({required int? academicYearId});
}

final class DioMyTeachingScheduleRemoteDataSource
    implements MyTeachingScheduleRemoteDataSource {
  DioMyTeachingScheduleRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<MyTeachingSchedulePage> fetch({required int? academicYearId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'jadwal-mengajar-saya',
        queryParameters: {'tahun_pelajaran_id': ?academicYearId},
      );
      return MyTeachingSchedulePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final myTeachingScheduleRemoteDataSourceProvider =
    Provider<MyTeachingScheduleRemoteDataSource>(
      (ref) => DioMyTeachingScheduleRemoteDataSource(ref.watch(dioProvider)),
    );
