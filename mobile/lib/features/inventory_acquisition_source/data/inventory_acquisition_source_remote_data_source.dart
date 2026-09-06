import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_acquisition_source/domain/inventory_acquisition_source.dart';

abstract interface class InventoryAcquisitionSourceRemoteDataSource {
  Future<InventoryAcquisitionSourcePage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  });

  Future<void> create(InventoryAcquisitionSourceFormValue value);

  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioInventoryAcquisitionSourceRemoteDataSource
    implements InventoryAcquisitionSourceRemoteDataSource {
  DioInventoryAcquisitionSourceRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryAcquisitionSourcePage> fetch({
    required String query,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'sumber-perolehan',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return InventoryAcquisitionSourcePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(InventoryAcquisitionSourceFormValue value) =>
      _save(path: 'sumber-perolehan', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
  }) => _save(path: 'sumber-perolehan/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('sumber-perolehan/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required InventoryAcquisitionSourceFormValue value,
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

final inventoryAcquisitionSourceRemoteDataSourceProvider =
    Provider<InventoryAcquisitionSourceRemoteDataSource>(
      (ref) =>
          DioInventoryAcquisitionSourceRemoteDataSource(ref.watch(dioProvider)),
    );
