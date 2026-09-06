import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/goods_request/domain/goods_request.dart';

abstract interface class GoodsRequestRemoteDataSource {
  Future<GoodsRequestPage> fetch({
    required String query,
    required String type,
    required String status,
    required int page,
    int perPage = 15,
  });
  Future<GoodsRequestDetail> detail(int id);
  Future<GoodsRequestDetail> fulfill(int id, GoodsRequestFulfillValue value);
  Future<GoodsRequestDetail> reject(int id, String reason);
}

final class DioGoodsRequestRemoteDataSource
    implements GoodsRequestRemoteDataSource {
  DioGoodsRequestRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<GoodsRequestPage> fetch({
    required String query,
    required String type,
    required String status,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengajuan-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'kata_kunci': query.trim(),
          'jenis': type,
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GoodsRequestPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsRequestDetail> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengajuan-barang/$id',
      );
      return GoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsRequestDetail> fulfill(
    int id,
    GoodsRequestFulfillValue value,
  ) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pengajuan-barang/$id/penuhi',
        data: {
          'unit_barang_ids': value.unitIds,
          'lokasi_barang_id': value.locationId,
          'catatan_petugas': value.notes?.trim().isEmpty == true
              ? null
              : value.notes?.trim(),
        },
      );
      return GoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsRequestDetail> reject(int id, String reason) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pengajuan-barang/$id/tolak',
        data: {'catatan_petugas': reason.trim()},
      );
      return GoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final goodsRequestRemoteDataSourceProvider =
    Provider<GoodsRequestRemoteDataSource>(
      (ref) => DioGoodsRequestRemoteDataSource(ref.watch(dioProvider)),
    );
