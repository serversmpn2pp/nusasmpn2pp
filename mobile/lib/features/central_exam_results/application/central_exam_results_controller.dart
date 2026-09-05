import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/central_exam_results/data/central_exam_results_repository.dart';
import 'package:nusa/features/central_exam_results/domain/central_exam_results.dart';

class CentralExamResultsController
    extends AsyncNotifier<CentralExamResultsPage> {
  String _query = '';
  String _status = 'semua';
  Timer? _debounce;

  @override
  Future<CentralExamResultsPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      _query = value.trim();
      unawaited(refresh());
    });
  }

  Future<void> filterStatus(String value) async {
    _status = value;
    await refresh();
  }

  Future<void> refresh() async =>
      state = await AsyncValue.guard(() => _fetch(1));

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<CentralExamResultsPage> _fetch(int page) => _guard(
    () => ref
        .read(centralExamResultsRepositoryProvider)
        .events(query: _query, status: _status, page: page),
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

final centralExamResultsControllerProvider =
    AsyncNotifierProvider.autoDispose<
      CentralExamResultsController,
      CentralExamResultsPage
    >(CentralExamResultsController.new);

typedef CentralExamResultsRequest = ({
  int eventId,
  int? scheduleId,
  int? classId,
  String status,
});

final centralExamResultsDetailProvider = FutureProvider.autoDispose
    .family<CentralExamResultsDetail, CentralExamResultsRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(centralExamResultsRepositoryProvider)
            .detail(
              eventId: request.eventId,
              scheduleId: request.scheduleId,
              classId: request.classId,
              status: request.status,
            );
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final centralExamResultsApplyProvider = Provider(
  (ref) => ({required int eventId, required int scheduleId}) async {
    try {
      return await ref
          .read(centralExamResultsRepositoryProvider)
          .apply(eventId: eventId, scheduleId: scheduleId);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  },
);
