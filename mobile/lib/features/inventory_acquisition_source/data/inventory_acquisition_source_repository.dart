import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_acquisition_source/data/inventory_acquisition_source_remote_data_source.dart';
import 'package:nusa/features/inventory_acquisition_source/domain/inventory_acquisition_source.dart';

final class InventoryAcquisitionSourceRepository {
  const InventoryAcquisitionSourceRepository(this._remote);

  final InventoryAcquisitionSourceRemoteDataSource _remote;

  Future<InventoryAcquisitionSourcePage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(InventoryAcquisitionSourceFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required InventoryAcquisitionSourceFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final inventoryAcquisitionSourceRepositoryProvider =
    Provider<InventoryAcquisitionSourceRepository>(
      (ref) => InventoryAcquisitionSourceRepository(
        ref.watch(inventoryAcquisitionSourceRemoteDataSourceProvider),
      ),
    );
