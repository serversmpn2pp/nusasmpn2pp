import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_receipt/data/goods_receipt_remote_data_source.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';

final class GoodsReceiptRepository {
  const GoodsReceiptRepository(this._remote);

  final GoodsReceiptRemoteDataSource _remote;

  Future<GoodsReceiptPage> fetch({
    required String query,
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
  }) => _remote.fetch(
    query: query,
    sourceId: sourceId,
    startDate: startDate,
    endDate: endDate,
    page: page,
  );

  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(int id) =>
      _remote.detail(id);

  Future<GoodsReceipt> create(GoodsReceiptFormValue value) =>
      _remote.create(value);

  Future<GoodsReceipt> cancel({required int id, required String reason}) =>
      _remote.cancel(id: id, reason: reason);
}

final goodsReceiptRepositoryProvider = Provider<GoodsReceiptRepository>(
  (ref) =>
      GoodsReceiptRepository(ref.watch(goodsReceiptRemoteDataSourceProvider)),
);
