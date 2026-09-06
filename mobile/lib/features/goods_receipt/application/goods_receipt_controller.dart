import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/goods_receipt/data/goods_receipt_repository.dart';
import 'package:nusa/features/goods_receipt/domain/goods_receipt.dart';

class GoodsReceiptController extends AsyncNotifier<GoodsReceiptPage> {
  String _query = '';
  int? _sourceId;
  DateTime? _startDate;
  DateTime? _endDate;
  int _requestVersion = 0;

  @override
  Future<GoodsReceiptPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required int? sourceId,
    required DateTime? startDate,
    required DateTime? endDate,
  }) async {
    _sourceId = sourceId;
    _startDate = startDate;
    _endDate = endDate;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<GoodsReceiptPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(goodsReceiptRepositoryProvider)
          .fetch(
            query: _query,
            sourceId: _sourceId,
            startDate: _startDate,
            endDate: _endDate,
            page: page,
          );
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final goodsReceiptControllerProvider =
    AsyncNotifierProvider.autoDispose<GoodsReceiptController, GoodsReceiptPage>(
      GoodsReceiptController.new,
    );

final goodsReceiptActionsProvider = Provider<GoodsReceiptActions>(
  GoodsReceiptActions.new,
);

class GoodsReceiptActions {
  GoodsReceiptActions(this._ref);

  final Ref _ref;

  Future<({GoodsReceipt receipt, GoodsReceiptAccess access})> detail(int id) =>
      _guard(() => _ref.read(goodsReceiptRepositoryProvider).detail(id));

  Future<GoodsReceipt> create(GoodsReceiptFormValue value) =>
      _guard(() => _ref.read(goodsReceiptRepositoryProvider).create(value));

  Future<GoodsReceipt> cancel({required int id, required String reason}) =>
      _guard(
        () => _ref
            .read(goodsReceiptRepositoryProvider)
            .cancel(id: id, reason: reason),
      );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
