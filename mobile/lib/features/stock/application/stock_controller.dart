import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/stock/data/stock_repository.dart';
import 'package:nusa/features/stock/domain/stock.dart';

class StockBalanceController extends AsyncNotifier<StockBalancePage> {
  String _query = '';
  String _status = 'semua';
  int? _categoryId;
  int? _locationId;
  int _requestVersion = 0;

  @override
  Future<StockBalancePage> build() => _fetch(1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String status,
    required int? categoryId,
    required int? locationId,
  }) async {
    _status = status;
    _categoryId = categoryId;
    _locationId = locationId;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<StockBalancePage> _fetch(int page) => _guard(
    () => ref
        .read(stockRepositoryProvider)
        .fetchBalances(
          query: _query,
          status: _status,
          categoryId: _categoryId,
          locationId: _locationId,
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

class StockMovementController extends AsyncNotifier<StockMovementPage> {
  String _query = '';
  String _type = 'semua';
  int? _goodsId;
  int? _locationId;
  DateTime? _startDate;
  DateTime? _endDate;
  int _requestVersion = 0;

  @override
  Future<StockMovementPage> build() => _fetch(1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> applyFilters({
    required String type,
    required int? goodsId,
    required int? locationId,
    required DateTime? startDate,
    required DateTime? endDate,
  }) async {
    _type = type;
    _goodsId = goodsId;
    _locationId = locationId;
    _startDate = startDate;
    _endDate = endDate;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<StockMovementPage> _fetch(int page) => _guard(
    () => ref
        .read(stockRepositoryProvider)
        .fetchMovements(
          query: _query,
          type: _type,
          goodsId: _goodsId,
          locationId: _locationId,
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

final stockBalanceControllerProvider =
    AsyncNotifierProvider.autoDispose<StockBalanceController, StockBalancePage>(
      StockBalanceController.new,
    );

final stockMovementControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StockMovementController,
      StockMovementPage
    >(StockMovementController.new);

final stockActionsProvider = Provider<StockActions>(StockActions.new);

class StockActions {
  StockActions(this._ref);
  final Ref _ref;

  Future<StockMovement> detail(int id) =>
      _guard(() => _ref.read(stockRepositoryProvider).movementDetail(id));

  Future<StockMovement> create(StockMovementFormValue value) =>
      _guard(() => _ref.read(stockRepositoryProvider).createMovement(value));

  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
