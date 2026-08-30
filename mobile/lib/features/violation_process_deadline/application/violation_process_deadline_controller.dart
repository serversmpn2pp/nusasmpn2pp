import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/violation_process_deadline/data/violation_process_deadline_repository.dart';
import 'package:nusa/features/violation_process_deadline/domain/violation_process_deadline.dart';

class ViolationProcessDeadlineController
    extends AsyncNotifier<ViolationProcessDeadlinePage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<ViolationProcessDeadlinePage> build() => _fetch();

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

  Future<ViolationProcessDeadlinePage> _fetch() async {
    try {
      return await ref
          .read(violationProcessDeadlineRepositoryProvider)
          .fetch(query: _query, status: _status);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final violationProcessDeadlineControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ViolationProcessDeadlineController,
      ViolationProcessDeadlinePage
    >(ViolationProcessDeadlineController.new);

final violationProcessDeadlineActionsProvider =
    Provider<ViolationProcessDeadlineActions>(
      ViolationProcessDeadlineActions.new,
    );

class ViolationProcessDeadlineActions {
  ViolationProcessDeadlineActions(this._ref);

  final Ref _ref;

  Future<void> update({
    required int academicYearId,
    required ViolationProcessDeadlineFormValue value,
  }) async {
    try {
      await _ref
          .read(violationProcessDeadlineRepositoryProvider)
          .update(academicYearId: academicYearId, value: value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
