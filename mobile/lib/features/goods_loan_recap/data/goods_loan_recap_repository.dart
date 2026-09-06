import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_loan_recap/data/goods_loan_recap_remote_data_source.dart';
import 'package:nusa/features/goods_loan_recap/domain/goods_loan_recap.dart';

class GoodsLoanRecapRepository {
  const GoodsLoanRecapRepository(this._remote);
  final GoodsLoanRecapRemoteDataSource _remote;

  Future<GoodsLoanRecapPage> fetch({
    required GoodsLoanRecapFilter filter,
    required int page,
  }) => _remote.fetch(filter: filter, page: page);
  Future<GoodsLoanRecapPage> document(GoodsLoanRecapFilter filter) =>
      _remote.document(filter);
}

final goodsLoanRecapRepositoryProvider = Provider<GoodsLoanRecapRepository>(
  (ref) => GoodsLoanRecapRepository(
    ref.watch(goodsLoanRecapRemoteDataSourceProvider),
  ),
);
