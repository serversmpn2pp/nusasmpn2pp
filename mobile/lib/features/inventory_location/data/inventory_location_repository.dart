import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_location/data/inventory_location_remote_data_source.dart';
import 'package:nusa/features/inventory_location/domain/inventory_location.dart';

final class InventoryLocationRepository {
  const InventoryLocationRepository(this._remote);

  final InventoryLocationRemoteDataSource _remote;

  Future<InventoryLocationPage> fetch({
    required String query,
    required String status,
    required String type,
    required int page,
  }) => _remote.fetch(query: query, status: status, type: type, page: page);

  Future<void> create(InventoryLocationFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required InventoryLocationFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final inventoryLocationRepositoryProvider =
    Provider<InventoryLocationRepository>(
      (ref) => InventoryLocationRepository(
        ref.watch(inventoryLocationRemoteDataSourceProvider),
      ),
    );
