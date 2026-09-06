import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/goods_loan/data/goods_loan_repository.dart';
import 'package:nusa/features/goods_loan/domain/goods_loan.dart';

class GoodsLoanController extends AsyncNotifier<GoodsLoanPage> {
  String _query = '';
  String _borrowerType = 'semua';
  String _status = 'semua';
  DateTime? _startDate;
  DateTime? _endDate;
  int _version = 0;

  @override
  Future<GoodsLoanPage> build() => _fetch(1);
  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String borrowerType,
    required String status,
    required DateTime? startDate,
    required DateTime? endDate,
  }) async {
    _borrowerType = borrowerType;
    _status = status;
    _startDate = startDate;
    _endDate = endDate;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
    } catch (error, stack) {
      if (version == _version) state = AsyncError(error, stack);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    state = AsyncData(
      current.append(await _fetch(current.pagination.page + 1)),
    );
  }

  Future<GoodsLoanPage> _fetch(int page) => _guard(
    () => ref
        .read(goodsLoanRepositoryProvider)
        .fetchLoans(
          query: _query,
          borrowerType: _borrowerType,
          status: _status,
          startDate: _startDate,
          endDate: _endDate,
          page: page,
        ),
  );
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

class GoodsReturnController extends AsyncNotifier<GoodsReturnPage> {
  String _query = '';
  int _version = 0;
  @override
  Future<GoodsReturnPage> build() => _fetch(1);
  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
    } catch (error, stack) {
      if (version == _version) state = AsyncError(error, stack);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    state = AsyncData(
      current.append(await _fetch(current.pagination.page + 1)),
    );
  }

  Future<GoodsReturnPage> _fetch(int page) => _guard(
    () => ref
        .read(goodsLoanRepositoryProvider)
        .fetchReturns(query: _query, page: page),
  );
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final goodsLoanControllerProvider =
    AsyncNotifierProvider.autoDispose<GoodsLoanController, GoodsLoanPage>(
      GoodsLoanController.new,
    );
final goodsReturnControllerProvider =
    AsyncNotifierProvider.autoDispose<GoodsReturnController, GoodsReturnPage>(
      GoodsReturnController.new,
    );
final goodsLoanActionsProvider = Provider<GoodsLoanActions>(
  GoodsLoanActions.new,
);

class GoodsLoanActions {
  GoodsLoanActions(this._ref);
  final Ref _ref;
  Future<GoodsLoanDetailResponse> detail(int id) =>
      _guard(() => _ref.read(goodsLoanRepositoryProvider).detail(id));
  Future<GoodsLoanDetailResponse> create(GoodsLoanFormValue value) =>
      _guard(() => _ref.read(goodsLoanRepositoryProvider).create(value));
  Future<IdentifiedBorrower> identifyBorrower({
    required String code,
    String type = 'otomatis',
  }) => _guard(
    () => _ref
        .read(goodsLoanRepositoryProvider)
        .identifyBorrower(code: code, type: type),
  );
  Future<GoodsLoanAvailableItem> identifyItem({
    required String code,
    int? locationId,
  }) => _guard(
    () => _ref
        .read(goodsLoanRepositoryProvider)
        .identifyItem(code: code, locationId: locationId),
  );
  Future<IdentifiedReturn> identifyReturn(String code) =>
      _guard(() => _ref.read(goodsLoanRepositoryProvider).identifyReturn(code));
  Future<GoodsLoanDetailResponse> returnGoods({
    required int loanId,
    required GoodsReturnFormValue value,
  }) => _guard(
    () => _ref
        .read(goodsLoanRepositoryProvider)
        .returnGoods(loanId: loanId, value: value),
  );
  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
