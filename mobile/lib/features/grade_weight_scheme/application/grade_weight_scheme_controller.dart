import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/grade_weight_scheme/data/grade_weight_scheme_repository.dart';
import 'package:nusa/features/grade_weight_scheme/domain/grade_weight_scheme.dart';

class GradeWeightSchemeController extends AsyncNotifier<GradeWeightSchemePage> {
  int? _academicYearId;
  String _semester = 'semua';
  String _grade = 'semua';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<GradeWeightSchemePage> build() => _fetch(page: 1);

  Future<void> filterAcademicYear(int? value) async {
    if (_academicYearId == value) return;
    _academicYearId = value;
    await refresh();
  }

  Future<void> filterSemester(String value) async {
    if (_semester == value) return;
    _semester = value;
    await refresh();
  }

  Future<void> filterGrade(String value) async {
    if (_grade == value) return;
    _grade = value;
    await refresh();
  }

  Future<void> filterStatus(String value) async {
    if (_status == value) return;
    _status = value;
    await refresh();
  }

  Future<void> refresh() async {
    final current = state.value;
    _academicYearId ??= current?.filter.academicYearId;
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

  Future<GradeWeightSchemePage> _fetch({required int page}) => _guard(
    () => ref
        .read(gradeWeightSchemeRepositoryProvider)
        .fetch(
          academicYearId: _academicYearId,
          semester: _semester,
          grade: _grade,
          status: _status,
          page: page,
        ),
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

final gradeWeightSchemeControllerProvider =
    AsyncNotifierProvider.autoDispose<
      GradeWeightSchemeController,
      GradeWeightSchemePage
    >(GradeWeightSchemeController.new);

final gradeWeightSchemeActionsProvider = Provider<GradeWeightSchemeActions>(
  GradeWeightSchemeActions.new,
);

class GradeWeightSchemeActions {
  GradeWeightSchemeActions(this._ref);

  final Ref _ref;

  Future<void> create(GradeWeightSchemeFormValue value) => _guard(
    () => _ref.read(gradeWeightSchemeRepositoryProvider).create(value),
  );

  Future<void> update({
    required int id,
    required GradeWeightSchemeFormValue value,
  }) => _guard(
    () => _ref
        .read(gradeWeightSchemeRepositoryProvider)
        .update(id: id, value: value),
  );

  Future<void> deactivate(int id) => _guard(
    () => _ref.read(gradeWeightSchemeRepositoryProvider).deactivate(id),
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
