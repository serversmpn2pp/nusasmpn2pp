import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_location/domain/inventory_location.dart';

abstract interface class InventoryLocationRemoteDataSource {
  Future<InventoryLocationPage> fetch({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  });

  Future<void> create(InventoryLocationFormValue value);

  Future<void> update({
    required int id,
    required InventoryLocationFormValue value,
  });

  Future<void> deactivate(int id);
}

final class DioInventoryLocationRemoteDataSource
    implements InventoryLocationRemoteDataSource {
  DioInventoryLocationRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryLocationPage> fetch({
    required String query,
    required String status,
    required String type,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'lokasi-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': status,
          'jenis': type,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return InventoryLocationPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(InventoryLocationFormValue value) =>
      _save(path: 'lokasi-barang', method: 'POST', value: value);

  @override
  Future<void> update({
    required int id,
    required InventoryLocationFormValue value,
  }) => _save(path: 'lokasi-barang/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('lokasi-barang/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required InventoryLocationFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'nama': value.name.trim(),
          'kode': value.code.trim(),
          'jenis': value.type,
          'penanggung_jawab_pegawai_id': value.responsibleEmployeeId,
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

final inventoryLocationRemoteDataSourceProvider =
    Provider<InventoryLocationRemoteDataSource>(
      (ref) => DioInventoryLocationRemoteDataSource(ref.watch(dioProvider)),
    );
