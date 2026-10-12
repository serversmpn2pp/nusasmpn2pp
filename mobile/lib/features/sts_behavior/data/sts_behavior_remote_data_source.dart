import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/sts_behavior/domain/sts_behavior.dart';

abstract interface class StsBehaviorRemoteDataSource {
  Future<StsBehaviorPage> fetch({int? activityId, int? classId});
  Future<void> saveContent(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  );
  Future<void> saveReviews(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  );
}

final class DioStsBehaviorRemoteDataSource
    implements StsBehaviorRemoteDataSource {
  DioStsBehaviorRemoteDataSource(this._dio);
  final Dio _dio;
  static const _path = 'lampiran-perilaku-sts';

  @override
  Future<StsBehaviorPage> fetch({int? activityId, int? classId}) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        _path,
        queryParameters: {'kegiatan_id': ?activityId, 'kelas_id': ?classId},
      );
      return StsBehaviorPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }

  @override
  Future<void> saveContent(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  ) => _put('$_path/$activityId/$classId/isi', data);

  @override
  Future<void> saveReviews(
    int activityId,
    int classId,
    Map<String, dynamic> data,
  ) => _put('$_path/$activityId/$classId/pemeriksaan', data);

  Future<void> _put(String path, Map<String, dynamic> data) async {
    try {
      await _dio.put<Map<String, dynamic>>(path, data: data);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}

final stsBehaviorRemoteDataSourceProvider =
    Provider<StsBehaviorRemoteDataSource>(
      (ref) => DioStsBehaviorRemoteDataSource(ref.watch(dioProvider)),
    );
