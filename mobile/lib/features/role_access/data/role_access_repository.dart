import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/role_access/data/role_access_remote_data_source.dart';
import 'package:nusa/features/role_access/domain/role_access.dart';

final class RoleAccessRepository {
  RoleAccessRepository(this._remote);

  final RoleAccessRemoteDataSource _remote;

  Future<RoleAccessPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<RoleAccessReference> fetchReference() => _remote.fetchReference();
  Future<RoleAccessDetail> fetchDetail(int roleId) =>
      _remote.fetchDetail(roleId);
  Future<int> create(RoleAccessFormValue value) => _remote.create(value);
  Future<void> update({
    required int roleId,
    required RoleAccessFormValue value,
  }) => _remote.update(roleId: roleId, value: value);
  Future<void> deactivate(int roleId) => _remote.deactivate(roleId);
}

final roleAccessRepositoryProvider = Provider<RoleAccessRepository>(
  (ref) => RoleAccessRepository(ref.watch(roleAccessRemoteDataSourceProvider)),
);
