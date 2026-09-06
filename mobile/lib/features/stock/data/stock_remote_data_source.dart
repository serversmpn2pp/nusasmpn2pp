import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/stock/domain/stock.dart';

abstract interface class StockRemoteDataSource {
  Future<StockBalancePage> fetchBalances({
    required String query,
    required String status,
    required int? categoryId,
    required int? locationId,
    required int page,
    int perPage = 15,
  });

  Future<StockMovementPage> fetchMovements({
    required String query,
    required String type,
    required int? goodsId,
    required int? locationId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  });

  Future<StockMovement> movementDetail(int id);
  Future<StockMovement> createMovement(StockMovementFormValue value);
}

final class DioStockRemoteDataSource implements StockRemoteDataSource {
  DioStockRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<StockBalancePage> fetchBalances({
    required String query,
    required String status,
    required int? categoryId,
    required int? locationId,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'saldo-stok',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'status_stok': status,
          'kategori_barang_id': ?categoryId,
          'lokasi_barang_id': ?locationId,
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StockBalancePage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StockMovementPage> fetchMovements({
    required String query,
    required String type,
    required int? goodsId,
    required int? locationId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'mutasi-stok',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'jenis_mutasi': type,
          'barang_id': ?goodsId,
          'lokasi_barang_id': ?locationId,
          if (startDate != null) 'tanggal_mulai': _date(startDate),
          if (endDate != null) 'tanggal_selesai': _date(endDate),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return StockMovementPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StockMovement> movementDetail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>('mutasi-stok/$id');
      return StockMovement.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<StockMovement> createMovement(StockMovementFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'mutasi-stok',
        data: {
          'barang_id': value.goodsId,
          'lokasi_barang_id': value.locationId,
          'jenis_mutasi': value.type,
          'kategori_mutasi': value.category,
          'tanggal_mutasi': _date(value.date),
          'jumlah': value.quantity,
          'referensi': _text(value.reference),
          'keterangan': _text(value.notes),
        },
      );
      return StockMovement.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final stockRemoteDataSourceProvider = Provider<StockRemoteDataSource>(
  (ref) => DioStockRemoteDataSource(ref.watch(dioProvider)),
);
