import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/my_goods_request/domain/my_goods_request.dart';

abstract interface class MyGoodsRequestRemoteDataSource {
  Future<MyGoodsRequestPage> fetch({
    required String status,
    required int page,
    int perPage = 12,
  });
  Future<MyGoodsCatalogPage> catalog({
    required String query,
    required int page,
    int perPage = 20,
  });
  Future<MyGoodsRequestDetail> detail(int id);
  Future<MyGoodsRequestDetail> create(MyGoodsRequestFormValue value);
  Future<MyGoodsRequestDetail> cancel(int id);
}

final class DioMyGoodsRequestRemoteDataSource
    implements MyGoodsRequestRemoteDataSource {
  DioMyGoodsRequestRemoteDataSource(this._dio);
  final Dio _dio;
  @override
  Future<MyGoodsRequestPage> fetch({
    required String status,
    required int page,
    int perPage = 12,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengajuan-saya',
        queryParameters: {
          'status': status,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return MyGoodsRequestPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyGoodsCatalogPage> catalog({
    required String query,
    required int page,
    int perPage = 20,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengajuan-saya/katalog',
        queryParameters: {
          if (query.trim().isNotEmpty) 'kata_kunci': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return MyGoodsCatalogPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyGoodsRequestDetail> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengajuan-saya/$id',
      );
      return MyGoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyGoodsRequestDetail> create(MyGoodsRequestFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'pengajuan-saya',
        data: {
          'barang_id': value.goodsId,
          'jumlah': value.quantity,
          'tanggal_dibutuhkan': _date(value.requiredDate),
          'rencana_kembali': value.plannedReturn == null
              ? null
              : _date(value.plannedReturn!),
          'tujuan': value.purpose.trim(),
        },
      );
      return MyGoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<MyGoodsRequestDetail> cancel(int id) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'pengajuan-saya/$id/batalkan',
      );
      return MyGoodsRequestDetail.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
final myGoodsRequestRemoteDataSourceProvider =
    Provider<MyGoodsRequestRemoteDataSource>(
      (ref) => DioMyGoodsRequestRemoteDataSource(ref.watch(dioProvider)),
    );
