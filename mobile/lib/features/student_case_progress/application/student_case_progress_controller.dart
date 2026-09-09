import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student_case_progress/data/student_case_progress_repository.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

class StudentCaseProgressController
    extends AsyncNotifier<StudentCaseProgressPage> {
  int _version = 0;

  @override
  Future<StudentCaseProgressPage> build() => _fetch(1);

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

  Future<StudentCaseProgressPage> _fetch(int page) => _guard(
    () => ref.read(studentCaseProgressRepositoryProvider).fetch(page: page),
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

final studentCaseProgressControllerProvider =
    AsyncNotifierProvider.autoDispose<
      StudentCaseProgressController,
      StudentCaseProgressPage
    >(StudentCaseProgressController.new);

final studentCaseDetailProvider = FutureProvider.autoDispose
    .family<StudentCaseDetail, int>((ref, id) async {
      try {
        return await ref
            .read(studentCaseProgressRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
