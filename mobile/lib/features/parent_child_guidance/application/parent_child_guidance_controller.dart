import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/parent_child_guidance/data/parent_child_guidance_repository.dart';
import 'package:nusa/features/parent_child_guidance/domain/parent_child_guidance.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';

class ParentChildGuidanceController
    extends AsyncNotifier<ParentChildGuidancePage> {
  ParentChildGuidanceTab _tab = ParentChildGuidanceTab.reports;
  int? _studentId;
  int? _academicYearId;
  int _version = 0;

  @override
  Future<ParentChildGuidancePage> build() => _fetch(1);

  Future<void> refresh() => _replace();

  Future<void> selectTab(ParentChildGuidanceTab tab) async {
    if (_tab == tab && state.value?.filter.tab == tab) return;
    _tab = tab;
    await _replace();
  }

  Future<void> selectStudent(int studentId) async {
    if (_studentId == studentId && state.value?.filter.studentId == studentId) {
      return;
    }
    _studentId = studentId;
    _academicYearId = null;
    await _replace();
  }

  Future<void> selectAcademicYear(int academicYearId) async {
    if (_academicYearId == academicYearId &&
        state.value?.filter.academicYearId == academicYearId) {
      return;
    }
    _academicYearId = academicYearId;
    await _replace();
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) return;
    final next = await _fetch(current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<void> _replace() async {
    final version = ++_version;
    state = const AsyncLoading();
    try {
      final result = await _fetch(1);
      if (version == _version) state = AsyncData(result);
    } catch (error, stackTrace) {
      if (version == _version) state = AsyncError(error, stackTrace);
    }
  }

  Future<ParentChildGuidancePage> _fetch(int page) => _guard(
    () => ref
        .read(parentChildGuidanceRepositoryProvider)
        .fetch(
          tab: _tab,
          page: page,
          studentId: _studentId,
          academicYearId: _academicYearId,
        ),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      final result = await operation();
      _tab = result is ParentChildGuidancePage ? result.filter.tab : _tab;
      if (result is ParentChildGuidancePage) {
        _studentId = result.filter.studentId;
        _academicYearId = result.filter.academicYearId;
      }
      return result;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final parentChildGuidanceControllerProvider =
    AsyncNotifierProvider.autoDispose<
      ParentChildGuidanceController,
      ParentChildGuidancePage
    >(ParentChildGuidanceController.new);

final parentChildGuidanceDetailProvider = FutureProvider.autoDispose
    .family<StudentCaseDetail, int>((ref, id) async {
      try {
        return await ref
            .read(parentChildGuidanceRepositoryProvider)
            .fetchDetail(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
