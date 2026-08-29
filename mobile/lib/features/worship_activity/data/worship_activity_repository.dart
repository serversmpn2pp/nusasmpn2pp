import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/worship_activity/data/worship_activity_remote_data_source.dart';
import 'package:nusa/features/worship_activity/domain/worship_activity.dart';

final class WorshipActivityRepository {
  WorshipActivityRepository(this._remote);

  final WorshipActivityRemoteDataSource _remote;

  Future<WorshipActivityPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(WorshipActivityFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required WorshipActivityFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final worshipActivityRepositoryProvider = Provider<WorshipActivityRepository>(
  (ref) => WorshipActivityRepository(
    ref.watch(worshipActivityRemoteDataSourceProvider),
  ),
);
