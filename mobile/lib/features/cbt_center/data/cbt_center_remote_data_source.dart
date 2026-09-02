import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';

abstract interface class CbtCenterRemoteDataSource {
  Future<CbtCenterData> fetch();
}

final class DioCbtCenterRemoteDataSource implements CbtCenterRemoteDataSource {
  DioCbtCenterRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<CbtCenterData> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('pusat-cbt');
      final data =
          response.data?['data'] as Map<String, dynamic>? ??
          <String, dynamic>{};
      return CbtCenterData.fromJson(data);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final cbtCenterRemoteDataSourceProvider = Provider<CbtCenterRemoteDataSource>(
  (ref) => DioCbtCenterRemoteDataSource(ref.watch(dioProvider)),
);
