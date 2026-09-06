import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/goods_loan/data/goods_loan_remote_data_source.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';

final class GoodsLoanRepository {
  const GoodsLoanRepository(this._remote);
  final GoodsLoanRemoteDataSource _remote;

  Future<GoodsLoanPage> fetchLoans({
    required String query,
    required String borrowerType,
    required String status,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
  }) => _remote.fetchLoans(
    query: query,
    borrowerType: borrowerType,
    status: status,
    startDate: startDate,
    endDate: endDate,
    page: page,
  );
  Future<GoodsReturnPage> fetchReturns({
    required String query,
    required int page,
  }) => _remote.fetchReturns(query: query, page: page);
  Future<GoodsLoanDetailResponse> detail(int id) => _remote.detail(id);
  Future<GoodsLoanDetailResponse> create(GoodsLoanFormValue value) =>
      _remote.create(value);
  Future<IdentifiedBorrower> identifyBorrower({
    required String code,
    String type = 'otomatis',
  }) => _remote.identifyBorrower(code: code, type: type);
  Future<GoodsLoanAvailableItem> identifyItem({
    required String code,
    int? locationId,
  }) => _remote.identifyItem(code: code, locationId: locationId);
  Future<IdentifiedReturn> identifyReturn(String code) =>
      _remote.identifyReturn(code);
  Future<GoodsLoanDetailResponse> returnGoods({
    required int loanId,
    required GoodsReturnFormValue value,
  }) => _remote.returnGoods(loanId: loanId, value: value);
}

final goodsLoanRepositoryProvider = Provider<GoodsLoanRepository>(
  (ref) => GoodsLoanRepository(ref.watch(goodsLoanRemoteDataSourceProvider)),
);
