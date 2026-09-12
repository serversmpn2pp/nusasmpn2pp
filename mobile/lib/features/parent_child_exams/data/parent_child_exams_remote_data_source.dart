import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/parent_child_exams/domain/parent_child_exams.dart';

abstract interface class ParentChildExamsRemoteDataSource {
  Future<ParentChildExamsPage> fetch({int? studentId});
}

final class DioParentChildExamsRemoteDataSource
    implements ParentChildExamsRemoteDataSource {
  DioParentChildExamsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<ParentChildExamsPage> fetch({int? studentId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'ujian-anak-saya',
        queryParameters: {'siswa_id': ?studentId},
      );
      return ParentChildExamsPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final parentChildExamsRemoteDataSourceProvider =
    Provider<ParentChildExamsRemoteDataSource>(
      (ref) => DioParentChildExamsRemoteDataSource(ref.watch(dioProvider)),
    );
