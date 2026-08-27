import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';

abstract interface class MyGradesRemoteDataSource {
  Future<MyGradesPage> fetch({
    required int? academicYearId,
    required String semester,
  });
}

final class DioMyGradesRemoteDataSource implements MyGradesRemoteDataSource {
  DioMyGradesRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<MyGradesPage> fetch({
    required int? academicYearId,
    required String semester,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'nilai-saya',
        queryParameters: {
          'tahun_pelajaran_id': ?academicYearId,
          'semester': semester,
        },
      );
      return MyGradesPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final myGradesRemoteDataSourceProvider = Provider<MyGradesRemoteDataSource>(
  (ref) => DioMyGradesRemoteDataSource(ref.watch(dioProvider)),
);
