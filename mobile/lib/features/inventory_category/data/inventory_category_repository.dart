import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/inventory_category/data/inventory_category_remote_data_source.dart';
import 'package:nusa/features/inventory_category/domain/inventory_category.dart';

final class InventoryCategoryRepository {
  const InventoryCategoryRepository(this._remote);

  final InventoryCategoryRemoteDataSource _remote;

  Future<InventoryCategoryPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(InventoryCategoryFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required InventoryCategoryFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final inventoryCategoryRepositoryProvider =
    Provider<InventoryCategoryRepository>(
      (ref) => InventoryCategoryRepository(
        ref.watch(inventoryCategoryRemoteDataSourceProvider),
      ),
    );
