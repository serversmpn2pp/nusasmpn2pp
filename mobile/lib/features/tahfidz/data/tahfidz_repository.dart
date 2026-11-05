import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';

import '../domain/tahfidz.dart';

class TahfidzRepository {
  TahfidzRepository(this._dio);
  final Dio _dio;
  Future<TfData> get(String path, [TfData? query]) =>
      call('GET', path, query: query);
  Future<TfData> call(
    String method,
    String path, {
    TfData? data,
    TfData? query,
  }) async {
    try {
      final response = await _dio.request<TfData>(
        path,
        data: data,
        queryParameters: query,
        options: Options(method: method),
      );
      return tfMap(response.data?['data']);
    } on DioException catch (error) {
      throw mapDioException(error);
    }
  }
}

final tahfidzRepositoryProvider = Provider(
  (ref) => TahfidzRepository(ref.watch(dioProvider)),
);
