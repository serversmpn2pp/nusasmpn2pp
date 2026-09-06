import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_goods/data/inventory_goods_remote_data_source.dart';
import 'package:nusa/features/inventory_goods/domain/inventory_goods.dart';

final class InventoryGoodsRepository {
  const InventoryGoodsRepository(this._remote);

  final InventoryGoodsRemoteDataSource _remote;

  Future<InventoryGoodsPage> fetch({
    required String query,
    required String status,
    required String type,
    required int? categoryId,
    required int page,
  }) => _remote.fetch(
    query: query,
    status: status,
    type: type,
    categoryId: categoryId,
    page: page,
  );

  Future<void> create(InventoryGoodsFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required InventoryGoodsFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final inventoryGoodsRepositoryProvider = Provider<InventoryGoodsRepository>(
  (ref) => InventoryGoodsRepository(
    ref.watch(inventoryGoodsRemoteDataSourceProvider),
  ),
);
