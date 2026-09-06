import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/inventory_settings/domain/inventory_settings.dart';

abstract interface class InventorySettingsRemoteDataSource {
  Future<InventorySettings> fetch();

  Future<InventorySettings> update(InventorySettingsFormValue value);
}

final class DioInventorySettingsRemoteDataSource
    implements InventorySettingsRemoteDataSource {
  DioInventorySettingsRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<InventorySettings> fetch() async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengaturan-inventaris',
      );
      return InventorySettings.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<InventorySettings> update(InventorySettingsFormValue value) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pengaturan-inventaris',
        data: {
          'awalan_nomor_aset': value.assetNumberPrefix.trim(),
          'akhiran_nomor_aset': value.assetNumberSuffix.trim(),
          'nama_pemilik': value.ownerName.trim(),
          'jumlah_digit_id_internal': value.internalIdDigits,
        },
      );
      return InventorySettings.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final inventorySettingsRemoteDataSourceProvider =
    Provider<InventorySettingsRemoteDataSource>(
      (ref) => DioInventorySettingsRemoteDataSource(ref.watch(dioProvider)),
    );
