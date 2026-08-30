import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/report_verification/data/report_verification_repository.dart';
import 'package:nusa/features/report_verification/domain/report_verification.dart';

class ReportVerificationController
    extends AsyncNotifier<ReportVerificationPage> {
  String _query = '';
  String _queue = 'semua';
  Timer? _debounce;
  int _requestVersion = 0;

  @override
  Future<ReportVerificationPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(page: 1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      final next = value.trim();
      if (_query == next) return;
      _query = next;
      unawaited(refresh());
    });
  }

  Future<void> selectQueue(String value) async {
    if (_queue == value) return;
    _queue = value;
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

  Future<ReportVerificationPage> _fetch({required int page}) => _guard(
    () => ref
        .read(reportVerificationRepositoryProvider)
        .fetch(query: _query, queue: _queue, page: page),
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

final reportVerificationControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ReportVerificationController,
      ReportVerificationPage
    >(ReportVerificationController.new);

final reportVerificationDetailProvider = FutureProvider.autoDispose
    .family<ReportVerificationDetail, int>((ref, reportId) async {
      try {
        return await ref
            .read(reportVerificationRepositoryProvider)
            .fetchDetail(reportId);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final reportVerificationActionsProvider =
    Provider<ReportVerificationActions>(ReportVerificationActions.new);

class ReportVerificationActions {
  const ReportVerificationActions(this._ref);

  final Ref _ref;

  Future<ReportVerificationMutation> review({
    required int reportId,
    required String result,
    required List<int> violationIds,
    required String? note,
  }) => _guard(
    () => _ref
        .read(reportVerificationRepositoryProvider)
        .review(
          reportId: reportId,
          result: result,
          violationIds: violationIds,
          note: note,
        ),
    reportId,
  );

  Future<ReportVerificationMutation> approve({
    required int reportId,
    required String decision,
    required String? note,
  }) => _guard(
    () => _ref
        .read(reportVerificationRepositoryProvider)
        .approve(reportId: reportId, decision: decision, note: note),
    reportId,
  );

  Future<T> _guard<T>(Future<T> Function() operation, int reportId) async {
    try {
      final result = await operation();
      _ref.invalidate(reportVerificationControllerProvider);
      _ref.invalidate(reportVerificationDetailProvider(reportId));
      return result;
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
