import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_label/domain/inventory_label.dart';

abstract interface class InventoryLabelRemoteDataSource {
  Future<InventoryLabelPage> fetch(InventoryLabelFilters filters);
}

final class DioInventoryLabelRemoteDataSource
    implements InventoryLabelRemoteDataSource {
  DioInventoryLabelRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventoryLabelPage> fetch(InventoryLabelFilters filters) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'label-inventaris',
        queryParameters: {
          if (filters.type.isNotEmpty) 'jenis_label': filters.type,
          'penerimaan_barang_id': ?filters.receiptId,
          'tahun_perolehan': ?filters.acquisitionYear,
          'kategori_barang_id': ?filters.categoryId,
          'barang_id': ?filters.goodsId,
          'lokasi_barang_id': ?filters.locationId,
        },
      );
      return InventoryLabelPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final inventoryLabelRemoteDataSourceProvider =
    Provider<InventoryLabelRemoteDataSource>(
      (ref) => DioInventoryLabelRemoteDataSource(ref.watch(dioProvider)),
    );
