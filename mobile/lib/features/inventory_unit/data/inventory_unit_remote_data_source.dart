import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_unit/domain/inventory_unit.dart';

abstract interface class InventoryUnitRemoteDataSource {
  Future<InventoryUnitPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(InventoryUnitFormValue value);

  Future<void> update({required int id, required InventoryUnitFormValue value});

  Future<void> deactivate(int id);
}

final class DioInventoryUnitRemoteDataSource
    implements InventoryUnitRemoteDataSource {
  DioInventoryUnitRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryUnitPage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'satuan-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return InventoryUnitPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(InventoryUnitFormValue value) =>
      _save(path: 'satuan-barang', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required InventoryUnitFormValue value,
  }) => _save(path: 'satuan-barang/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('satuan-barang/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required InventoryUnitFormValue value,
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

final inventoryUnitRemoteDataSourceProvider =
    Provider<InventoryUnitRemoteDataSource>(
      (ref) => DioInventoryUnitRemoteDataSource(ref.watch(dioProvider)),
    );
