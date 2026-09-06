import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_category/domain/inventory_category.dart';

abstract interface class InventoryCategoryRemoteDataSource {
  Future<InventoryCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(InventoryCategoryFormValue value);

  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioInventoryCategoryRemoteDataSource
    implements InventoryCategoryRemoteDataSource {
  DioInventoryCategoryRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'kategori-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return InventoryCategoryPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(InventoryCategoryFormValue value) =>
      _save(path: 'kategori-barang', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  }) => _save(path: 'kategori-barang/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('kategori-barang/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required InventoryCategoryFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama': value.name.trim(),
          'kode': value.code.trim(),
          'deskripsi': _text(value.description),
          'aktif': value.active,
        },
        options: Options(method: method),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final inventoryCategoryRemoteDataSourceProvider =
    Provider<InventoryCategoryRemoteDataSource>(
      (ref) => DioInventoryCategoryRemoteDataSource(ref.watch(dioProvider)),
    );
