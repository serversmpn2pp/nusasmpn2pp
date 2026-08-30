import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/point_sanction_rule/data/point_sanction_rule_repository.dart';
import 'package:nusa/features/point_sanction_rule/domain/point_sanction_rule.dart';

class PointSanctionRuleController extends AsyncNotifier<PointSanctionRulePage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<PointSanctionRulePage> build() => _fetch();

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<PointSanctionRulePage> _fetch() async {
    try {
      return await ref
          .read(pointSanctionRuleRepositoryProvider)
          .fetch(query: _query, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final pointSanctionRuleControllerProvider =
    AsyncNotifierProvider.autoDispose<
      PointSanctionRuleController,
      PointSanctionRulePage
    >(PointSanctionRuleController.new);

final pointSanctionRuleActionsProvider = Provider<PointSanctionRuleActions>(
  PointSanctionRuleActions.new,
);

class PointSanctionRuleActions {
  PointSanctionRuleActions(this._ref);

  final Ref _ref;

  Future<void> create(PointSanctionRuleFormValue value) => _guard(
    () => _ref.read(pointSanctionRuleRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required PointSanctionRuleFormValue value,
  }) => _guard(
    () => _ref
        .read(pointSanctionRuleRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () => _ref.read(pointSanctionRuleRepositoryProvider).deactivate(id),
  );

  Future<void> _guard(Future<void> Function() operation) async {
    try {
      await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
