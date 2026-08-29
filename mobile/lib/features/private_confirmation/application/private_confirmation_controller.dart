import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/private_confirmation/data/private_confirmation_repository.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';

class PrivateConfirmationController
    extends AsyncNotifier<PrivateConfirmationPage> {
  String _query = '';
  int? _classId;
  int _requestVersion = 0;

  @override
  Future<PrivateConfirmationPage> build() => _fetch(page: 1);

  Future<void> search(String value) async {
    _query = value.trim();
    await refresh();
  }

  Future<void> selectClass(int? value) async {
    if (_classId == value) return;
    _classId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final result = await _fetch(page: 1);
      _query = result.filter.query;
      _classId = result.filter.classId;
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

  Future<PrivateConfirmationPage> _fetch({required int page}) => _guard(
    () => ref
        .read(privateConfirmationRepositoryProvider)
        .fetch(query: _query, classId: _classId, page: page),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final privateConfirmationControllerProvider =
    AsyncNotifierProvider.autoDispose<
      PrivateConfirmationController,
      PrivateConfirmationPage
    >(PrivateConfirmationController.new);

final privateConfirmationDetailProvider = FutureProvider.autoDispose
    .family<PrivateConfirmationDetail, int>((ref, periodId) async {
      try {
        return await ref
            .read(privateConfirmationRepositoryProvider)
            .fetchDetail(periodId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final privateConfirmationActionsProvider = Provider<PrivateConfirmationActions>(
  PrivateConfirmationActions.new,
);

class PrivateConfirmationActions {
  PrivateConfirmationActions(this._ref);

  final Ref _ref;

  Future<PrivateConfirmationUpdateResult> update({
    required int periodId,
    required String result,
    required int? reminderDays,
    required String? privateNote,
  }) async {
    try {
      final updated = await _ref
          .read(privateConfirmationRepositoryProvider)
          .update(
            periodId: periodId,
            result: result,
            reminderDays: reminderDays,
            privateNote: privateNote,
          );
      _ref.invalidate(privateConfirmationControllerProvider);
      _ref.invalidate(privateConfirmationDetailProvider(periodId));
      return updated;
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
