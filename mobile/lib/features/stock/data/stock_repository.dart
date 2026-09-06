import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/stock/data/stock_remote_data_source.dart';
import 'package:nusa/features/stock/domain/stock.dart';

final class StockRepository {
  const StockRepository(this._remote);
  final StockRemoteDataSource _remote;

  Future<StockBalancePage> fetchBalances({
    required String query,
    required String status,
    required int? categoryId,
    required int? locationId,
    required int page,
  }) => _remote.fetchBalances(
    query: query,
    status: status,
    categoryId: categoryId,
    locationId: locationId,
    page: page,
  );

  Future<StockMovementPage> fetchMovements({
    required String query,
    required String type,
    required int? goodsId,
    required int? locationId,
    required DateTime? startDate,
    required DateTime? endDate,
    required int page,
  }) => _remote.fetchMovements(
    query: query,
    type: type,
    goodsId: goodsId,
    locationId: locationId,
    startDate: startDate,
    endDate: endDate,
    page: page,
  );

  Future<StockMovement> movementDetail(int id) => _remote.movementDetail(id);
  Future<StockMovement> createMovement(StockMovementFormValue value) =>
      _remote.createMovement(value);
}

final stockRepositoryProvider = Provider<StockRepository>(
  (ref) => StockRepository(ref.watch(stockRemoteDataSourceProvider)),
);
