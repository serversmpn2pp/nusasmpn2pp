import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

abstract interface class MenuRemoteDataSource {
  Future<MenuCatalog> fetchCatalog();
}

final class DioMenuRemoteDataSource implements MenuRemoteDataSource {
  DioMenuRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<MenuCatalog> fetchCatalog() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('menu');

      return MenuCatalog.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final menuRemoteDataSourceProvider = Provider<MenuRemoteDataSource>((ref) {
  return DioMenuRemoteDataSource(ref.watch(dioProvider));
});
