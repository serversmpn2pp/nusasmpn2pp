import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/goods_catalog/domain/goods_catalog.dart';

abstract interface class GoodsCatalogRemoteDataSource {
  Future<GoodsCatalogPage> fetch({
    required GoodsCatalogFilter filter,
    required int page,
    int perPage = 12,
  });
  Future<GoodsCatalogDetail> detail(int id);
}

final class DioGoodsCatalogRemoteDataSource
    implements GoodsCatalogRemoteDataSource {
  DioGoodsCatalogRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<GoodsCatalogPage> fetch({
    required GoodsCatalogFilter filter,
    required int page,
    int perPage = 12,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'katalog-barang',
        queryParameters: {
          if (filter.query.trim().isNotEmpty) 'kata_kunci': filter.query.trim(),
          if (filter.categoryId != null)
            'kategori_barang_id': filter.categoryId,
          'jenis_barang': filter.type,
          'ketersediaan': filter.availability,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GoodsCatalogPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsCatalogDetail> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'katalog-barang/$id',
      );
      return GoodsCatalogDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final goodsCatalogRemoteDataSourceProvider =
    Provider<GoodsCatalogRemoteDataSource>(
      (ref) => DioGoodsCatalogRemoteDataSource(ref.watch(dioProvider)),
    );
