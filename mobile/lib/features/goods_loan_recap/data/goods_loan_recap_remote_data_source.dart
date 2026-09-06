import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';

abstract interface class GoodsLoanRecapRemoteDataSource {
  Future<GoodsLoanRecapPage> fetch({
    required GoodsLoanRecapFilter filter,
    required int page,
    int perPage = 15,
  });
  Future<GoodsLoanRecapPage> document(GoodsLoanRecapFilter filter);
}

final class DioGoodsLoanRecapRemoteDataSource
    implements GoodsLoanRecapRemoteDataSource {
  DioGoodsLoanRecapRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<GoodsLoanRecapPage> fetch({
    required GoodsLoanRecapFilter filter,
    required int page,
    int perPage = 15,
  }) =>
      _request('rekap-peminjaman-barang', filter, page: page, perPage: perPage);

  @override
  Future<GoodsLoanRecapPage> document(GoodsLoanRecapFilter filter) =>
      _request('rekap-peminjaman-barang/dokumen', filter);

  Future<GoodsLoanRecapPage> _request(
    String path,
    GoodsLoanRecapFilter filter, {
    int? page,
    int? perPage,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: {
          if (filter.query.trim().isNotEmpty) 'kata_kunci': filter.query.trim(),
          'status_pemantauan': filter.monitoringStatus,
          'jenis_peminjam': filter.borrowerType,
          if (filter.borrower.isNotEmpty) 'peminjam': filter.borrower,
          if (filter.goodsId != null) 'barang_id': filter.goodsId,
          if (filter.startDate != null)
            'tanggal_mulai': _date(filter.startDate!),
          if (filter.endDate != null) 'tanggal_selesai': _date(filter.endDate!),
          'halaman': ?page,
          'per_halaman': ?perPage,
        },
      );
      return GoodsLoanRecapPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

final goodsLoanRecapRemoteDataSourceProvider =
    Provider<GoodsLoanRecapRemoteDataSource>(
      (ref) => DioGoodsLoanRecapRemoteDataSource(ref.watch(dioProvider)),
    );

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
