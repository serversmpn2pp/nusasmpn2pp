import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/bk_grade_assignment/data/bk_grade_assignment_repository.dart';
import 'package:nusa/features/bk_grade_assignment/domain/bk_grade_assignment.dart';

class BkGradeAssignmentController extends AsyncNotifier<BkGradeAssignmentPage> {
  int? _academicYearId;
  int _version = 0;

  @override
  Future<BkGradeAssignmentPage> build() => _fetch();

  Future<void> selectAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch();
      _academicYearId = result.selectedAcademicYear?.id;
      if (version == _version) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<BkGradeAssignmentMutation> create(
    BkGradeAssignmentPayload payload,
  ) async {
    final result = await _guard(
      () => ref.read(bkGradeAssignmentRepositoryProvider).create(payload),
    );
    await refresh();
    return result;
  }

  Future<BkGradeAssignmentMutation> end(int id) async {
    final result = await _guard(
      () => ref.read(bkGradeAssignmentRepositoryProvider).end(id),
    );
    await refresh();
    return result;
  }

  Future<BkGradeAssignmentPage> _fetch() => _guard(
    () => ref
        .read(bkGradeAssignmentRepositoryProvider)
        .fetch(academicYearId: _academicYearId),
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

final bkGradeAssignmentControllerProvider =
    AsyncNotifierProvider.autoDispose<
      BkGradeAssignmentController,
      BkGradeAssignmentPage
    >(BkGradeAssignmentController.new);
