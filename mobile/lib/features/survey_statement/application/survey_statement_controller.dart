import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/survey_statement/data/survey_statement_repository.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';

class SurveyStatementController extends AsyncNotifier<SurveyStatementPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<SurveyStatementPage> build() => _fetch(page: 1);

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
      final result = await _fetch(page: 1);
      if (version == _requestVersion) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _requestVersion) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<SurveyStatementPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(surveyStatementRepositoryProvider)
          .fetch(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final surveyStatementControllerProvider =
    AsyncNotifierProvider.autoDispose<
      SurveyStatementController,
      SurveyStatementPage
    >(SurveyStatementController.new);

final surveyStatementActionsProvider = Provider<SurveyStatementActions>(
  SurveyStatementActions.new,
);

class SurveyStatementActions {
  SurveyStatementActions(this._ref);

  final Ref _ref;

  Future<void> create(SurveyStatementFormValue value) =>
      _guard(() => _ref.read(surveyStatementRepositoryProvider).create(value));

  Future<void> update({
    required int id,
    required SurveyStatementFormValue value,
  }) => _guard(
    () => _ref
        .read(surveyStatementRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> updateStatus({required int id, required bool active}) => _guard(
    () => _ref
        .read(surveyStatementRepositoryProvider)
        .updateStatus(id: id, active: active),
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
