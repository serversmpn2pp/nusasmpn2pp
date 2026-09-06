import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/asset_unit/domain/asset_unit.dart';

abstract interface class AssetUnitRemoteDataSource {
  Future<AssetUnitPage> fetch({
    required String query,
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
    required int page,
    int perPage = 15,
  });

  Future<AssetUnit> detail(int id);

  Future<void> create(AssetUnitFormValue value);

  Future<void> update({required int id, required AssetUnitFormValue value});

  Future<void> deactivate(int id);
}

final class DioAssetUnitRemoteDataSource implements AssetUnitRemoteDataSource {
  DioAssetUnitRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<AssetUnitPage> fetch({
    required String query,
    required String dataStatus,
    required String condition,
    required String unitStatus,
    required int? goodsId,
    required int? locationId,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'unit-aset',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status': dataStatus,
          'kondisi': condition,
          'status_unit': unitStatus,
          'barang_id': ?goodsId,
          'lokasi_barang_id': ?locationId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return AssetUnitPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<AssetUnit> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('unit-aset/$id');
      final data = response.data!['data'] as Map<String, dynamic>;
      return AssetUnit.fromJson(data['unit'] as Map<String, dynamic>);
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<void> create(AssetUnitFormValue value) =>
      _save(path: 'unit-aset', method: 'POST', value: value);

  @override
  Future<void> update({required int id, required AssetUnitFormValue value}) =>
      _save(path: 'unit-aset/$id', method: 'PATCH', value: value);

  @override
  Future<void> deactivate(int id) async {
    try {
      await _dio.delete<Map<String, dynamic>>('unit-aset/$id');
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  Future<void> _save({
    required String path,
    required String method,
    required AssetUnitFormValue value,
  }) async {
    try {
      await _dio.request<Map<String, dynamic>>(
        path,
        data: {
          'barang_id': value.goodsId,
          'jumlah_unit': value.quantity,
          'lokasi_barang_id': value.locationId,
          'nomor_seri': _text(value.serialNumber),
          'merek': _text(value.brand),
          'tipe': _text(value.model),
          'kondisi': value.condition,
          'status_unit': value.unitStatus,
          'tanggal_perolehan': _date(value.acquisitionDate),
          'tahun_perolehan': value.acquisitionYear,
          'sumber_perolehan_barang_id': value.sourceId,
          'harga_perolehan': value.acquisitionPrice,
          'keterangan': _text(value.notes),
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

String? _date(DateTime? value) => value == null
    ? null
    : '${value.year.toString().padLeft(4, '0')}-'
          '${value.month.toString().padLeft(2, '0')}-'
          '${value.day.toString().padLeft(2, '0')}';

final assetUnitRemoteDataSourceProvider = Provider<AssetUnitRemoteDataSource>(
  (ref) => DioAssetUnitRemoteDataSource(ref.watch(dioProvider)),
);
