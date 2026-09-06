import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';

abstract interface class InventoryGoodsRemoteDataSource {
  Future<InventoryGoodsPage> fetch({
    required String query,
    required String status,
    required String type,
    required int? categoryId,
    required int page,
    int perPage = 15,
  });

  Future<void> create(InventoryGoodsFormValue value);

  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioInventoryGoodsRemoteDataSource
    implements InventoryGoodsRemoteDataSource {
  DioInventoryGoodsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryGoodsPage> fetch({
    required String query,
    required String status,
    required String type,
    required int? categoryId,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'jenis_barang': type,
          'kategori_barang_id': ?categoryId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return InventoryGoodsPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(InventoryGoodsFormValue value) =>
      _save(path: 'barang', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  }) => _save(path: 'barang/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('barang/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required InventoryGoodsFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama': value.name.trim(),
          'kode': _text(value.code),
          'kategori_barang_id': value.categoryId,
          'satuan_barang_id': value.unitId,
          'lokasi_penyimpanan_id': value.locationId,
          'jenis_barang': value.type,
          'stok_minimum': value.minimumStock,
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

final inventoryGoodsRemoteDataSourceProvider =
    Provider<InventoryGoodsRemoteDataSource>(
      (ref) => DioInventoryGoodsRemoteDataSource(ref.watch(dioProvider)),
    );
