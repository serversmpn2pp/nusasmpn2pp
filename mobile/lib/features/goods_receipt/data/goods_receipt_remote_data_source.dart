import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';

abstract interface class GoodsReceiptRemoteDataSource {
  Future<GoodsReceiptPage> fetch({
    required String query,
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  });

  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(int id);
  Future<GoodsReceipt> create(GoodsReceiptFormValue value);
  Future<GoodsReceipt> cancel({required int id, required String reason});
}

final class DioGoodsReceiptRemoteDataSource
    implements GoodsReceiptRemoteDataSource {
  DioGoodsReceiptRemoteDataSource(this._dio);

  final Dio _dio;

  @override
  Future<GoodsReceiptPage> fetch({
    required String query,
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'barang-datang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'sumber_perolehan_barang_id': ?sourceId,
          if (startDate != null) 'tanggal_mulai': _date(startDate),
          if (endDate != null) 'tanggal_selesai': _date(endDate),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GoodsReceiptPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(
    int id,
  ) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'barang-datang/$id',
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      return (
        receipt: GoodsReceipt.fromJson(
          data['penerimaan'] as Map<String, dynamic>,
        ),
        access: GoodsReceiptAccess.fromJson(
          data['hak_akses'] as Map<String, dynamic>,
        ),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsReceipt> create(GoodsReceiptFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'barang-datang',
        data: {
          'token_penyimpanan': value.storageToken,
          'tanggal_penerimaan': _date(value.date),
          'sumber_perolehan_barang_id': value.sourceId,
          'cara_perolehan': value.acquisitionMethod,
          'nomor_dokumen': _text(value.documentNumber),
          'asal_barang': _text(value.origin),
          'catatan': _text(value.notes),
          'rincian': [
            for (final line in value.lines)
              {
                'barang_id': line.goodsId,
                'lokasi_barang_id': line.locationId,
                'jumlah': line.quantity,
                'harga_satuan': line.unitPrice,
                'merek': _text(line.brand),
                'tipe': _text(line.model),
                'kondisi': line.condition,
                'keterangan': _text(line.notes),
              },
          ],
        },
      );
      return GoodsReceipt.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsReceipt> cancel({required int id, required String reason}) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(
        'barang-datang/$id/batalkan',
        data: {
          'alasan_pembatalan': reason.trim(),
          'konfirmasi_pembatalan': true,
        },
      );
      return GoodsReceipt.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

final goodsReceiptRemoteDataSourceProvider =
    Provider<GoodsReceiptRemoteDataSource>(
      (ref) => DioGoodsReceiptRemoteDataSource(ref.watch(dioProvider)),
    );
