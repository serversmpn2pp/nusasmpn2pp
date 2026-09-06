import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/api_exception_mapper.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';

abstract interface class GoodsLoanRemoteDataSource {
  Future<GoodsLoanPage> fetchLoans({
    required String query,
    required String borrowerType,
    required String status,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  });
  Future<GoodsReturnPage> fetchReturns({
    required String query,
    required int page,
    int perPage = 15,
  });
  Future<GoodsLoanDetailResponse> detail(int id);
  Future<GoodsLoanDetailResponse> create(GoodsLoanFormValue value);
  Future<IdentifiedBorrower> identifyBorrower({
    required String code,
    String type = 'otomatis',
  });
  Future<GoodsLoanAvailableItem> identifyItem({
    required String code,
    int? locationId,
  });
  Future<IdentifiedReturn> identifyReturn(String code);
  Future<GoodsLoanDetailResponse> returnGoods({
    required int loanId,
    required GoodsReturnFormValue value,
  });
}

final class DioGoodsLoanRemoteDataSource implements GoodsLoanRemoteDataSource {
  DioGoodsLoanRemoteDataSource(this._dio);
  final Dio _dio;

  @override
  Future<GoodsLoanPage> fetchLoans({
    required String query,
    required String borrowerType,
    required String status,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peminjaman-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'jenis_peminjam': borrowerType,
          'status': status,
          if (startDate != null) 'tanggal_mulai': _date(startDate),
          if (endDate != null) 'tanggal_selesai': _date(endDate),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GoodsLoanPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsReturnPage> fetchReturns({
    required String query,
    required int page,
    int perPage = 15,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengembalian-barang',
        queryParameters: {
          if (query.trim().isNotEmpty) 'cari': query.trim(),
          'halaman': page,
          'per_halaman': perPage,
        },
      );
      return GoodsReturnPage.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsLoanDetailResponse> detail(int id) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peminjaman-barang/$id',
      );
      return GoodsLoanDetailResponse.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsLoanDetailResponse> create(GoodsLoanFormValue value) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'peminjaman-barang',
        data: {
          'jenis_peminjam': value.borrowerType,
          'siswa_id': value.borrowerType == 'siswa' ? value.borrowerId : null,
          'pegawai_id': value.borrowerType == 'pegawai'
              ? value.borrowerId
              : null,
          'cara_input_peminjam': value.borrowerInputMethod,
          'tanggal_peminjaman': _date(value.date),
          'rencana_kembali': value.plannedReturn == null
              ? null
              : _date(value.plannedReturn!),
          'catatan': _text(value.notes),
          'items': [
            for (final line in value.lines)
              {
                'tipe_item': line.item.type,
                'unit_barang_id': line.item.assetUnitId,
                'barang_id': line.item.goodsId,
                'lokasi_barang_id': line.item.locationId,
                'jumlah': line.quantity,
                'cara_input_barang': line.inputMethod,
                'catatan': _text(line.notes),
              },
          ],
        },
      );
      return GoodsLoanDetailResponse.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<IdentifiedBorrower> identifyBorrower({
    required String code,
    String type = 'otomatis',
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peminjaman-barang/identifikasi-peminjam',
        queryParameters: {'kode': code.trim(), 'jenis_peminjam': type},
      );
      return IdentifiedBorrower.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsLoanAvailableItem> identifyItem({
    required String code,
    int? locationId,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'peminjaman-barang/identifikasi-barang',
        queryParameters: {'kode': code.trim(), 'lokasi_barang_id': ?locationId},
      );
      final data = response.data!['data'] as Map<String, dynamic>;
      if (data['item'] is! Map) {
        throw const FormatException(
          'Barang berada di beberapa lokasi; pilih barang secara manual agar lokasi asal tepat.',
        );
      }
      return GoodsLoanAvailableItem.fromJson(
        Map<String, dynamic>.from(data['item'] as Map),
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<IdentifiedReturn> identifyReturn(String code) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        'pengembalian-barang/identifikasi',
        queryParameters: {'kode': code.trim()},
      );
      return IdentifiedReturn.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }

  @override
  Future<GoodsLoanDetailResponse> returnGoods({
    required int loanId,
    required GoodsReturnFormValue value,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        'peminjaman-barang/$loanId/pengembalian',
        data: {
          'tanggal_pengembalian': _date(value.date),
          'catatan': _text(value.notes),
          'items': [
            for (final line in value.lines)
              {
                'detail_peminjaman_barang_id': line.detailId,
                'jumlah': line.quantity,
                'kondisi_pengembalian': line.condition,
                'cara_input_barang': line.inputMethod,
                'catatan': _text(line.notes),
              },
          ],
        },
      );
      return GoodsLoanDetailResponse.fromJson(
        response.data!['data'] as Map<String, dynamic>,
      );
    } on DioException catch (exception) {
      throw mapDioException(exception);
    }
  }
}

String _date(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
String? _text(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();

final goodsLoanRemoteDataSourceProvider = Provider<GoodsLoanRemoteDataSource>(
  (ref) => DioGoodsLoanRemoteDataSource(ref.watch(dioProvider)),
);
