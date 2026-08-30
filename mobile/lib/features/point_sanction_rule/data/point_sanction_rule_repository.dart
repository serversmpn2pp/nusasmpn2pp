import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/point_sanction_rule/data/point_sanction_rule_remote_data_source.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';

final class PointSanctionRuleRepository {
  PointSanctionRuleRepository(this._remote);

  final PointSanctionRuleRemoteDataSource _remote;

  Future<PointSanctionRulePage> fetch({
    required String query,
    required String status,
  }) => _remote.fetch(query: query, status: status);

  Future<void> create(PointSanctionRuleFormValue value) =>
      _remote.create(value);

  Future<void> update({
    required int id,
    required PointSanctionRuleFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> deactivate(int id) => _remote.deactivate(id);
}

final pointSanctionRuleRepositoryProvider =
    Provider<PointSanctionRuleRepository>(
      (ref) => PointSanctionRuleRepository(
        ref.watch(pointSanctionRuleRemoteDataSourceProvider),
      ),
    );
