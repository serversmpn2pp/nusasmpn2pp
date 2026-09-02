import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/guardian_assignment/data/guardian_assignment_repository.dart';
import 'package:nusa/features/guardian_assignment/domain/guardian_assignment.dart';

class GuardianAssignmentController
    extends AsyncNotifier<GuardianAssignmentPage> {
  String _query = '';
  int? _guardianId;
  Timer? _debounce;
  int _version = 0;

  @override
  Future<GuardianAssignmentPage> build() {
    ref.onDispose(() => _debounce?.cancel());
    return _fetch(1);
  }

  void search(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      final next = value.trim();
      if (next == _query) return;
      _query = next;
      unawaited(refresh());
    });
  }

  Future<void> filterGuardian(int? value) async {
    _guardianId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<GuardianAssignmentResult> create(
    GuardianAssignmentPayload payload,
  ) async {
    final result = await _guard(
      () => ref.read(guardianAssignmentRepositoryProvider).create(payload),
    );
    await refresh();
    return result;
  }

  Future<GuardianAssignmentMutation> end(int id) async {
    final result = await _guard(
      () => ref.read(guardianAssignmentRepositoryProvider).end(id),
    );
    await refresh();
    return result;
  }

  Future<GuardianAssignmentPage> _fetch(int page) => _guard(
    () => ref
        .read(guardianAssignmentRepositoryProvider)
        .fetch(query: _query, guardianId: _guardianId, page: page),
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

final guardianAssignmentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      GuardianAssignmentController,
      GuardianAssignmentPage
    >(GuardianAssignmentController.new);
