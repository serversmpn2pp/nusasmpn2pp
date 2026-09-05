import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/central_exam_execution/data/central_exam_execution_repository.dart';
import 'package:nusa/features/central_exam_execution/domain/central_exam_execution.dart';

class CentralExamExecutionController
    extends AsyncNotifier<CentralExamExecutionPage> {
  String _query = '';
  String _status = 'semua';
  Timer? _debounce;
  int _version = 0;

  @override
  Future<CentralExamExecutionPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_query == value.trim()) return;
      _query = value.trim();
      unawaited(refresh());
    });
  }

  Future<void> filterStatus(String status) async {
    _status = status;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final page = await _fetch(1);
      if (version == _version) state = AsyncData(page);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    state = AsyncData(
      current.append(await _fetch(current.pagination.page + 1)),
    );
  }

  Future<CentralExamExecutionPage> _fetch(int page) => _guard(
    () => ref
        .read(centralExamExecutionRepositoryProvider)
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

final centralExamExecutionControllerProvider =
    AsyncNotifierProvider.autoDispose<
      CentralExamExecutionController,
      CentralExamExecutionPage
    >(CentralExamExecutionController.new);

final centralExamExecutionDetailProvider = FutureProvider.autoDispose
    .family<CentralExamExecutionDetail, CentralExamExecutionRequest>((
      ref,
      request,
    ) async {
      try {
        return await ref
            .read(centralExamExecutionRepositoryProvider)
            .detail(request);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

class CentralExamExecutionActions {
  const CentralExamExecutionActions(this.ref);
  final Ref ref;

  Future<String> assignSupervisor({
    required int eventId,
    required int scheduleId,
    required int sourceRoomId,
    required String role,
    required int employeeId,
    required String? reason,
  }) => _guard(
    () => ref
        .read(centralExamExecutionRepositoryProvider)
        .assignSupervisor(
          eventId: eventId,
          scheduleId: scheduleId,
          sourceRoomId: sourceRoomId,
          role: role,
          employeeId: employeeId,
          reason: reason,
        ),
  );

  Future<void> unlockSafeMode(int participantId) => _guard(
    () => ref
        .read(centralExamExecutionRepositoryProvider)
        .unlockSafeMode(participantId),
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

final centralExamExecutionActionsProvider = Provider(
  CentralExamExecutionActions.new,
);
