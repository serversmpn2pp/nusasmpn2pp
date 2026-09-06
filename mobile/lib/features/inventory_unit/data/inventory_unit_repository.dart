import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_unit/data/inventory_unit_remote_data_source.dart';
import 'package:nusa/features/inventory_unit/domain/inventory_unit.dart';

final class InventoryUnitRepository {
  const InventoryUnitRepository(this._remote);

  final InventoryUnitRemoteDataSource _remote;

  Future<InventoryUnitPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(InventoryUnitFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required InventoryUnitFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final inventoryUnitRepositoryProvider = Provider<InventoryUnitRepository>(
  (ref) =>
      InventoryUnitRepository(ref.watch(inventoryUnitRemoteDataSourceProvider)),
);
